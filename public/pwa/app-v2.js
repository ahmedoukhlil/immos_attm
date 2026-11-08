/**
 * Application PWA Inventaire v2 - Workflow par Emplacement
 * Scan QR code emplacement → Scan QR biens → Calcul écarts
 */

console.log('[App v2] Démarrage...');

// ===========================================
// CONFIGURATION
// ===========================================

const CONFIG = {
    API_BASE_URL: document.querySelector('meta[name="api-base-url"]')?.content
        || `${window.location.origin}/api/v1`,
    STORAGE_KEY_TOKEN: 'inventaire_token_v2',
    STORAGE_KEY_USER: 'inventaire_user_v2',
    SESSION: {
        inactivityTimeoutMs: 2 * 60 * 60 * 1000 // 2h
    },
    SCANNER: {
        qr: {
            width: 960,
            height: 540,
            decodeIntervalMs: 80,
            decodeWidth: 400,
            decodeHeight: 300,
            detectCooldownMs: 1200
        },
        barcode: {
            width: 640,
            height: 480,
            frequency: 10,
            detectCooldownMs: 250
        }
    }
};

// ===========================================
// HAPTIC FEEDBACK (Vibrations) - Mobile
// ===========================================

class HapticFeedback {
    static isSupported() {
        return 'vibrate' in navigator;
    }

    static light() {
        if (this.isSupported()) {
            navigator.vibrate(10);
        }
    }

    static medium() {
        if (this.isSupported()) {
            navigator.vibrate(20);
        }
    }

    static heavy() {
        if (this.isSupported()) {
            navigator.vibrate(50);
        }
    }

    static success() {
        if (this.isSupported()) {
            navigator.vibrate([20, 50, 20]);
        }
    }

    static error() {
        if (this.isSupported()) {
            navigator.vibrate([50, 100, 50, 100, 50]);
        }
    }

    static warning() {
        if (this.isSupported()) {
            navigator.vibrate([30, 50, 30]);
        }
    }
}

// ===========================================
// STATE
// ===========================================

const AppState = {
    token: null,
    user: null,
    currentEmplacement: null,
    biensAttendus: [],
    biensScannés: [], // [{ num_ordre, etat_id, photo? }]
    etats: [], // [{ id, label, require_photo }] depuis API /etats
    scannerActive: false,
    modalBienEnCours: null, // bien en attente de confirmation (num_ordre, designation)
    qrProcessing: false,
    qrLastDecodedAt: 0,
    qrLastDetectedAt: 0,
    barcodeProcessing: false,
    barcodeLastDetectedAt: 0,
    barcodeLastCode: null,
    barcodeModalOpen: false,
    barcodeScannerActive: false,
    barcodeNativeLoopActive: false,
    barcodeInputMode: 'scan', // 'scan' | 'manual'
    scanRapide: (() => {
        try { return localStorage.getItem('immos-scan-rapide') === '1'; } catch (_) { return false; }
    })(),
    biensAttendusIndex: new Map(), // key: num_ordre
    biensScannesIndex: new Map(),  // key: num_ordre
    lastToastKey: null,
    lastToastAt: 0,
    barcodeLastInvalidAt: 0,
    lastActivityAt: 0,
    emplacementInputMode: 'scan', // 'scan' | 'manual'
    empTorchEnabled: false,
    bienTorchEnabled: false,
};

// Icônes SVG (Heroicons outline) — mêmes tracés que le composant Blade <x-icon>.
const ICONES = {
    'check-circle': ['M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    'x-circle': ['M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
    'exclamation': ['M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
    'information-circle': ['M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'question-mark-circle': ['M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    'location-marker': ['M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z', 'M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
    'light-bulb': ['M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
};

function icone(nom, classes = 'w-4 h-4') {
    const traces = ICONES[nom] || ICONES['information-circle'];
    return `<svg class="${classes} shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">${
        traces.map(trace => `<path stroke-linecap="round" stroke-linejoin="round" d="${trace}"/>`).join('')
    }</svg>`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// ===========================================
// NORMALISATION CODES SCANNES / NUM_ORDRE
// ===========================================

function normalizeNumOrdre(value) {
    if (value === null || value === undefined) return null;
    const num = Number.parseInt(String(value).trim(), 10);
    return Number.isFinite(num) && num > 0 ? num : null;
}

function finalizationKey(emplacement = AppState.currentEmplacement) {
    const inventoryId = emplacement?.inventaire_id;
    const inventoryEmplacementId = emplacement?.inventaire_emplacement_id || emplacement?.id;
    const userId = AppState.user?.idUser || AppState.user?.id;

    if (!inventoryId || !inventoryEmplacementId || !userId) {
        throw new Error('Le contexte de finalisation est incomplet. Rechargez cet emplacement.');
    }

    return `inventaire:${inventoryId}:emplacement:${inventoryEmplacementId}:user:${userId}`;
}

/**
 * Retrouve le numéro d'ordre (identifiant) d'un bien à partir d'un QR scanné
 * ou d'une saisie manuelle. Formats acceptés :
 *  - « 218/BUR/050/2026 | GS555 » (étiquettes actuelles) et « GS555 » (anciennes) :
 *    le GS<id>, immuable, fait foi ;
 *  - « GS555-ECR » ou « 218/BUR/050/2026-ECR | GS555-ECR » (composants) ;
 *  - « 555 » (saisie manuelle) ;
 *  - « 218/BUR/050/2026 » seul : recherché parmi les biens attendus du site.
 * Les derniers chiffres d'un code d'inventaire ne sont jamais pris pour un identifiant
 * (« 2026 » est une année, pas le bien n°2026).
 */
function extractNumOrdreFromBarcode(rawCode) {
    if (!rawCode) return null;

    // Nettoyer les caractères de contrôle parfois renvoyés par le scanner
    const cleaned = String(rawCode)
        .replace(/[\u0000-\u001F\u007F]/g, '')
        .trim();

    if (!cleaned) return null;

    const parIdentifiant = cleaned.match(/(?:^|[^A-Za-z0-9])GS(\d+)(?![0-9])/i);
    if (parIdentifiant) {
        return normalizeNumOrdre(parIdentifiant[1]);
    }

    if (/^\d+$/.test(cleaned)) {
        return normalizeNumOrdre(cleaned);
    }

    const codeInventaire = cleaned.split('|')[0].trim().toUpperCase();
    const bien = (AppState.biensAttendus || []).find(
        item => item && item.code && String(item.code).toUpperCase() === codeInventaire
    );

    return bien ? normalizeNumOrdre(bien.num_ordre) : null;
}

function rebuildBiensIndexes() {
    AppState.biensAttendusIndex = new Map(
        (AppState.biensAttendus || [])
            .filter(b => b && b.num_ordre)
            .map(b => [b.num_ordre, b])
    );

    AppState.biensScannesIndex = new Map(
        (AppState.biensScannés || [])
            .filter(b => b && b.num_ordre)
            .map(b => [b.num_ordre, b])
    );
}

// ===========================================
// JSQR QR DECODER
// ===========================================

class QRDecoder {
    static hasJsQR() {
        return typeof window.jsQR === 'function';
    }

    static decodeImageData(imageData) {
        if (!this.hasJsQR()) {
            throw new Error('jsQR non chargé');
        }
        const result = window.jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'attemptBoth',
        });
        return result && result.data ? String(result.data) : null;
    }

    static isHarmlessError(error) {
        if (!error) return true;
        const msg = String(error.message || '');
        return msg.includes('jsQR') && msg.includes('non chargé');
    }
}

// ===========================================
// API HELPER
// ===========================================

class API {
    static async request(endpoint, options = {}) {
        const url = `${CONFIG.API_BASE_URL}${endpoint}`;
        const method = options.method || 'GET';
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        if (AppState.token) {
            defaultOptions.headers['Authorization'] = `Bearer ${AppState.token}`;
        }

        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };

        try {
            const response = await fetch(url, finalOptions);
            
            if (!response.ok) {
                const errorText = await response.text();
                let errorData = null;
                try {
                    errorData = JSON.parse(errorText);
                } catch (_) {}

                const apiError = new Error(
                    response.status === 401
                        ? 'Session expirée'
                        : (errorData?.message || `Erreur HTTP ${response.status}`)
                );
                apiError.status = response.status;
                apiError.data = errorData;

                if (response.status === 401) {
                    await AuthManager.logout({ revokeServerToken: false });
                }

                throw apiError;
            }

            return await response.json();
        } catch (error) {
            console.error('[API] Erreur:', error);
            throw error;
        }
    }
}

class OfflineStore {
    static DB_NAME = 'InventaireDB';
    static DB_VERSION = 2;
    static _syncPromise = null;

    static open() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains('pending-operations')) {
                    db.createObjectStore('pending-operations', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('drafts')) {
                    db.createObjectStore('drafts', { keyPath: 'emplacement_id' });
                }
                if (!db.objectStoreNames.contains('emplacements')) {
                    db.createObjectStore('emplacements', { keyPath: 'emplacement_id' });
                }
            };
        });
    }

    static async put(storeName, value) {
        const db = await this.open();
        await new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readwrite');
            transaction.objectStore(storeName).put(value);
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
        db.close();
    }

    static async get(storeName, key) {
        const db = await this.open();
        const result = await new Promise((resolve, reject) => {
            const request = db.transaction(storeName, 'readonly').objectStore(storeName).get(key);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
        db.close();
        return result;
    }

    static async getAll(storeName) {
        const db = await this.open();
        const result = await new Promise((resolve, reject) => {
            const request = db.transaction(storeName, 'readonly').objectStore(storeName).getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
        db.close();
        return result;
    }

    static async delete(storeName, key) {
        const db = await this.open();
        await new Promise((resolve, reject) => {
            const transaction = db.transaction(storeName, 'readwrite');
            transaction.objectStore(storeName).delete(key);
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
        });
        db.close();
    }

    static async cacheEmplacement(response) {
        await this.put('emplacements', {
            emplacement_id: response.emplacement.id,
            response,
            user_id: AppState.user?.idUser || AppState.user?.id,
            cached_at: Date.now(),
        });
    }

    static async saveDraft() {
        if (!AppState.currentEmplacement) return;
        await this.put('drafts', {
            emplacement_id: AppState.currentEmplacement.id,
            biens_scannes: AppState.biensScannés,
            user_id: AppState.user?.idUser || AppState.user?.id,
            updated_at: Date.now(),
        });
    }

    static async queueFinalisation(emplacementId, body) {
        const operationKey = body.idempotency_key || finalizationKey();
        await this.put('pending-operations', {
            id: `finish-${operationKey}`,
            emplacement_id: emplacementId,
            endpoint: `/emplacements/${emplacementId}/terminer`,
            body,
            user_id: AppState.user?.idUser || AppState.user?.id,
            created_at: Date.now(),
        });
        await updateConnectivityStatus();
    }

    static syncPending() {
        if (this._syncPromise) {
            return this._syncPromise;
        }

        this._syncPromise = this._syncPending()
            .finally(() => {
                this._syncPromise = null;
            });

        return this._syncPromise;
    }

    static async _syncPending() {
        if (!navigator.onLine || !AppState.token) {
            await updateConnectivityStatus();
            return;
        }
        const operations = (await this.getAll('pending-operations'))
            .sort((left, right) => (left.created_at || 0) - (right.created_at || 0));
        const currentUserId = AppState.user?.idUser || AppState.user?.id;

        for (const operation of operations) {
            if (operation.user_id && operation.user_id !== currentUserId) {
                continue;
            }

            if (operation.next_retry_at && operation.next_retry_at > Date.now()) {
                continue;
            }

            try {
                await API.request(operation.endpoint, {
                    method: 'POST',
                    body: JSON.stringify(operation.body),
                });
                await this.delete('pending-operations', operation.id);
                if (operation.emplacement_id !== null) {
                    await this.delete('drafts', operation.emplacement_id);
                    await this.delete('emplacements', operation.emplacement_id);
                }
                UI.showToast(`Emplacement ${operation.emplacement_id} synchronisé`, 'success');
            } catch (error) {
                const dejaFinalise = error.status === 409
                    && /déjà\s+terminé/i.test(String(error.message || ''));

                if (dejaFinalise) {
                    await this.delete('pending-operations', operation.id);
                    if (operation.emplacement_id !== null) {
                        await this.delete('drafts', operation.emplacement_id);
                        await this.delete('emplacements', operation.emplacement_id);
                    }
                    UI.showToast(`Emplacement ${operation.emplacement_id} déjà synchronisé`, 'success');
                    continue;
                }

                const attempts = (operation.attempts || 0) + 1;
                const retryDelay = Math.min(30 * 60 * 1000, 30 * 1000 * (2 ** Math.min(attempts - 1, 6)));
                await this.put('pending-operations', {
                    ...operation,
                    attempts,
                    last_error: String(error.message || 'Erreur de synchronisation').slice(0, 500),
                    last_status: error.status || null,
                    last_attempt_at: Date.now(),
                    next_retry_at: Date.now() + retryDelay,
                });

                if (!error.status || error.status === 401 || error.status === 429 || error.status >= 500) {
                    UI.showToast(`Synchronisation temporairement indisponible pour l'emplacement ${operation.emplacement_id}`, 'warning');
                    break;
                }

                UI.showToast(`Emplacement ${operation.emplacement_id} à vérifier : ${error.message}`, 'warning');
            }
        }

        await updateConnectivityStatus();
    }

    static async migrateLegacyPendingScans() {
        const db = await this.open();
        if (!db.objectStoreNames.contains('pending-scans')) {
            db.close();
            return;
        }

        const legacyScans = await new Promise((resolve, reject) => {
            const request = db.transaction('pending-scans', 'readonly').objectStore('pending-scans').getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
        db.close();

        for (const scan of legacyScans) {
            await this.put('pending-operations', {
                id: `legacy-scan-${scan.id}`,
                emplacement_id: null,
                endpoint: `/inventaires/${scan.inventaire_id}/scan`,
                body: scan.data,
                user_id: AppState.user?.idUser || AppState.user?.id,
                created_at: scan.timestamp || Date.now(),
            });

            const legacyDb = await this.open();
            await new Promise((resolve, reject) => {
                const transaction = legacyDb.transaction('pending-scans', 'readwrite');
                transaction.objectStore('pending-scans').delete(scan.id);
                transaction.oncomplete = () => resolve();
                transaction.onerror = () => reject(transaction.error);
            });
            legacyDb.close();
        }
    }

    /**
     * Charge l'inventaire en cours et les sites de l'agent (2 requêtes), puis
     * renvoie les sites à précharger pour le mode hors ligne.
     */
    static async loadAssignedInventory() {
        if (!navigator.onLine || !AppState.token) return [];

        const current = await API.request('/inventaires/current');
        const inventoryId = current.inventaire.id;
        const assigned = await API.request(`/inventaires/${inventoryId}/mes-localisations`);
        UI.renderMesSites(assigned.localisations || [], { sauvegarder: true });

        return (assigned.localisations || []).flatMap(
            item => (item.localisation?.emplacements || []).map(emplacement => emplacement.id)
        );
    }

    /**
     * Précharge les biens de chaque site pour le mode hors ligne, en arrière-plan :
     * l'agent peut déjà scanner pendant ce temps.
     */
    static async prefetchEmplacements(emplacementIds) {
        if (this.prefetchEnCours || emplacementIds.length === 0) return;
        this.prefetchEnCours = true;
        let termines = 0;

        try {
            for (let index = 0; index < emplacementIds.length; index += 4) {
                if (!navigator.onLine || !AppState.token) break;
                const batch = emplacementIds.slice(index, index + 4);
                await Promise.allSettled(batch.map(async emplacementId => {
                    const response = await API.request(`/emplacements/${emplacementId}/biens`);
                    await this.cacheEmplacement(response);
                }));
                termines += batch.length;
                UI.afficherPrechargement(termines, emplacementIds.length);
            }
        } finally {
            this.prefetchEnCours = false;
        }
    }
}

async function updateConnectivityStatus() {
    const status = document.getElementById('connectivity-status');
    if (!status) return;

    let pendingCount = 0;
    let retryCount = 0;
    try {
        const operations = await OfflineStore.getAll('pending-operations');
        const currentUserId = AppState.user?.idUser || AppState.user?.id;
        const userOperations = currentUserId
            ? operations.filter(operation => !operation.user_id || operation.user_id === currentUserId)
            : operations;
        pendingCount = userOperations.length;
        retryCount = userOperations.filter(operation => operation.last_error).length;
    } catch (_) {}

    const online = navigator.onLine;
    status.className = online
        ? 'mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-xs font-semibold text-emerald-800'
        : 'mb-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-900';

    if (!online) {
        status.textContent = pendingCount > 0
            ? `Hors ligne — ${pendingCount} opération(s) en attente${retryCount ? `, dont ${retryCount} à vérifier` : ''}`
            : 'Hors ligne — les scans restent enregistrés sur cet appareil';
        return;
    }

    status.textContent = pendingCount > 0
        ? `En ligne — ${pendingCount} opération(s) en attente${retryCount ? `, dont ${retryCount} à vérifier` : ''}`
        : 'En ligne — données synchronisées';
}

// ===========================================
// AUTH MANAGER
// ===========================================

class AuthManager {
    static async login(username, password) {
        try {
            const response = await API.request('/login', {
                method: 'POST',
                body: JSON.stringify({ 
                    users: username, 
                    mdp: password 
                })
            });

            AppState.token = response.token;
            AppState.user = response.user;
            
            sessionStorage.setItem(CONFIG.STORAGE_KEY_TOKEN, response.token);
            sessionStorage.setItem(CONFIG.STORAGE_KEY_USER, JSON.stringify(response.user));
            localStorage.setItem(CONFIG.STORAGE_KEY_TOKEN, response.token);
            localStorage.setItem(CONFIG.STORAGE_KEY_USER, JSON.stringify(response.user));

            await UI.loadEtats();

            HapticFeedback.success();
            UI.updateUserInfo();
            SessionSecurityManager.start();
            await UI.showInventoryHome();
            UI.showToast('Connexion réussie', 'success');
        } catch (error) {
            HapticFeedback.error();
            throw error;
        }
    }

    static async logout(options = {}) {
        const reason = options.reason || 'manual';
        const showToast = options.showToast !== false;
        const revokeServerToken = options.revokeServerToken !== false;

        if (revokeServerToken && AppState.token) {
            try {
                await fetch(`${CONFIG.API_BASE_URL}/logout`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${AppState.token}`,
                    },
                });
            } catch (_) {}
        }

        SessionSecurityManager.stop();
        ScannerManager.stopScanner();
        BarcodeScannerManager.stopBarcodeScanner();

        AppState.token = null;
        AppState.user = null;
        sessionStorage.removeItem(CONFIG.STORAGE_KEY_TOKEN);
        sessionStorage.removeItem(CONFIG.STORAGE_KEY_USER);
        await this.clearSensitiveClientData();
        await updateConnectivityStatus();
        
        HapticFeedback.medium();
        UI.showView('login');
        if (showToast) {
            if (reason === 'inactivity') {
                UI.showToast('Déconnexion automatique après 2h d\'inactivité', 'warning');
            } else {
                UI.showToast('Déconnexion', 'info');
            }
        }
    }

    static async checkAuth() {
        const token = sessionStorage.getItem(CONFIG.STORAGE_KEY_TOKEN)
            || localStorage.getItem(CONFIG.STORAGE_KEY_TOKEN);
        const userStr = sessionStorage.getItem(CONFIG.STORAGE_KEY_USER)
            || localStorage.getItem(CONFIG.STORAGE_KEY_USER);

        if (token && userStr) {
            try {
                AppState.token = token;
                AppState.user = JSON.parse(userStr);
                // Restaurer aussi le stockage de session pour les accès suivants.
                sessionStorage.setItem(CONFIG.STORAGE_KEY_TOKEN, token);
                sessionStorage.setItem(CONFIG.STORAGE_KEY_USER, userStr);
                localStorage.setItem(CONFIG.STORAGE_KEY_TOKEN, token);
                localStorage.setItem(CONFIG.STORAGE_KEY_USER, userStr);
                await UI.loadEtats();
                UI.updateUserInfo();
                SessionSecurityManager.start();
                await UI.showInventoryHome();
                return true;
            } catch {
                await this.logout();
            }
        }
        return false;
    }

    static async clearSensitiveClientData() {
        try {
            localStorage.removeItem(CONFIG.STORAGE_KEY_TOKEN);
            localStorage.removeItem(CONFIG.STORAGE_KEY_USER);
        } catch (_) {}

        try {
            sessionStorage.removeItem(CONFIG.STORAGE_KEY_TOKEN);
            sessionStorage.removeItem(CONFIG.STORAGE_KEY_USER);
        } catch (_) {}

        // Supprime les cookies accessibles en JavaScript (HttpOnly non accessibles côté client)
        try {
            const host = window.location.hostname;
            const baseCookies = document.cookie ? document.cookie.split(';') : [];
            baseCookies.forEach(raw => {
                const cookieName = raw.split('=')[0].trim();
                if (!cookieName) return;
                const expires = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
                document.cookie = `${cookieName}=; ${expires}; path=/`;
                document.cookie = `${cookieName}=; ${expires}; path=/; domain=${host}`;
                if (host.includes('.')) {
                    document.cookie = `${cookieName}=; ${expires}; path=/; domain=.${host}`;
                }
            });
        } catch (_) {}

        try {
            const cacheNames = await caches.keys();
            await Promise.all(
                cacheNames
                    .filter(name => name.startsWith('inventaire-scanner-'))
                    .map(name => caches.delete(name))
            );
        } catch (_) {}

        try {
            const pendingOperations = await OfflineStore.getAll('pending-operations');
            if (pendingOperations.length === 0) {
                indexedDB.deleteDatabase('InventaireDB');
            }
        } catch (_) {}
    }
}

// ===========================================
// SESSION SECURITY MANAGER
// ===========================================

class SessionSecurityManager {
    static _timer = null;
    static _boundActivityHandler = null;
    static _boundVisibilityHandler = null;
    static _events = ['click', 'keydown', 'touchstart', 'pointerdown', 'scroll', 'mousemove'];

    static start() {
        if (!AppState.token) return;
        this.stop();
        AppState.lastActivityAt = Date.now();

        this._boundActivityHandler = () => this.registerActivity();
        this._boundVisibilityHandler = () => {
            if (document.visibilityState === 'visible') {
                this.checkNow();
            }
        };

        this._events.forEach(evt => {
            window.addEventListener(evt, this._boundActivityHandler, { passive: true });
        });
        document.addEventListener('visibilitychange', this._boundVisibilityHandler);

        this.schedule();
    }

    static stop() {
        clearTimeout(this._timer);
        this._timer = null;

        if (this._boundActivityHandler) {
            this._events.forEach(evt => {
                window.removeEventListener(evt, this._boundActivityHandler);
            });
        }
        if (this._boundVisibilityHandler) {
            document.removeEventListener('visibilitychange', this._boundVisibilityHandler);
        }

        this._boundActivityHandler = null;
        this._boundVisibilityHandler = null;
    }

    static registerActivity() {
        if (!AppState.token) return;
        AppState.lastActivityAt = Date.now();
        this.schedule();
    }

    static schedule() {
        clearTimeout(this._timer);
        if (!AppState.token) return;

        const elapsed = Date.now() - (AppState.lastActivityAt || 0);
        const remaining = CONFIG.SESSION.inactivityTimeoutMs - elapsed;
        if (remaining <= 0) {
            this.handleInactivityTimeout();
            return;
        }

        this._timer = setTimeout(() => this.checkNow(), remaining);
    }

    static checkNow() {
        if (!AppState.token) return;
        const elapsed = Date.now() - (AppState.lastActivityAt || 0);
        if (elapsed >= CONFIG.SESSION.inactivityTimeoutMs) {
            this.handleInactivityTimeout();
            return;
        }
        this.schedule();
    }

    static handleInactivityTimeout() {
        AuthManager.logout({ reason: 'inactivity', showToast: true });
    }
}

// ===========================================
// SCANNER MANAGER - QR Code pour Emplacement
// ===========================================

class ScannerManager {
    static async startQRScanner() {
        if (AppState.emplacementInputMode === 'manual') {
            return;
        }

        // Vérifier que jsQR est disponible
        if (!QRDecoder.hasJsQR()) {
            console.error('[Scanner] jsQR n\'est pas chargé');
            HapticFeedback.error();
            UI.showToast('Erreur: Bibliothèque QR code non chargée. Rechargez la page.', 'error');
            return;
        }

        const container = document.getElementById('scanner-container');
        this.stopScanner();
        container.innerHTML = `
            <video id="qr-video" class="w-full h-full object-cover" autoplay playsinline muted></video>
            <button id="emp-torch-toggle" class="hidden absolute top-2 right-2 z-20 px-3 py-1.5 rounded-full text-xs font-semibold bg-black/60 text-white backdrop-blur-sm">
                ${icone('light-bulb', 'inline-block w-4 h-4 mr-1 align-[-3px]')}Torche off
            </button>
            <div class="scan-overlay">
                <div class="scan-reticle">
                    <span class="scan-corner tl"></span>
                    <span class="scan-corner tr"></span>
                    <span class="scan-corner bl"></span>
                    <span class="scan-corner br"></span>
                </div>
                <span class="scan-overlay-hint">Placez le QR dans le cadre</span>
            </div>
        `;

        try {
            // Configuration optimisée pour mobile Android et iOS
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment', // Caméra arrière
                    width: { ideal: CONFIG.SCANNER.qr.width, max: 1280 },
                    height: { ideal: CONFIG.SCANNER.qr.height, max: 720 },
                    aspectRatio: { ideal: 16 / 9 },
                    frameRate: { ideal: 24, max: 30 }
                },
                audio: false
            });

            const video = document.getElementById('qr-video');
            video.srcObject = stream;
            this._videoTrack = stream.getVideoTracks()[0] || null;
            this._setupTorchToggle();

            await new Promise(resolve => {
                video.addEventListener('loadedmetadata', resolve, { once: true });
            });
            await video.play();

            console.log('[Scanner] Caméra prête:', video.videoWidth, 'x', video.videoHeight);
            AppState.scannerActive = true;
            HapticFeedback.light();
            this.detectQRCode(video);

        } catch (error) {
            console.error('[Scanner] Erreur caméra:', error);
            HapticFeedback.error();
            
            let errorMessage = 'Impossible d\'accéder à la caméra';
            if (error.name === 'NotAllowedError') {
                errorMessage = 'Permission caméra refusée. Autorisez l\'accès dans les paramètres.';
            } else if (error.name === 'NotFoundError') {
                errorMessage = 'Aucune caméra trouvée';
            } else if (error.name === 'NotReadableError') {
                errorMessage = 'Caméra déjà utilisée par une autre application';
            }
            
            UI.showToast(errorMessage, 'error');
        }
    }

    static detectQRCode(video) {
        if (!QRDecoder.hasJsQR()) {
            console.error('[Scanner] jsQR n\'est pas chargé');
            HapticFeedback.error();
            UI.showToast('Erreur: Bibliothèque QR code non chargée', 'error');
            return;
        }

        this._ensureCanvas();
        clearTimeout(this._loopTimer);

        const dw = CONFIG.SCANNER.qr.decodeWidth;
        const dh = CONFIG.SCANNER.qr.decodeHeight;

        const tick = () => {
            if (!AppState.scannerActive) return;

            if (AppState.qrProcessing) {
                this._loopTimer = setTimeout(tick, CONFIG.SCANNER.qr.decodeIntervalMs);
                return;
            }

            if (video.readyState >= video.HAVE_CURRENT_DATA && video.videoWidth > 0) {
                const vw = video.videoWidth;
                const vh = video.videoHeight;
                let decodedText = null;

                // Passe 1 : frame complète downscalée
                this._ctx.drawImage(video, 0, 0, dw, dh);
                const fullData = this._ctx.getImageData(0, 0, dw, dh);
                decodedText = QRDecoder.decodeImageData(fullData);

                // Passe 2 : crop central 60% (zoom sur le centre, meilleur pour petits QR)
                if (!decodedText) {
                    const r2 = 0.6;
                    const sw2 = vw * r2, sh2 = vh * r2;
                    this._ctx.drawImage(video, (vw - sw2) / 2, (vh - sh2) / 2, sw2, sh2, 0, 0, dw, dh);
                    decodedText = QRDecoder.decodeImageData(this._ctx.getImageData(0, 0, dw, dh));
                }

                if (decodedText) {
                    console.log('[Scanner] QR Code détecté:', decodedText);
                    this.handleQRCodeDetected(decodedText);
                    return;
                }
            }

            this._loopTimer = setTimeout(tick, CONFIG.SCANNER.qr.decodeIntervalMs);
        };

        tick();
    }

    static _ensureCanvas() {
        if (!this._canvas || !this._ctx) {
            this._canvas = document.createElement('canvas');
            this._canvas.width = CONFIG.SCANNER.qr.decodeWidth;
            this._canvas.height = CONFIG.SCANNER.qr.decodeHeight;
            this._ctx = this._canvas.getContext('2d', { willReadFrequently: true });
        }
    }

    static _setupTorchToggle() {
        const btn = document.getElementById('emp-torch-toggle');
        const track = this._videoTrack;
        AppState.empTorchEnabled = false;

        if (!btn || !track || typeof track.getCapabilities !== 'function') {
            if (btn) btn.classList.add('hidden');
            return;
        }

        const caps = track.getCapabilities();
        if (!caps || !caps.torch) {
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        this._updateTorchButton(btn, false);
        btn.onclick = () => this._toggleTorch(btn);
    }

    static async _toggleTorch(btn) {
        const track = this._videoTrack;
        if (!track) return;

        const next = !AppState.empTorchEnabled;
        try {
            await track.applyConstraints({ advanced: [{ torch: next }] });
            AppState.empTorchEnabled = next;
            this._updateTorchButton(btn, next);
        } catch (_) {
            AppState.empTorchEnabled = false;
            this._updateTorchButton(btn, false);
            UI.showToast('Torche non supportée sur cet appareil', 'warning');
        }
    }

    static _updateTorchButton(btn, enabled) {
        btn.innerHTML = `${icone('light-bulb', 'inline-block w-4 h-4 mr-1 align-[-3px]')}${enabled ? 'Torche on' : 'Torche off'}`;
        btn.classList.toggle('bg-emerald-600/90', enabled);
        btn.classList.toggle('bg-black/60', !enabled);
    }

    static async handleQRCodeDetected(data) {
        const now = Date.now();
        if (AppState.qrProcessing || (now - AppState.qrLastDetectedAt) < CONFIG.SCANNER.qr.detectCooldownMs) {
            return;
        }
        AppState.qrProcessing = true;
        AppState.qrLastDetectedAt = now;

        console.log('[Scanner] QR Code détecté:', data);
        
        let idEmplacement = null;

        // Format 1: EMP-{id} (format standard)
        const matchEmp = data.match(/^EMP-(\d+)$/);
        if (matchEmp) {
            idEmplacement = parseInt(matchEmp[1], 10);
            console.log('[Scanner] Format EMP-{id} détecté, ID:', idEmplacement);
        }

        // Format 2: JSON {"type":"...","id":...,"code":"..."}
        if (!idEmplacement) {
            try {
                const json = JSON.parse(data);
                if (json && json.id) {
                    if (json.type === 'emplacement') {
                        idEmplacement = parseInt(json.id, 10);
                        console.log('[Scanner] Format JSON emplacement détecté, ID:', idEmplacement);
                    } else if (json.type === 'localisation') {
                        console.log('[Scanner] Format JSON localisation détecté, ID:', json.id);
                        HapticFeedback.warning();
                        UI.showToast(`Ceci est un QR de localisation, pas d'emplacement. Utilisez la PWA v1 ou générez des QR codes d'emplacements.`, 'warning');
                        return;
                    } else {
                        idEmplacement = parseInt(json.id, 10);
                        console.log('[Scanner] Format JSON type "' + json.type + '" détecté, tentative avec ID:', idEmplacement);
                    }
                }
            } catch (e) {
                // Pas du JSON, continuer
            }
        }

        // Format 3: Nombre seul (juste l'ID)
        if (!idEmplacement && /^\d+$/.test(data.trim())) {
            idEmplacement = parseInt(data.trim(), 10);
            console.log('[Scanner] Format nombre seul détecté, ID:', idEmplacement);
        }

        if (!idEmplacement) {
            console.warn('[Scanner] Format QR Code non reconnu:', data);
            HapticFeedback.warning();
            UI.showToast(`QR Code non reconnu. Formats acceptés: EMP-{id} ou JSON avec id.`, 'warning');
            AppState.qrProcessing = false;
            return;
        }

        console.log('[Scanner] ID Emplacement extrait:', idEmplacement);
        
        this.stopScanner();
        
        HapticFeedback.success();
        UI.showToast('Chargement de l\'emplacement...', 'info');

        try {
            const currentUserId = AppState.user?.idUser || AppState.user?.id;
            const pendingFinalisation = (await OfflineStore.getAll('pending-operations'))
                .find(operation => operation.emplacement_id === idEmplacement
                    && operation.user_id === currentUserId);
            if (pendingFinalisation) {
                throw new Error('La finalisation de cet emplacement est déjà en attente de synchronisation.');
            }

            let response;
            let loadedOffline = false;
            try {
                response = await API.request(`/emplacements/${idEmplacement}/biens`);
                await OfflineStore.cacheEmplacement(response);
            } catch (networkError) {
                const cached = await OfflineStore.get('emplacements', idEmplacement);
                const currentUserId = AppState.user?.idUser || AppState.user?.id;
                if (!cached || cached.user_id !== currentUserId || networkError.status) {
                    throw networkError;
                }
                response = cached.response;
                loadedOffline = true;
            }
            console.log('[Scanner] Réponse API:', response);
            
            const dejaScannesSet = new Set(
                (response.biens_deja_scannes || []).map(scan => normalizeNumOrdre(scan.num_ordre))
            );

            AppState.currentEmplacement = {
                ...response.emplacement,
                inventaire_id: response.inventaire_id,
                inventaire_emplacement_id: response.inventaire_emplacement?.id,
            };
            // En réouverture, ne garder que les biens restants à scanner
            AppState.biensAttendus = (response.biens || [])
                .map(bien => ({
                    ...bien,
                    num_ordre: normalizeNumOrdre(bien.num_ordre)
                }))
                .filter(bien => bien.num_ordre && !dejaScannesSet.has(bien.num_ordre));
            AppState.biensScannés = (response.biens_deja_scannes || []).map(scan => ({
                num_ordre: normalizeNumOrdre(scan.num_ordre),
                etat_id: null, // Historique existant sans correspondance fiable idEtat
                photo: null,
                designation: null,
                categorie: null,
                statut: 'present',
                emplacement_initial: null,
                is_preloaded_expected: true
            }));

            const draft = await OfflineStore.get('drafts', idEmplacement);
            if (draft?.user_id === currentUserId && draft?.biens_scannes?.length) {
                AppState.biensScannés = draft.biens_scannes;
            }
            rebuildBiensIndexes();

            HapticFeedback.medium();
            UI.showEmplacementView();

            const invLoc = response.inventaire_localisation;
            if (invLoc?.reopened) {
                UI.showToast(`Emplacement réouvert: ${invLoc.biens_restants} bien(s) restant(s) à scanner`, 'info');
            } else if (AppState.biensScannés.length > 0) {
                UI.showToast(`ℹ️ ${AppState.biensScannés.length} bien(s) déjà scanné(s) chargés`, 'info');
            }
            if (loadedOffline) {
                UI.showToast('Données locales chargées — la finalisation sera synchronisée au retour du réseau', 'warning');
            }

        } catch (error) {
            console.error('[Scanner] Erreur API:', error);
            HapticFeedback.error();
            UI.showToast('Erreur: ' + error.message, 'error');
        } finally {
            AppState.qrProcessing = false;
        }
    }

    static stopScanner() {
        AppState.scannerActive = false;
        clearTimeout(this._loopTimer);
        this._loopTimer = null;
        AppState.empTorchEnabled = false;
        this._videoTrack = null;
        const video = document.getElementById('qr-video');
        if (video && video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
    }
}

// ===========================================
// QR SCANNER BIENS - QR code (jsQR + BarcodeDetector)
// ===========================================

class BarcodeScannerManager {
    static async startBarcodeScanner() {
        const container = document.getElementById('barcode-scanner-container');
        this.stopBarcodeScanner();
        container.innerHTML = `
            <video id="bien-qr-video" class="w-full h-full object-cover" autoplay playsinline muted></video>
            <button id="bien-torch-toggle" class="hidden absolute top-2 right-2 z-20 px-3 py-1.5 rounded-full text-xs font-semibold bg-black/60 text-white backdrop-blur-sm">
                ${icone('light-bulb', 'inline-block w-4 h-4 mr-1 align-[-3px]')}Torche off
            </button>
            <div class="scan-overlay">
                <div class="scan-reticle">
                    <span class="scan-corner tl"></span>
                    <span class="scan-corner tr"></span>
                    <span class="scan-corner bl"></span>
                    <span class="scan-corner br"></span>
                </div>
                <span class="scan-overlay-hint">Placez le QR dans le cadre</span>
            </div>
        `;

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'environment',
                    width: { ideal: CONFIG.SCANNER.qr.width, max: 1280 },
                    height: { ideal: CONFIG.SCANNER.qr.height, max: 720 },
                    aspectRatio: { ideal: 16 / 9 },
                    frameRate: { ideal: 24, max: 30 }
                },
                audio: false
            });

            const video = document.getElementById('bien-qr-video');
            video.srcObject = stream;
            this._videoTrack = stream.getVideoTracks()[0] || null;
            this._setupTorchToggle();

            await new Promise(resolve => {
                video.addEventListener('loadedmetadata', resolve, { once: true });
            });
            await video.play();

            console.log('[QR Biens] Caméra prête:', video.videoWidth, 'x', video.videoHeight);
            AppState.barcodeScannerActive = true;
            AppState.barcodeNativeLoopActive = true;
            HapticFeedback.light();
            this.startQrDetectionLoop(video);
        } catch (error) {
            console.error('[QR Biens] Erreur caméra:', error);
            HapticFeedback.error();
            UI.showToast('Impossible d\'accéder à la caméra pour scanner les QR', 'error');
        }
    }

    static startQrDetectionLoop(video) {
        if (!QRDecoder.hasJsQR()) {
            console.error('[QR Biens] jsQR n\'est pas chargé');
            HapticFeedback.error();
            UI.showToast('Erreur: Bibliothèque QR code non chargée', 'error');
            return;
        }

        this._setupNativeDetector();
        this._ensureCanvas();
        clearTimeout(this._loopTimer);

        const dw = this._canvas.width;
        const dh = this._canvas.height;

        const tick = () => {
            if (!AppState.barcodeNativeLoopActive || !AppState.barcodeScannerActive) return;

            if (AppState.barcodeModalOpen || AppState.barcodeProcessing) {
                this._loopTimer = setTimeout(tick, CONFIG.SCANNER.qr.decodeIntervalMs);
                return;
            }

            if (video.readyState >= video.HAVE_CURRENT_DATA && video.videoWidth > 0) {
                // Passe 1 : frame complète (QR peut être n'importe où)
                this._drawDecodeFrame(video, null);

                if (this._nativeDetector && !this._nativeDetectPending) {
                    this._nativeDetectPending = true;
                    this._nativeDetector.detect(this._canvas)
                        .then(results => {
                            if (!results || results.length === 0) return;
                            const raw = String(results[0].rawValue || '').trim();
                            this.consumeDetectedValue(raw);
                        })
                        .catch(() => {})
                        .finally(() => { this._nativeDetectPending = false; });
                }

                let decodedText = null;
                try {
                    decodedText = QRDecoder.decodeImageData(this._ctx.getImageData(0, 0, dw, dh));
                } catch (e) {
                    console.error('[QR Biens] jsQR erreur passe 1:', e);
                }

                // Passe 2 : crop central 60% (zoom sur petit QR centré)
                if (!decodedText) {
                    try {
                        this._drawDecodeFrame(video, 0.6);
                        decodedText = QRDecoder.decodeImageData(this._ctx.getImageData(0, 0, dw, dh));
                    } catch (e) {
                        console.error('[QR Biens] jsQR erreur passe 2:', e);
                    }
                }

                if (decodedText) {
                    const rawValue = String(decodedText).trim();
                    this.consumeDetectedValue(rawValue);
                }
            }

            this._loopTimer = setTimeout(tick, CONFIG.SCANNER.qr.decodeIntervalMs);
        };

        tick();
    }

    static consumeDetectedValue(rawValue) {
        if (!rawValue || AppState.barcodeModalOpen || AppState.barcodeProcessing) return false;
        const now = Date.now();
        if (
            AppState.barcodeLastCode === rawValue &&
            (now - AppState.barcodeLastDetectedAt) < CONFIG.SCANNER.barcode.detectCooldownMs
        ) {
            return false;
        }
        AppState.barcodeLastCode = rawValue;
        AppState.barcodeLastDetectedAt = now;
        this.handleBarcodeDetected(rawValue);
        return true;
    }

    static _ensureCanvas() {
        if (!this._canvas || !this._ctx) {
            this._canvas = document.createElement('canvas');
            this._canvas.width = CONFIG.SCANNER.qr.decodeWidth;
            this._canvas.height = CONFIG.SCANNER.qr.decodeHeight;
            this._ctx = this._canvas.getContext('2d', { willReadFrequently: true });
        }
    }

    static _drawDecodeFrame(video, cropRatio) {
        const srcW = video.videoWidth || 0;
        const srcH = video.videoHeight || 0;
        if (srcW <= 0 || srcH <= 0) return;

        if (!cropRatio || cropRatio >= 1) {
            this._ctx.drawImage(video, 0, 0, this._canvas.width, this._canvas.height);
            return;
        }

        const sw = srcW * cropRatio;
        const sh = srcH * cropRatio;
        const sx = (srcW - sw) / 2;
        const sy = (srcH - sh) / 2;
        this._ctx.drawImage(video, sx, sy, sw, sh, 0, 0, this._canvas.width, this._canvas.height);
    }

    static _setupNativeDetector() {
        this._nativeDetector = null;
        this._nativeDetectPending = false;
        if (typeof window.BarcodeDetector === 'undefined') return;
        try {
            this._nativeDetector = new window.BarcodeDetector({ formats: ['qr_code'] });
        } catch (_) {
            this._nativeDetector = null;
        }
    }

    static _setupTorchToggle() {
        const btn = document.getElementById('bien-torch-toggle');
        const track = this._videoTrack;
        AppState.bienTorchEnabled = false;

        if (!btn || !track || typeof track.getCapabilities !== 'function') {
            if (btn) btn.classList.add('hidden');
            return;
        }

        const caps = track.getCapabilities();
        if (!caps || !caps.torch) {
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        this._updateTorchButton(btn, false);
        btn.onclick = () => this._toggleTorch(btn);
    }

    static async _toggleTorch(btn) {
        const track = this._videoTrack;
        if (!track) return;

        const next = !AppState.bienTorchEnabled;
        try {
            await track.applyConstraints({ advanced: [{ torch: next }] });
            AppState.bienTorchEnabled = next;
            this._updateTorchButton(btn, next);
        } catch (_) {
            AppState.bienTorchEnabled = false;
            this._updateTorchButton(btn, false);
            UI.showToast('Torche non supportée sur cet appareil', 'warning');
        }
    }

    static _updateTorchButton(btn, enabled) {
        btn.innerHTML = `${icone('light-bulb', 'inline-block w-4 h-4 mr-1 align-[-3px]')}${enabled ? 'Torche on' : 'Torche off'}`;
        btn.classList.toggle('bg-emerald-600/90', enabled);
        btn.classList.toggle('bg-black/60', !enabled);
    }

    static async handleBarcodeDetected(codeBarre) {
        if (AppState.barcodeProcessing) return;
        AppState.barcodeProcessing = true;

        console.log('[Barcode] Détecté:', codeBarre);

        const numOrdre = extractNumOrdreFromBarcode(codeBarre);
        if (!numOrdre) {
            HapticFeedback.warning();
            const now = Date.now();
            // Eviter de saturer l'UI avec des toasts d'erreur sur faux positifs
            if ((now - AppState.barcodeLastInvalidAt) > 1500) {
                UI.showToast('QR code invalide ou format non reconnu', 'warning');
                AppState.barcodeLastInvalidAt = now;
            }
            AppState.barcodeProcessing = false;
            return;
        }

        // Vérifier si déjà scanné
        if (AppState.biensScannesIndex.has(numOrdre)) {
            HapticFeedback.warning();
            UI.showToast('Déjà scanné', 'warning');
            AppState.barcodeProcessing = false;
            return;
        }

        // Chercher dans les biens attendus de cet emplacement
        const bien = AppState.biensAttendusIndex.get(numOrdre);

        if (bien && AppState.scanRapide) {
            UI.enregistrerScanRapide(bien);
        } else if (bien) {
            HapticFeedback.light();
            UI.showModalEtatBien(bien);
        } else {
            // Bien pas dans cet emplacement → accepter comme "non attendu"
            // La détection de l'emplacement d'origine se fera au clic "Terminer" côté serveur
            console.log('[Barcode] Bien non attendu dans cet emplacement, N°', numOrdre);
            HapticFeedback.warning();
            UI.showToast(`Bien N°${numOrdre} non attendu dans cet emplacement`, 'warning');
            
            const bienNonAttendu = {
                num_ordre: numOrdre,
                designation: `Bien non attendu`,
                categorie: '-',
                etat: '-',
                statut: 'non_attendu',
                emplacement_initial: null
            };
            UI.showModalEtatBien(bienNonAttendu);
        }

        // Le reset est géré à la fermeture/confirmation de la modale
    }

    static stopBarcodeScanner() {
        AppState.barcodeScannerActive = false;
        AppState.barcodeNativeLoopActive = false;
        clearTimeout(this._loopTimer);
        this._loopTimer = null;
        this._nativeDetector = null;
        this._nativeDetectPending = false;
        AppState.bienTorchEnabled = false;
        this._videoTrack = null;
        const video = document.getElementById('bien-qr-video');
        if (video && video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
        }
        console.log('[QR Biens] Scanner arrêté');
    }
}

// ===========================================
// UI MANAGER
// ===========================================

class UI {
    static async loadEtats() {
        if (!AppState.token) return;
        try {
            const response = await API.request('/etats');
            AppState.etats = response.etats || [];
        } catch (error) {
            console.warn('[UI] Erreur chargement états:', error);
            AppState.etats = [];
        }
    }

    static showView(viewName) {
        document.querySelectorAll('[id^="view-"]').forEach(view => {
            view.classList.add('hidden');
        });

        const view = document.getElementById(`view-${viewName}`);
        if (view) {
            view.classList.remove('hidden');
        }

        // Cacher le header sur login
        const header = document.getElementById('app-header');
        if (viewName === 'login') {
            header.classList.add('hidden');
        } else {
            header.classList.remove('hidden');
        }

        const startScannerBtn = document.getElementById('start-scanner-btn');

        // Gestion lifecycle caméra scanner emplacement
        if (viewName === 'scanner') {
            if (startScannerBtn) startScannerBtn.style.display = 'none';
            this.setEmplacementInputMode(AppState.emplacementInputMode);
        } else {
            ScannerManager.stopScanner();
            if (startScannerBtn) startScannerBtn.style.display = '';
        }

        // Fermer le scanner biens dès qu'on quitte la vue emplacement
        if (viewName !== 'emplacement-biens') {
            BarcodeScannerManager.stopBarcodeScanner();
        }
    }

    static updateUserInfo() {
        if (AppState.user) {
            document.getElementById('user-name').textContent = AppState.user.name || 'Agent';
            document.getElementById('menu-user-name').textContent = AppState.user.name || 'Agent';
        }
    }

    static async showInventoryHome() {
        try {
            await OfflineStore.migrateLegacyPendingScans();
            await OfflineStore.syncPending();
            const emplacementIds = await OfflineStore.loadAssignedInventory();
            this.showView('scanner');
            OfflineStore.prefetchEmplacements(emplacementIds)
                .catch(error => console.warn('[UI] Préchargement hors ligne interrompu:', error));
        } catch (error) {
            const noActiveInventory = error?.status === 404
                && String(error.message || '').toLowerCase().includes('inventaire');

            if (noActiveInventory) {
                this.showView('no-inventory');
                return;
            }

            console.warn('[UI] Préparation hors ligne incomplète:', error);
            this.renderMesSitesDepuisCache();
            this.showView('scanner');
            this.showToast('Mode hors ligne : certaines données peuvent être indisponibles.', 'warning');
        }
    }

    static showEmplacementView() {
        this.showView('emplacement-biens');

        const emp = AppState.currentEmplacement;
        document.getElementById('emplacement-nom').textContent = emp.nom;
        
        let details = emp.code;
        if (emp.localisation) details += ` • ${emp.localisation.nom}`;
        if (emp.affectation) details += ` • ${emp.affectation.nom}`;
        document.getElementById('emplacement-details').textContent = details;

        document.getElementById('biens-count').textContent = `${AppState.biensAttendus.length} bien(s)`;

        this.updateBiensList();
        this.updateProgress();
        this.setBienInputMode('scan');
    }

    static setEmplacementInputMode(mode) {
        const scanBtn = document.getElementById('tab-emp-scan');
        const manualBtn = document.getElementById('tab-emp-manual');
        const scanPanel = document.getElementById('emp-mode-scan');
        const manualPanel = document.getElementById('emp-mode-manual');
        const manualInput = document.getElementById('manual-emp-input');

        AppState.emplacementInputMode = mode === 'manual' ? 'manual' : 'scan';

        if (AppState.emplacementInputMode === 'manual') {
            scanPanel.classList.add('hidden');
            manualPanel.classList.remove('hidden');
            scanBtn.classList.remove('bg-indigo-600', 'text-white');
            scanBtn.classList.add('bg-white', 'text-gray-700');
            manualBtn.classList.remove('bg-white', 'text-gray-700');
            manualBtn.classList.add('bg-indigo-600', 'text-white');
            ScannerManager.stopScanner();
            setTimeout(() => manualInput?.focus(), 50);
            return;
        }

        manualPanel.classList.add('hidden');
        scanPanel.classList.remove('hidden');
        manualBtn.classList.remove('bg-indigo-600', 'text-white');
        manualBtn.classList.add('bg-white', 'text-gray-700');
        scanBtn.classList.remove('bg-white', 'text-gray-700');
        scanBtn.classList.add('bg-indigo-600', 'text-white');

        const scannerViewVisible = !document.getElementById('view-scanner')?.classList.contains('hidden');
        if (scannerViewVisible) {
            ScannerManager.startQRScanner();
        }
    }

    static cleMesSites() {
        return `immos-mes-sites-${AppState.user?.idUser || AppState.user?.id || 'anonyme'}`;
    }

    static afficherPrechargement(termines, total) {
        const zone = document.getElementById('mes-sites-prechargement');
        if (!zone) return;
        zone.textContent = termines >= total
            ? `Mode hors ligne prêt : ${total} sites enregistrés sur l'appareil.`
            : `Préparation du mode hors ligne : ${termines}/${total} sites…`;
        zone.classList.remove('hidden');
    }

    static renderMesSitesDepuisCache() {
        try {
            const cache = JSON.parse(localStorage.getItem(this.cleMesSites()) || 'null');
            if (Array.isArray(cache)) {
                this.renderMesSites(cache);
            }
        } catch (_) {}
    }

    static async rafraichirMesSites() {
        if (!navigator.onLine || !AppState.token) {
            this.renderMesSitesDepuisCache();
            return;
        }
        try {
            const current = await API.request('/inventaires/current');
            const assigned = await API.request(`/inventaires/${current.inventaire.id}/mes-localisations`);
            this.renderMesSites(assigned.localisations || [], { sauvegarder: true });
        } catch (_) {
            this.renderMesSitesDepuisCache();
        }
    }

    static renderMesSites(localisations, { sauvegarder = false } = {}) {
        const section = document.getElementById('mes-sites');
        const liste = document.getElementById('mes-sites-list');
        if (!section || !liste) return;

        if (sauvegarder) {
            try {
                localStorage.setItem(this.cleMesSites(), JSON.stringify(localisations));
            } catch (_) {}
        }

        const statuts = {
            termine: { libelle: 'Terminé', classes: 'bg-green-100 text-green-700' },
            en_cours: { libelle: 'En cours', classes: 'bg-blue-100 text-blue-700' },
        };
        const tousLesSites = localisations.flatMap(item => item.localisation?.emplacements || []);
        if (tousLesSites.length === 0) {
            section.classList.add('hidden');
            return;
        }

        const termines = tousLesSites.filter(site => site.statut === 'termine').length;
        document.getElementById('mes-sites-resume').textContent = `${termines}/${tousLesSites.length} terminés`;

        liste.innerHTML = localisations
            .filter(item => (item.localisation?.emplacements || []).length > 0)
            .map(item => {
                const sites = [...item.localisation.emplacements]
                    .sort((a, b) => (a.statut === 'termine') - (b.statut === 'termine')
                        || String(a.nom || '').localeCompare(String(b.nom || ''), 'fr'));
                const lignes = sites.map(site => {
                    const statut = statuts[site.statut] || { libelle: 'À faire', classes: 'bg-gray-100 text-gray-700' };
                    const termine = site.statut === 'termine';
                    const compteur = site.nombre_biens_attendus
                        ? `${site.nombre_biens_scannes || 0}/${site.nombre_biens_attendus}`
                        : '';

                    return `
                        <li>
                            <button type="button" data-site-id="${escapeHtml(site.id)}" ${termine ? 'disabled' : ''}
                                class="touch-target touch-feedback w-full flex items-center gap-3 rounded-lg px-2 py-2.5 text-left ${termine ? 'opacity-60' : 'hover:bg-indigo-50 active:bg-indigo-100'}">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900">${escapeHtml(site.nom || 'Site')}</span>
                                    <span class="block text-[11px] font-mono text-gray-400">${escapeHtml(site.code || 'EMP-' + site.id)}</span>
                                </span>
                                ${compteur ? `<span class="text-[11px] tabular-nums text-gray-500">${escapeHtml(compteur)}</span>` : ''}
                                <span class="rounded-md px-2 py-0.5 text-[10px] font-medium ${statut.classes}">${statut.libelle}</span>
                            </button>
                        </li>`;
                }).join('');

                return `
                    <div class="mb-1">
                        <p class="px-2 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">${escapeHtml(item.localisation.localisation || item.localisation.designation || '')}</p>
                        <ul>${lignes}</ul>
                    </div>`;
            })
            .join('');

        liste.querySelectorAll('button[data-site-id]:not([disabled])').forEach(bouton => {
            bouton.addEventListener('click', () => {
                ScannerManager.handleQRCodeDetected(`EMP-${bouton.dataset.siteId}`);
            });
        });

        section.classList.remove('hidden');
    }

    static submitManualEmplacementCode() {
        const input = document.getElementById('manual-emp-input');
        const raw = String(input?.value || '').trim();
        if (!raw) {
            HapticFeedback.warning();
            this.showToast('Veuillez saisir un code/ID emplacement', 'warning');
            input?.focus();
            return;
        }
        ScannerManager.handleQRCodeDetected(raw);
        if (input) {
            input.value = '';
            input.focus();
        }
    }

    static setBienInputMode(mode) {
        const scanBtn = document.getElementById('tab-bien-scan');
        const manualBtn = document.getElementById('tab-bien-manual');
        const scanPanel = document.getElementById('bien-mode-scan');
        const manualPanel = document.getElementById('bien-mode-manual');
        const manualInput = document.getElementById('manual-num-ordre-input');

        AppState.barcodeInputMode = mode === 'manual' ? 'manual' : 'scan';

        if (AppState.barcodeInputMode === 'manual') {
            scanPanel.classList.add('hidden');
            manualPanel.classList.remove('hidden');
            scanBtn.classList.remove('bg-indigo-600', 'text-white');
            scanBtn.classList.add('bg-white', 'text-gray-700');
            manualBtn.classList.remove('bg-white', 'text-gray-700');
            manualBtn.classList.add('bg-indigo-600', 'text-white');

            BarcodeScannerManager.stopBarcodeScanner();
            setTimeout(() => manualInput?.focus(), 50);
            return;
        }

        manualPanel.classList.add('hidden');
        scanPanel.classList.remove('hidden');
        manualBtn.classList.remove('bg-indigo-600', 'text-white');
        manualBtn.classList.add('bg-white', 'text-gray-700');
        scanBtn.classList.remove('bg-white', 'text-gray-700');
        scanBtn.classList.add('bg-indigo-600', 'text-white');

        if (AppState.currentEmplacement) {
            BarcodeScannerManager.startBarcodeScanner();
        }
    }

    static submitManualNumOrdre() {
        const input = document.getElementById('manual-num-ordre-input');
        const raw = String(input?.value || '').trim();
        const numOrdre = extractNumOrdreFromBarcode(raw);

        if (!numOrdre) {
            HapticFeedback.warning();
            this.showToast('Veuillez saisir un numéro d\'ordre valide', 'warning');
            input?.focus();
            return;
        }

        if (AppState.barcodeProcessing || AppState.barcodeModalOpen) {
            this.showToast('Traitement en cours, veuillez patienter...', 'info');
            return;
        }

        BarcodeScannerManager.handleBarcodeDetected(String(numOrdre));
        input.value = '';
        input.focus();
    }

    static updateBiensList() {
        const list = document.getElementById('biens-list');
        list.innerHTML = '';
        const scansByNum = AppState.biensScannesIndex;

        // Afficher les biens attendus
        AppState.biensAttendus.forEach(bien => {
            const scanData = scansByNum.get(bien.num_ordre);
            const isScanned = !!scanData;
            const etatObj = scanData && scanData.etat_id ? AppState.etats.find(e => e.id === scanData.etat_id) : null;
            const isDefectueux = etatObj && etatObj.require_photo;
            const etatLabel = etatObj ? etatObj.label : '';
            
            const item = document.createElement('div');
            item.className = `p-3 ${isScanned ? (isDefectueux ? 'bg-amber-50' : 'bg-green-50') : 'bg-white'}`;
            item.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="font-medium text-gray-900 text-sm">${escapeHtml(bien.designation)}</p>
                        <p class="text-xs text-gray-600">N° ${escapeHtml(bien.num_ordre)} • ${escapeHtml(bien.categorie)}${isScanned ? ' • ' + escapeHtml(etatLabel) : ''}</p>
                    </div>
                    <div class="ml-3">
                        ${isScanned ? 
                            '<svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>' :
                            '<svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>'
                        }
                    </div>
                </div>
            `;
            list.appendChild(item);
        });

        // Afficher uniquement les biens réellement non attendus/déplacés
        const biensNonAttendus = AppState.biensScannés.filter(
            s => s.statut === 'non_attendu' || s.statut === 'deplace'
        );
        if (biensNonAttendus.length > 0) {
            const separator = document.createElement('div');
            separator.className = 'p-2 bg-amber-100 text-center';
            separator.innerHTML = `<p class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700">${icone('exclamation', 'w-3.5 h-3.5')} Biens non attendus dans cet emplacement</p>`;
            list.appendChild(separator);

            biensNonAttendus.forEach(scanData => {
                const etatObj = scanData.etat_id ? AppState.etats.find(e => e.id === scanData.etat_id) : null;
                const etatLabel = etatObj ? etatObj.label : '';

                const item = document.createElement('div');
                item.className = 'p-3 bg-amber-50';
                item.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <p class="font-medium text-amber-900 text-sm">Bien N°${escapeHtml(scanData.num_ordre)}</p>
                            <p class="text-xs text-amber-700">Non attendu${etatLabel ? ' • ' + escapeHtml(etatLabel) : ''}</p>
                        </div>
                        <div class="ml-3">
                            <svg class="w-6 h-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        </div>
                    </div>
                `;
                list.appendChild(item);
            });
        }
    }

    static updateProgress() {
        const total = AppState.biensAttendus.length;
        const scannedAttendus = Array.from(AppState.biensScannesIndex.keys())
            .filter(num => AppState.biensAttendusIndex.has(num)).length;
        const scannedDeplaces = AppState.biensScannés.filter(
            s => s.statut === 'non_attendu' || s.statut === 'deplace'
        ).length;
        const percent = total > 0 ? Math.round((scannedAttendus / total) * 100) : 0;

        let progressText = `${scannedAttendus}/${total} biens scannés`;
        if (scannedDeplaces > 0) {
            progressText += ` + ${scannedDeplaces} non attendu(s)`;
        }
        document.getElementById('progress-text').textContent = progressText;
        document.getElementById('progress-percent').textContent = `${percent}%`;
        document.getElementById('progress-bar').style.width = `${percent}%`;
    }

    static async showResultatsView() {
        BarcodeScannerManager.stopBarcodeScanner();

        UI.showToast('Calcul des écarts...', 'info');

        try {
            // Format: [{ num_ordre, etat_id, photo? }] - utilise table etat
            const biensPayload = AppState.biensScannés.map(b => ({
                num_ordre: b.num_ordre,
                etat_id: b.etat_id || null,
                photo: b.photo || null
            }));

            const requestBody = {
                biens_scannes: biensPayload,
                inventaire_id: AppState.currentEmplacement.inventaire_id,
                idempotency_key: finalizationKey(),
            };
            const response = await API.request(
                `/emplacements/${AppState.currentEmplacement.id}/terminer`,
                {
                    method: 'POST',
                    body: JSON.stringify(requestBody)
                }
            );

            await OfflineStore.delete('drafts', AppState.currentEmplacement.id);
            await OfflineStore.delete('pending-operations', `finish-${requestBody.idempotency_key}`);
            await OfflineStore.delete('emplacements', AppState.currentEmplacement.id);

            this.showView('resultats');
            this.displayResultats(response);

        } catch (error) {
            if (!navigator.onLine || !error.status) {
                const emplacementId = AppState.currentEmplacement.id;
                const requestBody = {
                    biens_scannes: AppState.biensScannés.map(b => ({
                        num_ordre: b.num_ordre,
                        etat_id: b.etat_id || null,
                        photo: b.photo || null
                    })),
                    inventaire_id: AppState.currentEmplacement.inventaire_id,
                    idempotency_key: finalizationKey(),
                };
                await OfflineStore.queueFinalisation(emplacementId, requestBody);
                await OfflineStore.saveDraft();
                AppState.currentEmplacement = null;
                AppState.biensAttendus = [];
                AppState.biensScannés = [];
                rebuildBiensIndexes();
                UI.showView('scanner');
                UI.showToast('Finalisation enregistrée localement et en attente de synchronisation', 'warning');
                return;
            }
            UI.showToast('Erreur: ' + error.message, 'error');
        }
    }

    static showModalEtatBien(bien) {
        AppState.modalBienEnCours = bien;
        AppState.barcodeModalOpen = true;
        const modal = document.getElementById('modal-etat-bien');
        const nonAttendu = bien.statut === 'non_attendu';
        document.getElementById('modal-etat-designation').textContent = nonAttendu
            ? 'Bien non attendu sur ce site'
            : (bien.designation || 'Désignation inconnue');
        const descriptif = document.getElementById('modal-etat-descriptif');
        descriptif.textContent = bien.descriptif || '';
        descriptif.classList.toggle('hidden', !bien.descriptif);
        document.getElementById('modal-etat-code').textContent = [bien.code, `GS${bien.num_ordre}`]
            .filter(Boolean)
            .join(' · ');
        document.getElementById('modal-etat-details').textContent = nonAttendu
            ? 'Ses informations seront vérifiées à la fin du site.'
            : [
                bien.categorie && bien.categorie !== '-' && bien.categorie !== 'N/A' ? bien.categorie : null,
                [bien.marque, bien.modele].filter(Boolean).join(' ') || null,
                bien.numero_chassis ? `Châssis : ${bien.numero_chassis}` : null,
                bien.numero_attm ? `ATTM : ${bien.numero_attm}` : null,
                bien.code_immo ? `Code immo : ${bien.code_immo}` : null,
                bien.etat && bien.etat !== '-' && bien.etat !== 'N/A' ? `État enregistré : ${bien.etat}` : null,
            ].filter(Boolean).join(' · ');
        
        // Boutons dynamiques depuis API /etats
        const container = document.getElementById('modal-etat-buttons');
        container.innerHTML = '';
        if (AppState.etats.length > 0) {
            AppState.etats.forEach(etat => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'modal-etat-btn touch-target py-3 px-4 rounded-xl border-2 border-gray-200 text-sm font-medium text-gray-700 hover:border-indigo-500 hover:bg-indigo-50 transition';
                btn.dataset.etatId = etat.id;
                btn.dataset.requirePhoto = etat.require_photo ? '1' : '0';
                btn.textContent = etat.label;
                container.appendChild(btn);
            });
        } else {
            // Fallback si états non chargés (envoie null -> API utilise 'bon')
            const fallback = [{ id: null, label: 'Bon', require_photo: false }];
            fallback.forEach(etat => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'modal-etat-btn touch-target py-3 px-4 rounded-xl border-2 border-gray-200 text-sm font-medium text-gray-700 hover:border-indigo-500 hover:bg-indigo-50 transition';
                btn.dataset.etatId = etat.id ?? '';
                btn.dataset.requirePhoto = etat.require_photo ? '1' : '0';
                btn.textContent = etat.label;
                container.appendChild(btn);
            });
        }
        
        // Reset modal state
        document.getElementById('modal-etat-photo-section').classList.add('hidden');
        document.getElementById('modal-etat-photo-input').value = '';
        document.getElementById('modal-etat-photo-preview').classList.add('hidden');
        document.getElementById('modal-etat-confirmer').disabled = true;
        
        modal.classList.remove('hidden');
    }

    static hideModalEtatBien() {
        AppState.modalBienEnCours = null;
        AppState.barcodeModalOpen = false;
        AppState.barcodeProcessing = false;
        document.getElementById('modal-etat-bien').classList.add('hidden');
    }

    /**
     * Mode scan rapide : enregistre le bien avec l'état « Bon » (ou l'état par
     * défaut du serveur si aucun état « Bon » n'est paramétré) sans fenêtre.
     */
    static enregistrerScanRapide(bien) {
        const etatBon = AppState.etats.find(etat => String(etat.code || '').toUpperCase() === 'BON')
            || AppState.etats.find(etat => /^bon/i.test(String(etat.label || '')));
        AppState.modalBienEnCours = bien;
        this.confirmModalEtatBien(etatBon?.id ?? null, null);
    }

    static confirmModalEtatBien(etatId, photoBase64) {
        if (!AppState.modalBienEnCours) return;
        
        const bien = AppState.modalBienEnCours;
        AppState.biensScannés.push({
            num_ordre: bien.num_ordre,
            etat_id: etatId ? parseInt(etatId, 10) : null,
            photo: photoBase64 || null,
            designation: bien.designation || null,
            categorie: bien.categorie || null,
            statut: bien.statut || 'present',
            emplacement_initial: bien.emplacement_initial || null
        });
        rebuildBiensIndexes();
        OfflineStore.saveDraft().catch(() => {});
        
        const isNonAttendu = bien.statut === 'non_attendu' || bien.statut === 'deplace';
        HapticFeedback.success();
        UI.showToast(`${bien.designation} (N°${bien.num_ordre})`, isNonAttendu ? 'warning' : 'success');
        UI.updateBiensList();
        UI.updateProgress();
        UI.hideModalEtatBien();
    }

    static displayResultats(data) {
        const stats = data.statistiques;

        document.getElementById('stat-scannes').textContent = stats.total_scanne;
        document.getElementById('stat-manquants').textContent = stats.total_manquant;
        document.getElementById('stat-deplaces').textContent = stats.total_en_trop || 0;
        
        document.getElementById('conformite-bar').style.width = `${stats.taux_conformite}%`;
        document.getElementById('conformite-text').textContent = `${stats.taux_conformite}%`;

        // Biens manquants
        if (data.biens_manquants && data.biens_manquants.length > 0) {
            document.getElementById('section-manquants').classList.remove('hidden');
            const listManquants = document.getElementById('list-manquants');
            listManquants.innerHTML = '';

            data.biens_manquants.forEach(bien => {
                const item = document.createElement('div');
                item.className = 'bg-red-50 border-l-4 border-red-500 p-3 rounded';
                item.innerHTML = `
                    <p class="font-medium text-red-800">${escapeHtml(bien.designation)}</p>
                    <p class="text-sm text-red-600">N° ${escapeHtml(bien.num_ordre)} • ${escapeHtml(bien.categorie)}</p>
                `;
                listManquants.appendChild(item);
            });
        } else {
            document.getElementById('section-manquants').classList.add('hidden');
        }

        // Biens déplacés (scannés ici mais enregistrés dans un autre emplacement)
        if (data.biens_en_trop && data.biens_en_trop.length > 0) {
            document.getElementById('section-deplaces').classList.remove('hidden');
            const listDeplaces = document.getElementById('list-deplaces');
            listDeplaces.innerHTML = '';

            data.biens_en_trop.forEach(bien => {
                const empInit = bien.emplacement_initial;
                const origin = empInit ? `${empInit.nom || ''} ${empInit.affectation ? '• ' + empInit.affectation : ''}` : 'Inconnu';
                const item = document.createElement('div');
                item.className = 'bg-amber-50 border-l-4 border-amber-500 p-3 rounded';
                item.innerHTML = `
                    <p class="font-medium text-amber-800">${escapeHtml(bien.designation)}</p>
                    <p class="text-sm text-amber-700">N° ${escapeHtml(bien.num_ordre)} • ${escapeHtml(bien.categorie)}</p>
                    <p class="text-xs text-amber-600 mt-1 flex items-center gap-1">${icone('location-marker', 'w-3.5 h-3.5')} Emplacement d'origine : ${escapeHtml(origin)}</p>
                `;
                listDeplaces.appendChild(item);
            });
        } else {
            document.getElementById('section-deplaces').classList.add('hidden');
        }

        // Biens introuvables (code-barres ne correspondant à aucun bien en BD)
        if (data.biens_introuvables && data.biens_introuvables.length > 0) {
            const sectionDeplaces = document.getElementById('section-deplaces');
            sectionDeplaces.classList.remove('hidden');
            const listDeplaces = document.getElementById('list-deplaces');

            const separatorIntrouvable = document.createElement('div');
            separatorIntrouvable.className = 'bg-gray-100 p-2 rounded text-center mt-3';
            separatorIntrouvable.innerHTML = `<p class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600">${icone('question-mark-circle', 'w-3.5 h-3.5')} Biens introuvables en base de données</p>`;
            listDeplaces.appendChild(separatorIntrouvable);

            data.biens_introuvables.forEach(numOrdre => {
                const item = document.createElement('div');
                item.className = 'bg-gray-50 border-l-4 border-gray-400 p-3 rounded';
                item.innerHTML = `
                    <p class="font-medium text-gray-800">Bien N°${escapeHtml(numOrdre)}</p>
                    <p class="text-sm text-gray-600">Non trouvé dans la base de données</p>
                `;
                listDeplaces.appendChild(item);
            });
        }
    }

    static showToast(message, type = 'info') {
        const now = Date.now();
        const toastKey = `${type}:${message}`;
        if (AppState.lastToastKey === toastKey && (now - AppState.lastToastAt) < 900) {
            return;
        }
        AppState.lastToastKey = toastKey;
        AppState.lastToastAt = now;

        const container = document.getElementById('toast-container');
        
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };

        const icones = {
            success: 'check-circle',
            error: 'x-circle',
            warning: 'exclamation',
            info: 'information-circle'
        };

        const toast = document.createElement('div');
        toast.className = `${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg flex items-start gap-2`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = icone(icones[type] || icones.info, 'w-5 h-5 mt-0.5');
        const texte = document.createElement('span');
        texte.textContent = message;
        toast.appendChild(texte);

        container.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// ===========================================
// EVENT LISTENERS
// ===========================================

// Login
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('login-users').value;
    const password = document.getElementById('login-mdp').value;
    const btn = document.getElementById('login-btn');
    const btnText = document.getElementById('login-btn-text');
    const spinner = document.getElementById('login-spinner');
    const errorDiv = document.getElementById('login-error');

    btn.disabled = true;
    btnText.textContent = 'Connexion...';
    spinner.classList.remove('hidden');
    errorDiv.classList.add('hidden');

    try {
        await AuthManager.login(username, password);
    } catch (error) {
        errorDiv.classList.remove('hidden');
        errorDiv.querySelector('p').textContent = error.message || 'Erreur de connexion';
    } finally {
        btn.disabled = false;
        btnText.textContent = 'Se connecter';
        spinner.classList.add('hidden');
    }
});

// Menu
document.getElementById('menu-btn').addEventListener('click', () => {
    document.getElementById('menu-drawer').classList.remove('hidden');
});

document.getElementById('close-menu').addEventListener('click', () => {
    document.getElementById('menu-drawer').classList.add('hidden');
});

document.getElementById('menu-overlay').addEventListener('click', () => {
    document.getElementById('menu-drawer').classList.add('hidden');
});

document.getElementById('nav-logout').addEventListener('click', () => {
    AuthManager.logout();
    document.getElementById('menu-drawer').classList.add('hidden');
});

document.getElementById('retry-inventory-btn').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const originalText = button.textContent;

    button.disabled = true;
    button.textContent = 'Vérification...';

    try {
        await UI.showInventoryHome();
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});

// Scanner
document.getElementById('start-scanner-btn').addEventListener('click', () => {
    ScannerManager.startQRScanner();
    document.getElementById('start-scanner-btn').style.display = 'none';
});

// Onglets scan / saisie manuelle emplacement
document.getElementById('tab-emp-scan').addEventListener('click', () => {
    UI.setEmplacementInputMode('scan');
});

document.getElementById('tab-emp-manual').addEventListener('click', () => {
    UI.setEmplacementInputMode('manual');
});

document.getElementById('manual-emp-submit').addEventListener('click', () => {
    UI.submitManualEmplacementCode();
});

document.getElementById('manual-emp-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        UI.submitManualEmplacementCode();
    }
});

// Fermer les caméras quand la page est masquée/fermée
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') {
        ScannerManager.stopScanner();
        BarcodeScannerManager.stopBarcodeScanner();
        return;
    }

    if (!AppState.token) return;

    const scannerViewVisible = !document.getElementById('view-scanner')?.classList.contains('hidden');
    const emplacementViewVisible = !document.getElementById('view-emplacement-biens')?.classList.contains('hidden');

    if (scannerViewVisible && AppState.emplacementInputMode !== 'manual') {
        ScannerManager.startQRScanner();
    } else if (emplacementViewVisible && AppState.barcodeInputMode !== 'manual') {
        BarcodeScannerManager.startBarcodeScanner();
    }
});

window.addEventListener('pagehide', () => {
    ScannerManager.stopScanner();
    BarcodeScannerManager.stopBarcodeScanner();
});

window.addEventListener('online', () => {
    updateConnectivityStatus().catch(() => {});
    OfflineStore.syncPending().catch(() => {});
});

window.addEventListener('offline', () => {
    updateConnectivityStatus().catch(() => {});
});

// Terminer scan emplacement : résumé avant validation, car les biens non
// scannés seront déclarés absents et seul un admin peut rouvrir le site.
const modalTerminer = document.getElementById('modal-terminer');

function fermerModalTerminer() {
    modalTerminer.classList.add('hidden');
}

document.getElementById('btn-terminer-emplacement').addEventListener('click', () => {
    const scannes = AppState.biensScannés || [];
    const nonAttendus = scannes.filter(s => s.statut === 'non_attendu' || s.statut === 'deplace').length;
    const manquants = (AppState.biensAttendus || [])
        .filter(b => !AppState.biensScannesIndex.has(b.num_ordre))
        .length;
    const site = AppState.currentEmplacement;

    document.getElementById('modal-terminer-site').textContent = site
        ? [site.code, site.nom].filter(Boolean).join(' · ')
        : '';
    document.getElementById('modal-terminer-scannes').textContent = scannes.length - nonAttendus;
    document.getElementById('modal-terminer-non-attendus').textContent = nonAttendus;
    document.getElementById('modal-terminer-manquants').textContent = manquants;

    const alerte = document.getElementById('modal-terminer-alerte');
    if (manquants > 0) {
        alerte.textContent = manquants === 1
            ? '1 bien non scanné sera déclaré absent.'
            : `${manquants} biens non scannés seront déclarés absents.`;
        alerte.classList.remove('hidden');
        HapticFeedback.warning();
    } else {
        alerte.classList.add('hidden');
    }

    modalTerminer.classList.remove('hidden');
    document.getElementById('modal-terminer-annuler').focus();
});

document.getElementById('modal-terminer-annuler').addEventListener('click', fermerModalTerminer);
document.getElementById('modal-terminer-overlay').addEventListener('click', fermerModalTerminer);
document.getElementById('modal-terminer-confirmer').addEventListener('click', () => {
    fermerModalTerminer();
    UI.showResultatsView();
});

// Nouveau scan
document.getElementById('btn-nouveau-scan').addEventListener('click', () => {
    AppState.currentEmplacement = null;
    AppState.biensAttendus = [];
    AppState.biensScannés = [];
    AppState.barcodeModalOpen = false;
    AppState.barcodeProcessing = false;
    AppState.barcodeInputMode = 'scan';
    AppState.emplacementInputMode = 'scan';
    rebuildBiensIndexes();
    UI.showView('scanner');
    UI.rafraichirMesSites();
});

// Mode scan rapide (mémorisé sur l'appareil)
const toggleScanRapide = document.getElementById('toggle-scan-rapide');
toggleScanRapide.checked = AppState.scanRapide;
toggleScanRapide.addEventListener('change', () => {
    AppState.scanRapide = toggleScanRapide.checked;
    try {
        localStorage.setItem('immos-scan-rapide', AppState.scanRapide ? '1' : '0');
    } catch (_) {}
});

// Onglets scan / saisie manuelle des biens
document.getElementById('tab-bien-scan').addEventListener('click', () => {
    UI.setBienInputMode('scan');
});

document.getElementById('tab-bien-manual').addEventListener('click', () => {
    UI.setBienInputMode('manual');
});

document.getElementById('manual-num-ordre-submit').addEventListener('click', () => {
    UI.submitManualNumOrdre();
});

document.getElementById('manual-num-ordre-input').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        UI.submitManualNumOrdre();
    }
});

// Modal État du bien
let modalEtatSelectionne = null;
let modalPhotoBase64 = null;
let modalPhotoProcessingId = 0;

const PHOTO_MAX_DIMENSION = 1600;
const PHOTO_MAX_BASE64_LENGTH = 6_800_000;

async function loadPhotoSource(file) {
    if ('createImageBitmap' in window) {
        try {
            const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });

            return {
                image: bitmap,
                width: bitmap.width,
                height: bitmap.height,
                cleanup: () => bitmap.close(),
            };
        } catch (error) {
            console.warn('[Photo] createImageBitmap indisponible pour ce fichier, utilisation du fallback.', error);
        }
    }

    return new Promise((resolve, reject) => {
        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => resolve({
            image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            cleanup: () => URL.revokeObjectURL(objectUrl),
        });
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Cette image ne peut pas être lue.'));
        };
        image.src = objectUrl;
    });
}

async function prepareScanPhoto(file) {
    if (!file.type.startsWith('image/')) {
        throw new Error('Veuillez sélectionner une image.');
    }

    const source = await loadPhotoSource(file);

    try {
        if (!source.width || !source.height) {
            throw new Error('Les dimensions de la photo sont invalides.');
        }

        const scale = Math.min(1, PHOTO_MAX_DIMENSION / Math.max(source.width, source.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(source.width * scale));
        canvas.height = Math.max(1, Math.round(source.height * scale));

        const context = canvas.getContext('2d');
        if (!context) {
            throw new Error('La photo ne peut pas être traitée sur cet appareil.');
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(source.image, 0, 0, canvas.width, canvas.height);

        for (const quality of [0.82, 0.72, 0.62, 0.52]) {
            const dataUrl = canvas.toDataURL('image/jpeg', quality);
            if (dataUrl.length <= PHOTO_MAX_BASE64_LENGTH) {
                return dataUrl;
            }
        }

        throw new Error('La photo reste trop volumineuse après compression.');
    } finally {
        source.cleanup();
    }
}

document.getElementById('modal-etat-buttons').addEventListener('click', (e) => {
    const btn = e.target.closest('.modal-etat-btn');
    if (!btn) return;
    
    document.querySelectorAll('.modal-etat-btn').forEach(b => b.classList.remove('border-indigo-600', 'bg-indigo-50'));
    btn.classList.add('border-indigo-600', 'bg-indigo-50');
    modalEtatSelectionne = btn.dataset.etatId;
    const requirePhoto = btn.dataset.requirePhoto === '1';
    
    const photoSection = document.getElementById('modal-etat-photo-section');
    const confirmBtn = document.getElementById('modal-etat-confirmer');
    
    if (requirePhoto) {
        photoSection.classList.remove('hidden');
        confirmBtn.disabled = !modalPhotoBase64;
    } else {
        modalPhotoProcessingId++;
        photoSection.classList.add('hidden');
        modalPhotoBase64 = null;
        document.getElementById('modal-etat-photo-input').value = '';
        document.getElementById('modal-etat-photo-preview').classList.add('hidden');
        document.getElementById('modal-etat-photo-img').removeAttribute('src');
        confirmBtn.textContent = 'Confirmer';
        confirmBtn.disabled = false;
    }
});

document.getElementById('modal-etat-photo-input').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const processingId = ++modalPhotoProcessingId;
    const confirmBtn = document.getElementById('modal-etat-confirmer');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Traitement...';

    try {
        const preparedPhoto = await prepareScanPhoto(file);
        if (processingId !== modalPhotoProcessingId) return;

        modalPhotoBase64 = preparedPhoto;
        document.getElementById('modal-etat-photo-img').src = modalPhotoBase64;
        document.getElementById('modal-etat-photo-preview').classList.remove('hidden');
        confirmBtn.disabled = false;
    } catch (error) {
        if (processingId !== modalPhotoProcessingId) return;

        modalPhotoBase64 = null;
        e.target.value = '';
        document.getElementById('modal-etat-photo-img').removeAttribute('src');
        document.getElementById('modal-etat-photo-preview').classList.add('hidden');
        UI.showToast(`${error.message}`, 'error');
    } finally {
        if (processingId === modalPhotoProcessingId) {
            confirmBtn.textContent = 'Confirmer';
        }
    }
});

document.getElementById('modal-etat-photo-retake').addEventListener('click', () => {
    modalPhotoProcessingId++;
    modalPhotoBase64 = null;
    document.getElementById('modal-etat-photo-input').value = '';
    document.getElementById('modal-etat-photo-preview').classList.add('hidden');
    document.getElementById('modal-etat-photo-img').removeAttribute('src');
    const btn = document.querySelector('.modal-etat-btn.border-indigo-600');
    const requirePhoto = btn && btn.dataset.requirePhoto === '1';
    const confirmBtn = document.getElementById('modal-etat-confirmer');
    confirmBtn.textContent = 'Confirmer';
    confirmBtn.disabled = requirePhoto;
});

document.getElementById('modal-etat-annuler').addEventListener('click', () => {
    modalPhotoProcessingId++;
    document.getElementById('modal-etat-confirmer').textContent = 'Confirmer';
    UI.hideModalEtatBien();
    modalEtatSelectionne = null;
    modalPhotoBase64 = null;
});

document.getElementById('modal-etat-overlay').addEventListener('click', () => {
    modalPhotoProcessingId++;
    document.getElementById('modal-etat-confirmer').textContent = 'Confirmer';
    UI.hideModalEtatBien();
    modalEtatSelectionne = null;
    modalPhotoBase64 = null;
});

document.getElementById('modal-etat-confirmer').addEventListener('click', () => {
    if (!modalEtatSelectionne) return;
    const btn = document.querySelector('.modal-etat-btn.border-indigo-600');
    const requirePhoto = btn && btn.dataset.requirePhoto === '1';
    if (requirePhoto && !modalPhotoBase64) {
        UI.showToast('Veuillez prendre une photo du bien défectueux', 'warning');
        return;
    }
    UI.confirmModalEtatBien(modalEtatSelectionne, modalPhotoBase64);
    modalPhotoProcessingId++;
    modalEtatSelectionne = null;
    modalPhotoBase64 = null;
});

// ===========================================
// INIT
// ===========================================

document.addEventListener('DOMContentLoaded', async () => {
    console.log('[App v2] Init...');

    await updateConnectivityStatus();

    if (!await AuthManager.checkAuth()) {
        UI.showView('login');
    }
});

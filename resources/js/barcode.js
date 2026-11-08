import JsBarcode from 'jsbarcode';

window.JsBarcode = JsBarcode;

/**
 * Génère un code-barres Code 128 avec jsbarcode
 * @param {string} codeValue - La valeur du code à encoder
 * @param {HTMLElement|string} element - L'élément SVG ou son sélecteur
 * @param {object} options - Options pour jsbarcode
 * @returns {string} Le SVG généré
 */
export function generateBarcode(codeValue, element, options = {}) {
    const defaultOptions = {
        format: "CODE128",
        width: 1,
        height: 40,
        displayValue: false, // Ne pas afficher le texte sous le code-barres
        background: "#ffffff",
        lineColor: "#000000",
        margin: 0,
        ...options
    };

    try {
        JsBarcode(element, codeValue, defaultOptions);
        
        // Récupérer le SVG généré
        const svgElement = typeof element === 'string' 
            ? document.querySelector(element) 
            : element;
        
        if (svgElement && svgElement.tagName === 'svg') {
            return svgElement.outerHTML;
        }
        
        return null;
    } catch (error) {
        console.error('Erreur lors de la génération du code-barres:', error);
        throw error;
    }
}

/**
 * Génère un code-barres et retourne le SVG en string
 * @param {string} codeValue - La valeur du code à encoder
 * @param {object} options - Options pour jsbarcode
 * @returns {string} Le SVG généré
 */
export function generateBarcodeSVG(codeValue, options = {}) {
    // Créer un élément SVG temporaire
    const tempSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    tempSvg.setAttribute('id', 'temp-barcode-svg');
    tempSvg.style.position = 'absolute';
    tempSvg.style.left = '-9999px';
    document.body.appendChild(tempSvg);
    
    try {
        generateBarcode(codeValue, tempSvg, options);
        const svgString = tempSvg.outerHTML;
        document.body.removeChild(tempSvg);
        return svgString;
    } catch (error) {
        document.body.removeChild(tempSvg);
        throw error;
    }
}

// Exporter jsbarcode pour utilisation directe si nécessaire
export { JsBarcode };

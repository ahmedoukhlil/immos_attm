# Immos GIMTEL

Application Laravel de gestion des immobilisations : référentiel des biens,
étiquetage, transferts, amortissements, inventaires physiques et scanner PWA.

## Prérequis

- PHP 8.2 ou supérieur avec `dom`, `fileinfo`, `gd`, `pdo`, `xml` et `zip`
- Composer 2
- Node.js 22 et npm
- MySQL/MariaDB en production, ou SQLite pour le développement et les tests

## Installation locale

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Renseigner avant le premier seeding les variables
`ADMIN_INITIAL_USERNAME` et `ADMIN_INITIAL_PASSWORD` dans `.env`.

Pour lancer l'environnement de développement :

```bash
composer run dev
```

## Vérifications

```bash
composer validate --no-check-publish
php artisan test
npm run build
```

La reconstruction d'une base vierge peut être vérifiée avec :

```bash
php artisan migrate:fresh --force
```

Cette commande efface la base ciblée. Elle ne doit être exécutée que sur une
base de développement ou de test.

## Déploiement

Avant chaque mise en production :

1. sauvegarder la base de données et le dossier `storage` ;
2. définir `APP_ENV=production`, `APP_DEBUG=false` et une URL HTTPS ;
3. installer les dépendances avec `composer install --no-dev --optimize-autoloader` ;
4. construire les assets avec `npm ci && npm run build` ;
5. exécuter `php artisan migrate --force` ;
6. exécuter `php artisan optimize` et démarrer le worker de file d'attente ;
7. vérifier la connexion, un scan PWA, un export et une génération d'étiquette.

Les rapports de 500 biens ou plus sont générés par la file `exports`. En
production, maintenir un worker et le planificateur Laravel actifs :

```bash
php artisan queue:work --queue=exports,default --tries=2 --timeout=600
php artisan schedule:work
```

Le seuil et la durée de conservation sont configurables avec
`INVENTORY_ASYNC_EXPORT_THRESHOLD` et `INVENTORY_EXPORT_RETENTION_DAYS`.

Les contrôles automatiques sont définis dans
`.github/workflows/quality.yml`.

## Documentation métier

- `TDR_Application_Immos_Gimtel.md` : manuel utilisateur
- `FONCTIONNALITES_Immos_GIMTEL.md` : modules et apports métier
- `Calcul_Amortissements_CGI_Mauritanie_2024.md` : règles d'amortissement

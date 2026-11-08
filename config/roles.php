<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Autorisations fixes des deux rôles métier
    |--------------------------------------------------------------------------
    |
    | Les permissions ne sont pas personnalisables utilisateur par utilisateur :
    | attribuer un rôle applique immédiatement l'ensemble correspondant.
    |
    */
    'abilities' => [
        'dashboard.view' => ['admin', 'agent'],
        'profile.update_self' => ['admin', 'agent'],
        'immobilisations.view' => ['admin', 'agent'],
        'immobilisations.manage' => ['admin'],
        'localisations.view' => ['admin', 'agent'],
        'referentiel.manage' => ['admin'],
        'inventaires.view' => ['admin', 'agent'],
        'inventaires.manage' => ['admin'],
        'inventaires.export_assigned' => ['admin', 'agent'],
        'pwa.access' => ['admin', 'agent'],
        'pwa.scan_assigned' => ['admin', 'agent'],
        'administration.manage' => ['admin'],
    ],

    'summaries' => [
        'admin' => 'Accès complet',
        'agent' => 'Consultation et inventaires assignés',
    ],

    'matrix' => [
        [
            'module' => 'Tableau de bord',
            'admin' => 'Consulter toutes les données et statistiques',
            'agent' => 'Consulter les données liées à ses inventaires',
        ],
        [
            'module' => 'Immobilisations',
            'admin' => 'Consulter, importer, créer, modifier, transférer, archiver et exporter',
            'agent' => 'Consulter uniquement',
        ],
        [
            'module' => 'Référentiel',
            'admin' => 'Gérer les localisations, sites, catégories, désignations, QR codes et étiquettes',
            'agent' => 'Consulter les localisations, sans modification',
        ],
        [
            'module' => 'Inventaires',
            'admin' => 'Créer, assigner, démarrer, suivre, terminer, clôturer, rouvrir et exporter',
            'agent' => 'Consulter et exporter ses inventaires assignés',
        ],
        [
            'module' => 'Inventaire physique PWA',
            'admin' => 'Scanner et superviser tous les sites',
            'agent' => 'Scanner et terminer uniquement les sites qui lui sont assignés',
        ],
        [
            'module' => 'Utilisateurs et rôles',
            'admin' => 'Créer, modifier, supprimer et attribuer les rôles',
            'agent' => null,
        ],
        [
            'module' => 'Profil personnel',
            'admin' => 'Modifier son propre mot de passe',
            'agent' => 'Modifier son propre mot de passe',
        ],
    ],
];

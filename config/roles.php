<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Autorisations fixes des trois rôles métier
    |--------------------------------------------------------------------------
    |
    | Les permissions ne sont pas personnalisables utilisateur par utilisateur :
    | attribuer un rôle applique immédiatement l'ensemble correspondant.
    |
    */
    'abilities' => [
        'dashboard.view' => ['admin', 'gestionnaire', 'agent'],
        'profile.update_self' => ['admin', 'gestionnaire', 'agent'],
        'immobilisations.view' => ['admin', 'gestionnaire', 'agent'],
        'immobilisations.manage' => ['admin', 'gestionnaire'],
        'localisations.view' => ['admin', 'gestionnaire', 'agent'],
        'referentiel.manage' => ['admin', 'gestionnaire'],
        'inventaires.view' => ['admin', 'gestionnaire', 'agent'],
        'inventaires.manage' => ['admin', 'gestionnaire'],
        'inventaires.export_assigned' => ['admin', 'gestionnaire', 'agent'],
        'pwa.access' => ['admin', 'gestionnaire', 'agent'],
        'pwa.scan_assigned' => ['admin', 'gestionnaire', 'agent'],
        'operations.manage' => ['admin', 'gestionnaire'],
        'administration.manage' => ['admin'],
    ],

    'summaries' => [
        'admin' => 'Accès complet',
        'gestionnaire' => 'Accès complet hors gestion des utilisateurs',
        'agent' => 'Consultation et inventaires assignés',
    ],

    'matrix' => [
        [
            'module' => 'Tableau de bord',
            'admin' => 'Consulter toutes les données et statistiques',
            'gestionnaire' => 'Consulter toutes les données et statistiques',
            'agent' => 'Consulter les données liées à ses inventaires',
        ],
        [
            'module' => 'Immobilisations',
            'admin' => 'Consulter, importer, créer, modifier, transférer, archiver et exporter',
            'gestionnaire' => 'Consulter, importer, créer, modifier, transférer, archiver et exporter',
            'agent' => 'Consulter uniquement',
        ],
        [
            'module' => 'Référentiel',
            'admin' => 'Gérer les localisations, sites, catégories, désignations, QR codes et étiquettes',
            'gestionnaire' => 'Gérer les localisations, sites, catégories, désignations, QR codes et étiquettes',
            'agent' => 'Consulter les localisations, sans modification',
        ],
        [
            'module' => 'Inventaires',
            'admin' => 'Créer, assigner, démarrer, suivre, terminer, clôturer, rouvrir et exporter',
            'gestionnaire' => 'Créer, assigner, démarrer, suivre, terminer, clôturer, rouvrir et exporter',
            'agent' => 'Consulter et exporter ses inventaires assignés',
        ],
        [
            'module' => 'Inventaire physique PWA',
            'admin' => 'Scanner et superviser tous les sites',
            'gestionnaire' => 'Scanner et superviser tous les sites',
            'agent' => 'Scanner et terminer uniquement les sites qui lui sont assignés',
        ],
        [
            'module' => 'Utilisateurs et rôles',
            'admin' => 'Créer, modifier, supprimer et attribuer les rôles',
            'gestionnaire' => null,
            'agent' => null,
        ],
        [
            'module' => 'Profil personnel',
            'admin' => 'Modifier son propre mot de passe',
            'gestionnaire' => 'Modifier son propre mot de passe',
            'agent' => 'Modifier son propre mot de passe',
        ],
    ],
];

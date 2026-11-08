<?php

return [
    'barryvdh/laravel-dompdf' => [
        'aliases' => [
            'PDF' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
            'Pdf' => 'Barryvdh\\DomPDF\\Facade\\Pdf',
        ],
        'providers' => [
            0 => 'Barryvdh\\DomPDF\\ServiceProvider',
        ],
    ],
    'laravel/pail' => [
        'providers' => [
            0 => 'Laravel\\Pail\\PailServiceProvider',
        ],
    ],
    'laravel/sail' => [
        'providers' => [
            0 => 'Laravel\\Sail\\SailServiceProvider',
        ],
    ],
    'laravel/sanctum' => [
        'providers' => [
            0 => 'Laravel\\Sanctum\\SanctumServiceProvider',
        ],
    ],
    'laravel/tinker' => [
        'providers' => [
            0 => 'Laravel\\Tinker\\TinkerServiceProvider',
        ],
    ],
    'livewire/livewire' => [
        'aliases' => [
            'Livewire' => 'Livewire\\Livewire',
        ],
        'providers' => [
            0 => 'Livewire\\LivewireServiceProvider',
        ],
    ],
    'maatwebsite/excel' => [
        'aliases' => [
            'Excel' => 'Maatwebsite\\Excel\\Facades\\Excel',
        ],
        'providers' => [
            0 => 'Maatwebsite\\Excel\\ExcelServiceProvider',
        ],
    ],
    'nesbot/carbon' => [
        'providers' => [
            0 => 'Carbon\\Laravel\\ServiceProvider',
        ],
    ],
    'nunomaduro/collision' => [
        'providers' => [
            0 => 'NunoMaduro\\Collision\\Adapters\\Laravel\\CollisionServiceProvider',
        ],
    ],
    'nunomaduro/termwind' => [
        'providers' => [
            0 => 'Termwind\\Laravel\\TermwindServiceProvider',
        ],
    ],
    'simplesoftwareio/simple-qrcode' => [
        'aliases' => [
            'QrCode' => 'SimpleSoftwareIO\\QrCode\\Facades\\QrCode',
        ],
        'providers' => [
            0 => 'SimpleSoftwareIO\\QrCode\\QrCodeServiceProvider',
        ],
    ],
];

<?php

use App\Models\InventaireExportRequest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventaires:prune-exports', function (): void {
    $deleted = 0;

    InventaireExportRequest::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->chunkById(100, function ($exports) use (&$deleted): void {
            foreach ($exports as $export) {
                if ($export->path) {
                    Storage::disk($export->disk)->delete($export->path);
                }

                $export->delete();
                $deleted++;
            }
        });

    $this->info("{$deleted} export(s) expiré(s) supprimé(s).");
})->purpose('Supprime les exports d’inventaire arrivés à expiration');

Schedule::command('inventaires:prune-exports')->dailyAt('02:00');

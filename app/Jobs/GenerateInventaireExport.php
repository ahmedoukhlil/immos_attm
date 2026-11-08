<?php

namespace App\Jobs;

use App\Models\InventaireExportRequest;
use App\Services\RapportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateInventaireExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public InventaireExportRequest $exportRequest)
    {
        $this->onQueue('exports');
    }

    public function handle(RapportService $rapportService): void
    {
        $request = $this->exportRequest->fresh(['inventaire']);

        if (! $request || ! $request->inventaire || $request->status === 'ready') {
            return;
        }

        $request->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        $path = match ($request->format) {
            'pdf' => $rapportService->genererRapportPDF($request->inventaire),
            'xlsx' => $rapportService->genererRapportExcel($request->inventaire),
            default => throw new \InvalidArgumentException('Format d’export non pris en charge.'),
        };

        $request->update([
            'status' => 'ready',
            'disk' => 'local',
            'path' => $path,
            'filename' => basename($path),
            'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
            'finished_at' => now(),
            'expires_at' => $request->automatic
                ? null
                : now()->addDays(max(1, (int) config('inventory.export_retention_days', 7))),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->exportRequest->update([
            'status' => 'failed',
            'error_message' => mb_substr($exception?->getMessage() ?? 'Échec de la génération.', 0, 1000),
        ]);
    }
}

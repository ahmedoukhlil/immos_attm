<?php

namespace App\Http\Controllers;

use App\Exceptions\InventaireWorkflowException;
use App\Exports\InventaireExport;
use App\Jobs\GenerateInventaireExport;
use App\Models\Inventaire;
use App\Models\InventaireExportRequest;
use App\Services\InventaireService;
use App\Services\RapportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventaireController extends Controller
{
    /**
     * Clôture un inventaire
     *
     * @return RedirectResponse
     */
    public function cloturer(Inventaire $inventaire)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if ($inventaire->statut === 'cloture') {
            return redirect()->back()->with('warning', 'Cet inventaire est déjà clôturé');
        }

        try {
            app(InventaireService::class)->cloturerInventaire($inventaire, auth()->user());

            return redirect()->route('inventaires.show', $inventaire)
                ->with('success', 'Inventaire clôturé avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', InventaireWorkflowException::messagePour($e, 'Erreur lors de la clôture.'));
        }
    }

    /**
     * Exporte un inventaire en format PDF
     *
     * @return BinaryFileResponse|RedirectResponse
     */
    public function exportPDF(Inventaire $inventaire)
    {
        $this->authorizeRapport($inventaire);

        if ($this->requiresQueuedExport($inventaire)) {
            return $this->queueExport($inventaire, 'pdf');
        }

        try {
            $service = app(RapportService::class);

            return $service->streamRapportPDF($inventaire);
        } catch (\Exception $e) {
            report($e);

            return redirect()->back()->with('error', 'Erreur lors de la génération du PDF.');
        }
    }

    /**
     * Exporte un inventaire en format Excel
     *
     * @return BinaryFileResponse|RedirectResponse
     */
    public function exportExcel(Inventaire $inventaire)
    {
        $this->authorizeRapport($inventaire);

        if ($this->requiresQueuedExport($inventaire)) {
            return $this->queueExport($inventaire, 'xlsx');
        }

        return Excel::download(
            new InventaireExport($inventaire),
            'rapport_inventaire_'.$inventaire->annee.'_'.now()->format('Y-m-d_His').'.xlsx',
        );
    }

    public function downloadExport(InventaireExportRequest $exportRequest): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user && $exportRequest->inventaire->isAccessibleTo($user), 403);
        abort_unless($user->isAdmin() || $exportRequest->requested_by === $user->idUser, 403);
        abort_unless($exportRequest->status === 'ready' && $exportRequest->path, 409);
        abort_if($exportRequest->expires_at?->isPast(), 410, 'Cet export a expiré.');
        abort_unless(Storage::disk($exportRequest->disk)->exists($exportRequest->path), 404);

        return Storage::disk($exportRequest->disk)->download(
            $exportRequest->path,
            $exportRequest->filename,
        );
    }

    private function requiresQueuedExport(Inventaire $inventaire): bool
    {
        $threshold = max(1, (int) config('inventory.async_export_threshold', 500));
        $assetCount = max(
            $inventaire->biensAttendus()->count(),
            (int) $inventaire->inventaireLocalisations()->sum('nombre_biens_attendus'),
        );

        return $assetCount >= $threshold;
    }

    private function queueExport(Inventaire $inventaire, string $format): RedirectResponse
    {
        $exportRequest = InventaireExportRequest::query()
            ->where('inventaire_id', $inventaire->id)
            ->where('requested_by', auth()->id())
            ->where('format', $format)
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id')
            ->first();

        if (! $exportRequest) {
            $exportRequest = InventaireExportRequest::create([
                'inventaire_id' => $inventaire->id,
                'requested_by' => auth()->id(),
                'format' => $format,
                'status' => 'pending',
                'disk' => 'local',
            ]);

            GenerateInventaireExport::dispatch($exportRequest);
        }

        return redirect()->route('inventaires.rapport', $inventaire)
            ->with('success', 'L’export volumineux a été placé dans la file de traitement.');
    }

    private function authorizeRapport(Inventaire $inventaire): void
    {
        abort_unless(
            auth()->user() && $inventaire->isAccessibleTo(auth()->user()),
            403,
        );
        abort_unless(in_array($inventaire->statut, ['termine', 'cloture'], true), 409);
    }
}

<?php

namespace App\Services;

use App\Models\Code;
use App\Models\Immobilisation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeService
{
    /**
     * Génère un code-barres Code 128 pour une immobilisation
     *
     * Le code-barres contient uniquement l'identifiant immuable du bien.
     *
     * @param  Immobilisation  $immobilisation  L'immobilisation pour laquelle générer le code-barres
     * @return string Le code-barres en base64 (format PNG ou SVG)
     *
     * @throws \Exception Si la génération échoue
     */
    public function generateForImmobilisation(Immobilisation $immobilisation): string
    {
        try {
            $codeValue = $immobilisation->scan_code;

            if (empty($codeValue)) {
                throw new \Exception("Impossible de générer le code d'immobilisation. Vérifiez que toutes les relations sont définies.");
            }

            // Générer le code-barres Code 128 en HTML (meilleure compatibilité avec DomPDF)
            // Le HTML génère le code-barres avec des divs et CSS, ce qui fonctionne mieux dans les PDF
            // Paramètres : widthFactor=1 (barres plus fines), height=40 (hauteur adaptée)
            $generator = new BarcodeGeneratorHTML;
            $barcodeHTML = $generator->getBarcode($codeValue, $generator::TYPE_CODE_128, 1, 40);

            // Logger la génération réussie
            Log::info("Code-barres Code 128 généré pour l'immobilisation", [
                'id' => $immobilisation->id,
                'code' => $codeValue,
            ]);

            // Sauvegarder ou mettre à jour dans la table codes (stockage du HTML directement)
            $codeModel = Code::updateOrCreate(
                ['immobilisation_id' => $immobilisation->id],
                ['barcode' => $barcodeHTML] // Stocker le HTML directement dans la base de données
            );

            return $barcodeHTML;
        } catch (\Exception $e) {
            // Logger l'erreur
            Log::error("Erreur lors de la génération du code-barres pour l'immobilisation", [
                'id' => $immobilisation->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Génère un code-barres Code 128 en PNG pour une immobilisation
     *
     * @param  Immobilisation  $immobilisation  L'immobilisation pour laquelle générer le code-barres
     * @return string Le code-barres en base64 (format PNG)
     *
     * @throws \Exception Si la génération échoue
     */
    public function generatePNGForImmobilisation(Immobilisation $immobilisation): string
    {
        try {
            $codeValue = $immobilisation->scan_code;

            if (empty($codeValue)) {
                throw new \Exception("Impossible de générer le code d'immobilisation.");
            }

            // Générer le code-barres Code 128 en PNG
            $generator = new BarcodeGeneratorPNG;
            $barcodePNG = $generator->getBarcode($codeValue, $generator::TYPE_CODE_128, 2, 50);

            // Convertir en base64 pour stockage
            $barcodeBase64 = base64_encode($barcodePNG);

            // Sauvegarder dans la table codes
            $codeModel = Code::updateOrCreate(
                ['immobilisation_id' => $immobilisation->id],
                ['barcode' => $barcodeBase64]
            );

            return $barcodeBase64;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du code-barres PNG', [
                'id' => $immobilisation->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Génère un code-barres Code 128 en HTML pour affichage direct
     *
     * @param  Immobilisation  $immobilisation  L'immobilisation pour laquelle générer le code-barres
     * @return string Le code-barres en HTML
     *
     * @throws \Exception Si la génération échoue
     */
    public function generateHTMLForImmobilisation(Immobilisation $immobilisation): string
    {
        try {
            $codeValue = $immobilisation->scan_code;

            if (empty($codeValue)) {
                throw new \Exception("Impossible de générer le code d'immobilisation.");
            }

            // Générer le code-barres Code 128 en HTML
            $generator = new BarcodeGeneratorHTML;
            $barcode = $generator->getBarcode($codeValue, $generator::TYPE_CODE_128, 2, 50);

            return $barcode;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du code-barres HTML', [
                'id' => $immobilisation->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Génère plusieurs codes-barres en batch
     *
     * @param  Collection  $immobilisations  Collection d'immobilisations
     * @return array Tableau des codes-barres générés avec succès
     */
    public function generateBatch(Collection $immobilisations): array
    {
        $generated = [];
        $errors = [];

        Log::info('Début de la génération en batch de codes-barres', [
            'count' => $immobilisations->count(),
        ]);

        foreach ($immobilisations as $immobilisation) {
            try {
                $barcode = $this->generateForImmobilisation($immobilisation);
                $generated[] = [
                    'id' => $immobilisation->id,
                    'code' => $immobilisation->scan_code,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $immobilisation->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Erreur lors de la génération du code-barres en batch', [
                    'id' => $immobilisation->id ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Fin de la génération en batch de codes-barres', [
            'success' => count($generated),
            'errors' => count($errors),
        ]);

        return $generated;
    }
}

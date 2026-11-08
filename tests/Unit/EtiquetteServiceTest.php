<?php

namespace Tests\Unit;

use App\Models\Categorie;
use App\Models\Designation;
use App\Models\DesignationComposant;
use App\Models\Immobilisation;
use App\Services\EtiquetteService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class EtiquetteServiceTest extends TestCase
{
    public function test_un_materiel_simple_genere_une_etiquette_principale(): void
    {
        $bien = $this->bienAvecComposants(false, []);

        $etiquettes = (new EtiquetteService)->etiquettesPourBien($bien);

        $this->assertCount(1, $etiquettes);
        $this->assertSame('GS154', $etiquettes->first()['code']);
        $this->assertSame('216/ORD/003/2026', $etiquettes->first()['code_affiche']);
        // Le QR porte le code d'inventaire lisible et l'identifiant immuable qui fait foi au scan.
        $this->assertSame('216/ORD/003/2026 | GS154', $etiquettes->first()['contenu_qr']);
        $this->assertNull($etiquettes->first()['composant']);
    }

    public function test_un_materiel_compose_genere_une_etiquette_par_composant_et_quantite(): void
    {
        $bien = $this->bienAvecComposants(true, [
            new DesignationComposant(['nom' => 'Unité centrale', 'code' => 'U', 'quantite' => 1]),
            new DesignationComposant(['nom' => 'Écran', 'code' => 'E', 'quantite' => 2]),
            new DesignationComposant(['nom' => 'Clavier', 'code' => 'C', 'quantite' => 1]),
        ]);

        $etiquettes = (new EtiquetteService)->etiquettesPourBien($bien);

        $this->assertSame([
            'GS154-U',
            'GS154-E-1',
            'GS154-E-2',
            'GS154-C',
        ], $etiquettes->pluck('code')->all());
        $this->assertSame('216/ORD/003/2026-E-2', $etiquettes[2]['code_affiche']);
        $this->assertSame('216/ORD/003/2026-E-2 | GS154-E-2', $etiquettes[2]['contenu_qr']);
    }

    public function test_le_format_physique_reste_celui_deja_utilise(): void
    {
        $this->assertSame(70.0, EtiquetteService::LARGEUR_MM);
        $this->assertSame(24.4, EtiquetteService::HAUTEUR_MM);
    }

    public function test_une_planche_a4_calcule_automatiquement_sa_grille(): void
    {
        $configuration = (new EtiquetteService)->configurationImpression([
            'page_format' => 'a4_portrait',
            'label_width' => 70,
            'label_height' => 30,
            'page_margin' => 10,
            'gap_x' => 2,
            'gap_y' => 2,
            'start_position' => 11,
        ]);

        $this->assertSame(2, $configuration['columns']);
        $this->assertSame(8, $configuration['rows']);
        $this->assertSame(16, $configuration['labels_per_page']);
        $this->assertSame(11, $configuration['start_position']);
    }

    public function test_le_format_individuel_adapte_la_page_a_letiquette(): void
    {
        $configuration = (new EtiquetteService)->configurationImpression([
            'page_format' => 'individuelle',
            'label_width' => 80,
            'label_height' => 35,
            'page_margin' => 10,
            'gap_x' => 4,
            'gap_y' => 4,
        ]);

        $this->assertSame(80.0, $configuration['page_width']);
        $this->assertSame(35.0, $configuration['page_height']);
        $this->assertSame(1, $configuration['labels_per_page']);
        $this->assertSame(0.0, $configuration['page_margin']);
    }

    public function test_une_grille_manuelle_de_sept_colonnes_sur_huit_lignes_produit_56_etiquettes(): void
    {
        $configuration = (new EtiquetteService)->configurationImpression([
            'page_format' => 'a4_portrait',
            'grid_mode' => 'manual',
            'columns' => 7,
            'rows' => 8,
            'page_margin' => 10,
            'gap_x' => 2,
            'gap_y' => 2,
        ]);

        $this->assertSame(7, $configuration['columns']);
        $this->assertSame(8, $configuration['rows']);
        $this->assertSame(56, $configuration['labels_per_page']);
        $this->assertEqualsWithDelta(25.43, $configuration['label_width'], 0.01);
        $this->assertEqualsWithDelta(32.88, $configuration['label_height'], 0.01);
        $this->assertTrue($configuration['compact_layout']);
        $this->assertFalse($configuration['is_horizontal']);
    }

    public function test_une_grille_de_quatre_colonnes_sur_quatorze_lignes_reste_horizontale(): void
    {
        $configuration = (new EtiquetteService)->configurationImpression([
            'page_format' => 'a4_portrait',
            'grid_mode' => 'manual',
            'columns' => 4,
            'rows' => 14,
            'page_margin' => 2,
            'gap_x' => 0,
            'gap_y' => 0,
            'qr_size' => 12,
        ]);

        $this->assertSame(56, $configuration['labels_per_page']);
        $this->assertEqualsWithDelta(51.5, $configuration['label_width'], 0.01);
        $this->assertEqualsWithDelta(20.93, $configuration['label_height'], 0.01);
        $this->assertSame(12.0, $configuration['qr_size']);
        $this->assertTrue($configuration['is_horizontal']);
        $this->assertTrue($configuration['short_layout']);
    }

    /** @param array<int, DesignationComposant> $composants */
    private function bienAvecComposants(bool $estCompose, array $composants): Immobilisation
    {
        $designation = new Designation([
            'designation' => 'Ordinateur de bureau',
            'code' => 'ORD',
            'est_compose' => $estCompose,
        ]);
        $designation->setRelation('composants', new Collection($composants));

        $categorie = new Categorie(['code' => 'INFO']);

        $bien = new Immobilisation;
        $bien->id = 154;
        $bien->code_propose = '216/ORD/003/2026';
        $bien->setRelation('designation', $designation);
        $bien->setRelation('categorie', $categorie);

        return $bien;
    }
}

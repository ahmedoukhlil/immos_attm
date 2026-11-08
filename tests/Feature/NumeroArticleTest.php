<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Designation;
use App\Models\Immobilisation;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLaxSchema;
use Tests\TestCase;

class NumeroArticleTest extends TestCase
{
    use CreatesLaxSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createLaxSchema();

        DB::table('designations')->insert([
            ['id' => 1, 'designation' => 'Ordinateur'],
            ['id' => 2, 'designation' => 'Imprimante'],
        ]);
    }

    public function test_each_designation_has_its_own_article_sequence(): void
    {
        $ordinateurs = collect(range(1, 3))->map(fn () => Immobilisation::create(['designation_id' => 1]));
        $imprimantes = collect(range(1, 2))->map(fn () => Immobilisation::create(['designation_id' => 2]));

        $this->assertSame([1, 2, 3], $ordinateurs->pluck('numero_article')->all());
        $this->assertSame([1, 2], $imprimantes->pluck('numero_article')->all());
        $this->assertSame('Ordinateur 3', $ordinateurs->last()->load('designation')->libelle_article);
        // Le numéro d'ordre reste l'identifiant global.
        $this->assertSame([1, 2, 3, 4, 5], Immobilisation::orderBy('id')->pluck('id')->all());
    }

    public function test_archived_article_numbers_are_never_reused(): void
    {
        Immobilisation::create(['designation_id' => 1]);
        $deuxieme = Immobilisation::create(['designation_id' => 1]);
        $deuxieme->delete();

        $this->assertSame(3, Immobilisation::create(['designation_id' => 1])->numero_article);
    }

    public function test_inventory_code_is_generated_from_imputation_designation_number_and_year(): void
    {
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Mobilier', 'code' => 'MMOB', 'imputation_comptable' => '21800000']);
        DB::table('designations')->where('id', 1)->update(['code' => 'BUR']);

        $premier = Immobilisation::create(['designation_id' => 1, 'categorie_id' => 1]);
        $ancien = Immobilisation::create(['designation_id' => 1, 'categorie_id' => 1, 'annee_acquisition' => 2015]);

        $this->assertSame('218/BUR/001/'.now()->year, $premier->code_propose);
        $this->assertSame('218/BUR/002/2015', $ancien->code_propose);

        $ancien->update(['annee_acquisition' => 2016]);
        $this->assertSame('218/BUR/002/2016', $ancien->fresh()->code_propose);
    }

    public function test_codes_follow_a_change_of_imputation_or_designation_code(): void
    {
        DB::table('categories')->insert(['id' => 1, 'categorie' => 'Mobilier', 'code' => 'MMOB', 'imputation_comptable' => '21800000']);
        DB::table('designations')->where('id', 1)->update(['code' => 'BUR']);
        $bien = Immobilisation::create(['designation_id' => 1, 'categorie_id' => 1, 'annee_acquisition' => 2026]);

        Categorie::findOrFail(1)->update(['imputation_comptable' => '21400000']);
        $this->assertSame('214/BUR/001/2026', $bien->fresh()->code_propose);

        Designation::findOrFail(1)->update(['code' => 'BRX']);
        $this->assertSame('214/BRX/001/2026', $bien->fresh()->code_propose);
    }

    public function test_changing_the_designation_takes_the_next_number_of_the_new_one(): void
    {
        Immobilisation::create(['designation_id' => 2]);
        $bien = Immobilisation::create(['designation_id' => 1]);

        $bien->update(['designation_id' => 2]);

        $this->assertSame(2, $bien->fresh()->numero_article);
    }
}

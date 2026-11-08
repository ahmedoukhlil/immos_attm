<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorie', function (Blueprint $table) {
            $table->integer('duree_amortissement')->nullable()->after('CodeCategorie');
            $table->decimal('taux_amortissement', 5, 2)->nullable()->after('duree_amortissement');
            $table->string('type_cgi')->nullable()->after('taux_amortissement');
        });

        $mapping = [
            '001' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '010' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '100' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '110' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '101' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '011' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '220' => ['duree' => 5,  'taux' => 20.00, 'type' => "Matériel d'exploitation"],
            '111' => ['duree' => 4,  'taux' => 25.00, 'type' => 'Matériel informatique'],
            '002' => ['duree' => 10, 'taux' => 10.00, 'type' => 'Mobilier'],
            '020' => ['duree' => 10, 'taux' => 10.00, 'type' => 'Mobilier'],
            '200' => ['duree' => 10, 'taux' => 10.00, 'type' => 'Mobilier'],
        ];

        foreach ($mapping as $code => $values) {
            DB::table('categorie')
                ->where('CodeCategorie', $code)
                ->update([
                    'duree_amortissement' => $values['duree'],
                    'taux_amortissement'  => $values['taux'],
                    'type_cgi'            => $values['type'],
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('categorie', function (Blueprint $table) {
            $table->dropColumn(['duree_amortissement', 'taux_amortissement', 'type_cgi']);
        });
    }
};

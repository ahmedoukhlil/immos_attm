<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE categorie MODIFY Categorie VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE categorie MODIFY CodeCategorie VARCHAR(255) NULL');
        DB::statement('ALTER TABLE categorie MODIFY type_cgi VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // Pas de retour arrière : la taille précédente n'est pas fiable selon l'environnement.
    }
};

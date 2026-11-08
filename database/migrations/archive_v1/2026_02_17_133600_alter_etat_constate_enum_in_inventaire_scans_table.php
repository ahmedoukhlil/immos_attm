<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Harmoniser etat_constate avec la table etat: Bon Etat (BE), Neuf (NF), Defectueux (DFCT)
     */
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Étape 1 : Élargir l'enum pour accepter les anciennes ET nouvelles valeurs
        DB::statement("ALTER TABLE inventaire_scans MODIFY COLUMN etat_constate ENUM('neuf', 'bon', 'moyen', 'mauvais', 'bon_etat', 'defectueux') NOT NULL DEFAULT 'bon'");

        // Étape 2 : Convertir les anciennes valeurs vers les nouvelles
        DB::statement("UPDATE inventaire_scans SET etat_constate = 'bon_etat' WHERE etat_constate IN ('bon', 'moyen')");
        DB::statement("UPDATE inventaire_scans SET etat_constate = 'defectueux' WHERE etat_constate = 'mauvais'");

        // Étape 3 : Restreindre l'enum aux nouvelles valeurs uniquement
        DB::statement("ALTER TABLE inventaire_scans MODIFY COLUMN etat_constate ENUM('bon_etat', 'neuf', 'defectueux') NOT NULL DEFAULT 'bon_etat'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Convertir les nouvelles valeurs vers les anciennes
        DB::statement("UPDATE inventaire_scans SET etat_constate = 'bon' WHERE etat_constate = 'bon_etat'");
        DB::statement("UPDATE inventaire_scans SET etat_constate = 'mauvais' WHERE etat_constate = 'defectueux'");

        // Remettre l'ancien enum
        DB::statement("ALTER TABLE inventaire_scans MODIFY COLUMN etat_constate ENUM('neuf', 'bon', 'moyen', 'mauvais') NOT NULL");
    }
};

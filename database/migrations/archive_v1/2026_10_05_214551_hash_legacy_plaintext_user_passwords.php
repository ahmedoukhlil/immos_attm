<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->select(['idUser', 'mdp'])
            ->orderBy('idUser')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $passwordInfo = password_get_info((string) $user->mdp);

                    if (($passwordInfo['algoName'] ?? 'unknown') !== 'unknown') {
                        continue;
                    }

                    DB::table('users')
                        ->where('idUser', $user->idUser)
                        ->update(['mdp' => Hash::make((string) $user->mdp)]);
                }
            }, 'idUser');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Les mots de passe hachés ne peuvent et ne doivent pas être restaurés en clair.
    }
};

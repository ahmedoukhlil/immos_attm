<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = (string) env('ADMIN_INITIAL_USERNAME', '');
        $password = (string) env('ADMIN_INITIAL_PASSWORD', '');

        if ($username === '' || mb_strlen($password) < 8) {
            throw new \RuntimeException('ADMIN_INITIAL_USERNAME et ADMIN_INITIAL_PASSWORD (8 caractères minimum) sont requis.');
        }

        $existing = User::where('users', $username)->first();

        if ($existing) {
            $this->command->warn("L'utilisateur {$username} existe déjà (idUser: {$existing->idUser})");

            return;
        }

        User::create([
            'users' => $username,
            'mdp' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->command->info("Utilisateur {$username} créé avec succès.");
    }
}

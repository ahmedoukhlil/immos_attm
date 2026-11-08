<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Les comptes existants (restaurés ou créés à la main) sont conservés.
        if (User::where('role', 'admin')->exists()) {
            $this->command?->info('Un administrateur existe déjà : création ignorée.');

            return;
        }

        $this->call(AdminSeeder::class);
    }
}

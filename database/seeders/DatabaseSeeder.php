<?php

namespace Database\Seeders;

use App\Domains\Accounts\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Login de desenvolvimento, com dados inventados. Em produção o dono é
     * criado com `php artisan owly:owner`.
     */
    public function run(): void
    {
        $organization = Organization::factory()->create(['name' => 'Empresa de Teste']);

        User::factory()->for($organization)->create([
            'name' => 'Dono de Teste',
            'email' => 'dono@owly.test',
            'password' => 'owly',
        ]);
    }
}

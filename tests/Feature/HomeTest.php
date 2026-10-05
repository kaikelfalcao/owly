<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get(route('home'))->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_quem_entrou_cai_no_painel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect('/dashboard');
    }

    public function test_nao_existe_cadastro_publico(): void
    {
        $this->get('/register')->assertNotFound();
    }
}

<?php

namespace Tests\Feature\Ai;

use App\Domains\Ai\Models\AiConnection;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeGemini;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/ia')->assertRedirect('/login');
        $this->get('/ia/conectar')->assertRedirect('/login');
        $this->post('/ia/conexoes')->assertRedirect('/login');
    }

    public function test_tela_de_ia_abre_vazia(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/ia')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('ai/index')->has('connections', 0));
    }

    public function test_assistente_mostra_gemini_disponivel_e_os_outros_em_breve(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/ia/conectar')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ai/connect')
                ->where('providers.0.id', 'gemini')
                ->where('providers.0.available', true)
                ->where('providers.1.available', false)
                ->where('providers.2.available', false));
    }

    public function test_testar_a_chave_devolve_os_modelos_sem_guardar_nada(): void
    {
        FakeGemini::ok();

        $this->actingAs(User::factory()->create())
            ->from('/ia/conectar')
            ->post('/ia/conectar/testar', ['provider' => 'gemini', 'api_key' => FakeGemini::KEY])
            ->assertRedirect('/ia/conectar')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('verified.suggested', 'gemini-2.5-flash')
            ->assertInertiaFlash('verified.models.0.id', 'gemini-2.5-flash');

        $this->assertSame(0, AiConnection::count());
    }

    public function test_chave_recusada_aparece_no_campo(): void
    {
        FakeGemini::invalidKey();

        $this->actingAs(User::factory()->create())
            ->post('/ia/conectar/testar', ['provider' => 'gemini', 'api_key' => 'chave-errada-0000000000000'])
            ->assertSessionHasErrors(['api_key' => 'O provedor recusou a chave. Confira se copiou a chave inteira e se ela está ativa.']);
    }

    public function test_provedor_sem_adaptador_e_recusado(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->post('/ia/conectar/testar', ['provider' => 'openai', 'api_key' => FakeGemini::KEY])
            ->assertSessionHasErrors(['provider' => 'Esse provedor ainda não está disponível.']);

        Http::assertNothingSent();
    }

    public function test_conectar_guarda_a_chave_cifrada_e_vira_a_padrao(): void
    {
        FakeGemini::ok();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/ia/conexoes', $this->payload())
            ->assertRedirect('/ia')
            ->assertSessionHasNoErrors();

        $connection = AiConnection::sole();
        $this->assertSame($user->organization_id, $connection->organization_id);
        $this->assertTrue($connection->is_default);
        $this->assertSame(FakeGemini::KEY, $connection->api_key);
        $this->assertSame('abcd', $connection->key_hint);
        $this->assertStringNotContainsString(FakeGemini::KEY, (string) DB::table('ai_connections')->value('api_key'));
        $this->assertDatabaseHas('audit_entries', ['action' => 'ai.connection_created']);
        $this->assertSame(['provider' => 'gemini'], AuditEntry::where('action', 'ai.connection_created')->sole()->meta);
    }

    public function test_a_chave_nunca_volta_inteira_para_a_tela(): void
    {
        FakeGemini::ok();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/ia/conexoes', $this->payload());

        $response = $this->actingAs($user)->get('/ia');

        $response->assertInertia(fn (AssertableInertia $page) => $page->where('connections.0.keyHint', '••••abcd'));
        $this->assertStringNotContainsString(FakeGemini::KEY, $response->getContent());
    }

    public function test_modelo_fora_da_lista_e_recusado(): void
    {
        FakeGemini::ok();

        $this->actingAs(User::factory()->create())
            ->post('/ia/conexoes', $this->payload(['model' => 'gemini-inventado']))
            ->assertSessionHasErrors(['model' => 'Escolha um dos modelos da lista.']);

        $this->assertSame(0, AiConnection::count());
    }

    public function test_segunda_conexao_nao_tira_a_padrao_e_pode_virar_padrao(): void
    {
        FakeGemini::ok();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/ia/conexoes', $this->payload());
        $this->actingAs($user)->post('/ia/conexoes', $this->payload(['label' => 'Gemini Pro', 'model' => 'gemini-2.5-pro']));

        [$first, $second] = AiConnection::orderBy('id')->get();
        $this->assertTrue($first->is_default);
        $this->assertFalse($second->is_default);

        $this->actingAs($user)->post("/ia/conexoes/{$second->id}/padrao")->assertRedirect();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_remover_a_padrao_passa_a_vez_para_outra(): void
    {
        FakeGemini::ok();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/ia/conexoes', $this->payload());
        $this->actingAs($user)->post('/ia/conexoes', $this->payload(['label' => 'Outra']));
        [$first, $second] = AiConnection::orderBy('id')->get();

        $this->actingAs($user)->delete("/ia/conexoes/{$first->id}")->assertRedirect();

        $this->assertModelMissing($first);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertDatabaseHas('audit_entries', ['action' => 'ai.connection_removed']);
    }

    public function test_outra_empresa_nao_mexe_na_conexao(): void
    {
        FakeGemini::ok();
        $this->actingAs(User::factory()->create())->post('/ia/conexoes', $this->payload());
        $connection = AiConnection::sole();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->delete("/ia/conexoes/{$connection->id}")->assertNotFound();
        $this->actingAs($intruder)->post("/ia/conexoes/{$connection->id}/padrao")->assertNotFound();
        $this->actingAs($intruder)->get('/ia')->assertInertia(fn (AssertableInertia $page) => $page->has('connections', 0));
        $this->assertModelExists($connection);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'provider' => 'gemini',
            'api_key' => FakeGemini::KEY,
            'model' => 'gemini-2.5-flash',
            'label' => 'Gemini',
            ...$overrides,
        ];
    }
}

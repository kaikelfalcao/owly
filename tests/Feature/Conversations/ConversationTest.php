<?php

namespace Tests\Feature\Conversations;

use App\Domains\Conversations\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WhatsAppZip;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/conversas')->assertRedirect('/login');
        $this->get('/conversas/1')->assertRedirect('/login');
    }

    public function test_lista_vazia_antes_de_importar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/conversas')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('conversations/index')
                ->where('conversations.total', 0)
                ->where('search', null));
    }

    public function test_lista_a_mais_recente_primeiro_com_a_ultima_mensagem(): void
    {
        $user = $this->withImport();

        $this->actingAs($user)
            ->get('/conversas')
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Uma linha por atendimento: o Cliente Teste voltou na sexta,
                // depois de dois dias úteis, e tem dois.
                ->where('conversations.total', 3)
                ->where('conversations.data.0.contact', 'Cliente Teste')
                ->where('conversations.data.0.phone', '+55 11 98888-7777')
                ->where('conversations.data.0.messagesCount', 2)
                ->where('conversations.data.0.lastMessage.who', 'Resposta automática')
                ->where('conversations.data.1.contact', '⭐ Maria Inventada')
                ->where('conversations.data.1.lastMessage.who', 'Equipe')
                ->where('conversations.data.1.lastMessage.text', 'Arquivo')
                ->where('conversations.data.2.contact', 'Cliente Teste')
                ->where('conversations.data.2.messagesCount', 3));
    }

    public function test_busca_por_nome_ou_telefone(): void
    {
        $user = $this->withImport();

        $this->actingAs($user)->get('/conversas?busca=maria')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('search', 'maria')
                ->where('conversations.total', 1)
                ->where('conversations.data.0.contact', '⭐ Maria Inventada'));

        $this->actingAs($user)->get('/conversas?busca='.urlencode('(11) 98888'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('conversations.total', 2)
                ->where('conversations.data.0.contact', 'Cliente Teste'));

        $this->actingAs($user)->get('/conversas?busca=ninguem')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('conversations.total', 0));
    }

    public function test_conversa_mostra_a_ficha_e_as_mensagens_em_ordem(): void
    {
        $user = $this->withImport();
        [$first, $second] = Conversation::whereHas('contact', fn ($q) => $q->where('phone', '5511988887777'))->orderBy('first_message_at')->get()->all();

        // A tela mostra só aquele atendimento.
        $this->actingAs($user)
            ->get("/conversas/{$first->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('conversations/show')
                ->where('conversation.contact', 'Cliente Teste')
                ->where('conversation.messagesCount', 3)
                ->where('conversation.fromContact', 2)
                ->where('conversation.sellers', [['name' => 'Ana', 'messages' => 1]])
                ->has('messages', 3)
                ->where('messages.0.author', 'contact')
                ->where('messages.1.seller', 'Ana')
                ->where('messages.2.event', 'missed_call'));

        $this->actingAs($user)
            ->get("/conversas/{$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('messages', 2)
                ->where('messages.0.author', 'contact')
                ->where('messages.1.author', 'bot'));
    }

    public function test_conversa_de_outra_empresa_nao_existe(): void
    {
        $this->withImport();
        $conversation = Conversation::first();

        $this->actingAs(User::factory()->create())
            ->get("/conversas/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_outra_empresa_nao_ve_as_conversas_na_lista(): void
    {
        $this->withImport();

        $this->actingAs(User::factory()->create())
            ->get('/conversas')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('conversations.total', 0));
    }

    private function withImport(): User
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/importar', [
            'file' => new UploadedFile(WhatsAppZip::sample()->path(), 'conversas.zip', 'application/zip', null, true),
        ])->assertSessionHasNoErrors();

        return $user;
    }
}

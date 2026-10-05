<?php

namespace Tests\Feature\Ai;

use App\Domains\Ai\Models\AiConnection;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeGemini;
use Tests\Support\WhatsAppZip;
use Tests\TestCase;

class AskAboutConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->actingAs($this->user)->post('/importar', [
            'file' => new UploadedFile(WhatsAppZip::sample()->path(), 'conversas.zip', 'application/zip', null, true),
        ]);
        $this->conversation = Conversation::whereHas('contact', fn ($q) => $q->where('phone', '5511988887777'))->sole();
    }

    public function test_sem_ia_conectada_a_conversa_mostra_o_convite(): void
    {
        $this->actingAs($this->user)
            ->get("/conversas/{$this->conversation->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('panels.ai.connection', null)
                ->has('panels.ai.questions', 0));
    }

    public function test_sem_ia_conectada_perguntar_e_recusado(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->post($this->url(), ['question' => 'O cliente comprou?'])
            ->assertSessionHasErrors(['question' => 'Conecte uma IA antes de perguntar.']);

        Http::assertNothingSent();
    }

    public function test_pergunta_vai_mascarada_e_a_resposta_fica_na_conversa(): void
    {
        $this->connect();

        $this->actingAs($this->user)
            ->post($this->url(), ['question' => 'O Cliente Teste (11 98888-7777) comprou?'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(function (Request $request) {
            if (! str_ends_with($request->url(), ':generateContent')) {
                return false;
            }

            $text = $request['contents'][0]['parts'][0]['text'];

            return ! str_contains($text, 'Cliente Teste')
                && ! str_contains($text, '98888')
                && str_contains($text, 'Pergunta do dono: O [cliente] ([telefone]) comprou?')
                && str_contains($text, '01/09/2026 09:05 · Vendedora Ana: Bom dia! Custa R$ 90.')
                && str_contains($text, '(ligação perdida)');
        });

        $question = AiQuestion::sole();
        $this->assertSame(AiQuestion::DONE, $question->status);
        $this->assertSame('O cliente fechou o pedido às 20:00.', $question->answer);
        $this->assertSame([1200, 40], [$question->input_tokens, $question->output_tokens]);

        $this->actingAs($this->user)
            ->get("/conversas/{$this->conversation->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('panels.ai.connection.providerName', 'Gemini')
                ->where('panels.ai.questions.0.answer', 'O cliente fechou o pedido às 20:00.'));
    }

    public function test_auditoria_da_pergunta_so_tem_numeros(): void
    {
        $this->connect();

        $this->actingAs($this->user)->post($this->url(), ['question' => 'Resuma a conversa']);

        $this->assertSame(
            ['provider' => 'gemini', 'ok' => true, 'input_tokens' => 1200, 'output_tokens' => 40],
            AuditEntry::where('action', 'ai.question_asked')->sole()->meta,
        );
    }

    public function test_pergunta_sobre_uma_mensagem_leva_a_mensagem_em_foco(): void
    {
        $this->connect();
        $message = Message::where('conversation_id', $this->conversation->id)->where('author', 'seller')->first();

        $this->actingAs($this->user)
            ->post($this->url(), ['question' => 'Essa resposta foi boa?', 'message_id' => $message->id])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), ':generateContent')
            && str_contains($request['contents'][0]['parts'][0]['text'], 'Mensagem em foco: 01/09/2026 09:05 · Vendedora Ana: Bom dia! Custa R$ 90.'));
        $this->assertSame($message->id, AiQuestion::sole()->message_id);
    }

    public function test_mensagem_de_outra_conversa_e_recusada(): void
    {
        $this->connect();
        $other = Message::where('conversation_id', '!=', $this->conversation->id)->first();

        $this->actingAs($this->user)
            ->post($this->url(), ['question' => 'E essa?', 'message_id' => $other->id])
            ->assertSessionHasErrors(['message_id' => 'Essa mensagem não é desta conversa.']);

        $this->assertSame(0, AiQuestion::count());
    }

    public function test_falha_do_provedor_fica_registrada_e_avisa(): void
    {
        AiConnection::create([
            'organization_id' => $this->user->organization_id,
            'provider' => 'gemini',
            'label' => 'Gemini',
            'api_key' => FakeGemini::KEY,
            'key_hint' => 'abcd',
            'model' => 'gemini-2.5-flash',
            'is_default' => true,
        ]);
        FakeGemini::quotaOnGenerate();

        $this->actingAs($this->user)
            ->post($this->url(), ['question' => 'Resuma'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');

        $question = AiQuestion::sole();
        $this->assertSame(AiQuestion::FAILED, $question->status);
        $this->assertSame('quota', $question->error_code);
        $this->assertNull($question->answer);
    }

    public function test_conversa_de_outra_empresa_nao_existe(): void
    {
        $intruder = User::factory()->create();
        FakeGemini::ok();
        $this->actingAs($intruder)->post('/ia/conexoes', [
            'provider' => 'gemini', 'api_key' => FakeGemini::KEY, 'model' => 'gemini-2.5-flash', 'label' => 'Gemini',
        ]);

        $this->actingAs($intruder)
            ->post($this->url(), ['question' => 'O cliente comprou?'])
            ->assertNotFound();

        $this->assertSame(0, AiQuestion::count());
    }

    public function test_pergunta_vazia_e_recusada(): void
    {
        $this->actingAs($this->user)
            ->post($this->url(), ['question' => ''])
            ->assertSessionHasErrors(['question' => 'Escreva a pergunta.']);
    }

    public function test_visitante_nao_pergunta(): void
    {
        auth()->logout();

        $this->post($this->url(), ['question' => 'Oi'])->assertRedirect('/login');
    }

    private function connect(): void
    {
        FakeGemini::ok();
        $this->actingAs($this->user)->post('/ia/conexoes', [
            'provider' => 'gemini', 'api_key' => FakeGemini::KEY, 'model' => 'gemini-2.5-flash', 'label' => 'Gemini',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, AiConnection::count());

        // Segue o redirecionamento, como o navegador, para o aviso de sucesso não sobrar.
        $this->actingAs($this->user)->get('/ia');
    }

    private function url(): string
    {
        return "/conversas/{$this->conversation->id}/perguntas";
    }
}

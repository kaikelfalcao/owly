<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * owly:integrity: a foto de antes de uma migração e a conferência depois.
 */
class IntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->organization = Organization::factory()->create();
        $this->store('wa:5511911110001', ['a', 'b', 'c']);
        $this->store('wa:5511911110002', ['a', 'd']);
    }

    public function test_a_foto_guarda_so_numeros_e_hash(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])->assertSuccessful();

        $file = Storage::disk('local')->files('integrity')[0];
        $snapshot = json_decode((string) Storage::disk('local')->get($file), true);

        $this->assertSame(['messages' => 5, 'contacts' => 2, 'conversations' => 2, 'empty_conversations' => 0, 'opened_by_company' => 0], array_diff_key($snapshot['facts'], ['messages_hash' => true, 'first_conversations' => true]));
        $this->assertCount(2, $snapshot['facts']['first_conversations']);
        $this->assertSame(64, strlen($snapshot['facts']['messages_hash']));
        $this->assertSame(2, $snapshot['panel']['tudo']['conversations']);

        $raw = (string) Storage::disk('local')->get($file);
        $this->assertStringNotContainsString('Cliente Inventado', $raw);
        $this->assertStringNotContainsString('5511911110001', $raw);
        $this->assertStringNotContainsString('mensagem a', $raw);
    }

    public function test_sem_mudanca_a_conferencia_passa(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])->assertSuccessful();

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])
            ->expectsOutputToContain('Tudo confere')
            ->assertSuccessful();
    }

    public function test_mensagem_perdida_reprova_a_conferencia(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])->assertSuccessful();

        $this->inOrganization($this->organization, fn () => Message::where('external_id', 'd')->delete());

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])
            ->expectsOutputToContain('O total de mensagens mudou.')
            ->expectsOutputToContain('As mensagens mudaram (hash diferente).')
            ->expectsOutputToContain('Conversas com total, início ou fim diferente das mensagens: 1')
            ->assertFailed();
    }

    public function test_mensagem_trocada_de_cliente_reprova_a_conferencia(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])->assertSuccessful();

        $this->inOrganization($this->organization, function (): void {
            [$first, $second] = Conversation::orderBy('id')->get()->all();
            Message::where('conversation_id', $first->id)->where('external_id', 'b')->update(['contact_id' => $second->contact_id]);
        });

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])
            ->expectsOutputToContain('Mensagens com cliente diferente do da conversa: 1')
            ->assertFailed();
    }

    public function test_atendimento_aberto_pela_empresa_no_meio_do_historico_reprova(): void
    {
        $this->inOrganization($this->organization, fn () => Conversation::query()->update(['opened_by' => 'company']));

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])
            ->expectsOutputToContain('Atendimentos abertos por mensagem que não é do cliente (fora o primeiro do histórico): 2')
            ->assertFailed();
    }

    public function test_corte_desatualizado_reprova(): void
    {
        // Uma mensagem do cliente uma semana depois, colada à mão no mesmo
        // atendimento: o owly:recut separaria. A referência anda junto, e o
        // atendimento do outro cliente, ainda aberto, também teria de fechar.
        $this->inOrganization($this->organization, function (): void {
            $conversation = Conversation::orderBy('id')->first();
            Message::where('conversation_id', $conversation->id)->where('external_id', 'c')->update(['sent_at' => '2026-09-08 12:00:00']);
            $conversation->update(['last_message_at' => '2026-09-08 12:00:00']);
        });

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])
            ->expectsOutputToContain('Clientes cujo corte mudaria se o owly:recut rodasse de novo: 2')
            ->assertFailed();
    }

    public function test_atendimento_mais_antigo_com_outro_id_reprova(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])->assertSuccessful();
        $file = Storage::disk('local')->files('integrity')[0];
        $snapshot = json_decode((string) Storage::disk('local')->get($file), true);
        $snapshot['facts']['first_conversations'] = array_map(fn (int $id) => $id + 100, $snapshot['facts']['first_conversations']);
        Storage::disk('local')->put($file, (string) json_encode($snapshot));

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])
            ->expectsOutputToContain('Clientes cujo atendimento mais antigo não ficou com o id de antes: 2')
            ->assertFailed();
    }

    public function test_pergunta_a_ia_fora_do_atendimento_da_mensagem_reprova(): void
    {
        $this->inOrganization($this->organization, function (): void {
            [$first, $second] = Conversation::orderBy('id')->pluck('id')->all();
            AiQuestion::create([
                'conversation_id' => $second,
                'message_id' => Message::where('conversation_id', $first)->value('id'),
                'question' => 'Fechou?',
                'provider' => 'gemini',
                'model' => 'teste',
                'status' => AiQuestion::DONE,
            ]);
        });

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])
            ->expectsOutputToContain('Perguntas à IA fora do atendimento da mensagem ou em atendimento que não existe: 1')
            ->assertFailed();
    }

    public function test_dado_ja_errado_barra_a_foto(): void
    {
        $this->inOrganization($this->organization, fn () => Conversation::query()->update(['messages_count' => 99]));

        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--snapshot' => true])
            ->expectsOutputToContain('Conversas com total, início ou fim diferente das mensagens: 2')
            ->assertFailed();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_conferir_sem_foto_avisa(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])
            ->expectsOutputToContain('Rode antes com --snapshot')
            ->assertFailed();
    }

    public function test_a_foto_e_de_uma_empresa_so(): void
    {
        $other = Organization::factory()->create();
        $this->artisan('owly:integrity', ['--empresa' => $other->id, '--snapshot' => true])->assertSuccessful();

        $snapshot = json_decode((string) Storage::disk('local')->get(Storage::disk('local')->files('integrity')[0]), true);

        $this->assertSame(0, $snapshot['facts']['messages']);
        $this->artisan('owly:integrity', ['--empresa' => $this->organization->id, '--compare' => true])->assertFailed();
    }

    public function test_empresa_que_nao_existe_e_recusada(): void
    {
        $this->artisan('owly:integrity', ['--empresa' => 999, '--snapshot' => true])
            ->expectsOutputToContain('Empresa não encontrada')
            ->assertExitCode(2);
    }

    /**
     * @param  list<string>  $ids
     */
    private function store(string $contactKey, array $ids): void
    {
        $messages = array_map(fn (string $id, int $i) => new IncomingMessage(
            externalId: $id,
            sentAt: CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC')->addMinutes($i),
            direction: IncomingMessage::IN,
            author: 'contact',
            body: "mensagem {$id}",
        ), $ids, array_keys($ids));

        app(ConversationStore::class)->store($this->organization->id, 'zip', new IncomingConversation(
            contactKey: $contactKey,
            contactName: 'Cliente Inventado',
            contactPhone: substr($contactKey, 3),
            messages: $messages,
        ));
    }
}

<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Data\StoreResult;
use App\Domains\Conversations\Events\ConversationsRecut;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * O corte gravando: importação em partes, arquivo antigo, re-corte, tudo ou
 * nada (I11) e as perguntas à IA acompanhando as mensagens. Horário padrão
 * da empresa: segunda a sexta 8h às 18h e sábado 8h às 12h.
 */
class ConversationCutFlowTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT = 'wa:5511911110001';

    private Organization $organization;

    private bool $failOnUpdate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();

        DB::listen(function (QueryExecuted $query): void {
            if ($this->failOnUpdate && str_starts_with($query->sql, 'update "conversations"')) {
                throw new RuntimeException('falha no meio do corte');
            }
        });
    }

    public function test_importar_em_duas_partes_da_o_mesmo_corte_que_de_uma_vez(): void
    {
        $messages = [
            ['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's'], ['c', '07/10 09:00', 'c'],
            ['d', '07/10 09:30', 's'], ['e', '09/10 08:00', 's'], ['f', '14/10 10:00', 'c'],
        ];
        $other = Organization::factory()->create();

        $this->store($messages);
        $this->store(array_slice($messages, 0, 3), organization: $other);
        $this->store(array_slice($messages, 3), organization: $other);

        $this->assertSame($this->episodes($this->organization), $this->episodes($other));
        $this->assertSame([['a', 'contact', 2, 'closed'], ['c', 'contact', 3, 'closed'], ['f', 'contact', 1, 'open']], $this->episodes($this->organization));
    }

    public function test_arquivo_com_mensagens_antigas_refaz_o_historico_sem_trocar_ids(): void
    {
        $this->store([['c', '07/10 09:00', 'c'], ['d', '07/10 09:05', 's']]);
        $id = $this->inOrganization($this->organization, fn () => Conversation::sole()->id);

        // Terça 17h e quarta 9h: nenhum dia útil no meio, viram um só.
        $this->store([['b', '06/10 17:00', 'c']]);
        $this->assertSame([['b', 'contact', 3, 'open']], $this->episodes());
        $this->assertSame($id, $this->inOrganization($this->organization, fn () => Conversation::sole()->id));

        // Uma semana antes: outro atendimento, e o de quarta continua o mesmo.
        $this->store([['a', '29/09 10:00', 'c']]);
        $this->assertSame([['a', 'contact', 1, 'closed'], ['b', 'contact', 3, 'open']], $this->episodes());
        $this->assertSame($id, $this->inOrganization($this->organization, fn () => Conversation::orderByDesc('first_message_at')->first()->id));
    }

    public function test_cobranca_da_vendedora_reabre_o_atendimento(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's']]);
        // Outro cliente escreve quinta: a referência anda e o primeiro fecha.
        $this->store([['x', '08/10 08:00', 'c']], 'wa:5511911110002');
        app(ConversationStore::class)->closeIdle($this->organization->id, CarbonImmutable::parse('2026-10-08 11:00', 'UTC'));
        $this->assertSame([['a', 'contact', 2, 'closed']], $this->episodes(contact: self::CLIENT));

        $this->store([['c', '08/10 09:00', 's']]);

        $this->assertSame([['a', 'contact', 3, 'open']], $this->episodes(contact: self::CLIENT));
    }

    public function test_falha_no_meio_da_gravacao_nao_muda_nada(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's']]);
        $before = $this->snapshot();

        $this->failOnUpdate = true;

        try {
            $this->store([['c', '07/10 09:00', 'c'], ['d', '07/10 09:05', 's']]);
            $this->fail('A gravação deveria ter falhado.');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_recorte_refaz_o_estado_de_antes_da_migracao_e_e_idempotente(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's'], ['c', '07/10 09:00', 'c'], ['d', '07/10 09:05', 's']]);
        $first = $this->merge();

        $this->artisan('owly:recut', ['--empresa' => $this->organization->id, '--dry-run' => true])
            ->expectsOutputToContain('Nada foi gravado')
            ->assertSuccessful();
        $this->assertSame([['a', 'contact', 4, 'open']], $this->episodes());

        $this->artisan('owly:recut', ['--empresa' => $this->organization->id])->assertSuccessful();
        $this->assertSame([['a', 'contact', 2, 'closed'], ['c', 'contact', 2, 'open']], $this->episodes());
        // O atendimento mais antigo fica com o id da conversa de antes.
        $this->assertSame($first, $this->inOrganization($this->organization, fn () => Conversation::orderBy('first_message_at')->first()->id));

        $before = $this->snapshot();
        $this->artisan('owly:recut', ['--empresa' => $this->organization->id])->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_falha_no_meio_do_recorte_deixa_o_cliente_como_estava(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's'], ['c', '07/10 09:00', 'c']]);
        $this->merge();
        $before = $this->snapshot();

        $this->failOnUpdate = true;

        try {
            $this->artisan('owly:recut', ['--empresa' => $this->organization->id])->run();
            $this->fail('O re-corte deveria ter falhado.');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->snapshot());

        // Rodar de novo termina o que faltou.
        $this->failOnUpdate = false;
        $this->artisan('owly:recut', ['--empresa' => $this->organization->id])->assertSuccessful();
        $this->assertCount(2, $this->episodes());
    }

    public function test_perguntas_a_ia_acompanham_a_mensagem_em_foco(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's'], ['c', '07/10 09:00', 'c']]);
        $merged = $this->merge();
        [$onMessage, $general] = $this->inOrganization($this->organization, fn () => [
            $this->question($merged, Message::where('external_id', 'c')->value('id')),
            $this->question($merged, null),
        ]);

        $this->artisan('owly:recut', ['--empresa' => $this->organization->id])->assertSuccessful();

        $this->inOrganization($this->organization, function () use ($onMessage, $general, $merged): void {
            $this->assertSame(Message::where('external_id', 'c')->value('conversation_id'), $onMessage->fresh()->conversation_id);
            // Sem mensagem em foco, fica no primeiro atendimento.
            $this->assertSame($merged, $general->fresh()->conversation_id);
        });
    }

    public function test_atendimentos_que_se_juntam_avisam_e_levam_as_perguntas(): void
    {
        $this->store([['a', '05/10 10:00', 'c'], ['b', '05/10 10:10', 's'], ['c', '07/10 09:00', 'c']]);
        [$first, $second] = $this->inOrganization($this->organization, fn () => Conversation::orderBy('first_message_at')->pluck('id')->all());
        $question = $this->inOrganization($this->organization, fn () => $this->question($second, null));
        Event::listen(ConversationsRecut::class, function (ConversationsRecut $event) use (&$seen): void {
            $seen = $event;
        });

        // Uma mensagem da vendedora na terça, vinda de um arquivo antigo: o
        // silêncio de terça some e os dois viram um.
        $this->store([['t', '06/10 12:00', 's']]);

        $this->assertSame([['a', 'contact', 4, 'open']], $this->episodes());
        $this->assertSame([$second], $seen->conversationIds);
        $this->assertSame([$second => $first], $seen->replaced);
        $this->assertSame($first, $this->inOrganization($this->organization, fn () => $question->fresh()->conversation_id));
    }

    /**
     * Junta todos os atendimentos de cada cliente no mais antigo: o estado de
     * logo depois da migração, antes do owly:recut.
     */
    private function merge(): int
    {
        return $this->inOrganization($this->organization, function (): int {
            $first = Conversation::orderBy('first_message_at')->first();
            Message::query()->update(['conversation_id' => $first->id]);
            Conversation::where('id', '!=', $first->id)->delete();
            $first->update([
                'messages_count' => Message::count(),
                'last_message_at' => Message::max('sent_at'),
                'status' => Conversation::OPEN,
            ]);

            return $first->id;
        });
    }

    private function question(int $conversationId, ?int $messageId): AiQuestion
    {
        return AiQuestion::create([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'question' => 'O cliente fechou?',
            'provider' => 'gemini',
            'model' => 'teste',
            'status' => AiQuestion::DONE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return $this->inOrganization($this->organization, fn () => [
            'conversations' => Conversation::orderBy('id')->get(['id', 'first_message_at', 'last_message_at', 'messages_count', 'status', 'opened_by'])->toArray(),
            'messages' => Message::orderBy('id')->pluck('conversation_id', 'external_id')->all(),
        ]);
    }

    /**
     * Por atendimento, em ordem: mensagem que abriu, quem abriu, total e situação.
     *
     * @return list<array{0: string, 1: string, 2: int, 3: string}>
     */
    private function episodes(?Organization $organization = null, ?string $contact = null): array
    {
        return $this->inOrganization($organization ?? $this->organization, fn () => Conversation::query()
            ->when($contact, fn ($query) => $query->whereHas('contact', fn ($q) => $q->where('external_key', $contact)))
            ->orderBy('first_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                Message::where('conversation_id', $c->id)->orderBy('sent_at')->orderBy('id')->value('external_id'),
                $c->opened_by,
                $c->messages_count,
                $c->status,
            ])
            ->all());
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $messages  [id externo, dia/mês hora:minuto, c (cliente) ou s (vendedora)]
     */
    private function store(array $messages, string $contact = self::CLIENT, ?Organization $organization = null): StoreResult
    {
        return app(ConversationStore::class)->store(($organization ?? $this->organization)->id, 'zip', new IncomingConversation(
            contactKey: $contact,
            contactName: 'Cliente Inventado',
            contactPhone: substr($contact, 3),
            messages: array_map(fn (array $m) => new IncomingMessage(
                externalId: $m[0],
                sentAt: CarbonImmutable::createFromFormat('d/m/Y H:i', str_replace(' ', '/2026 ', $m[1]), 'America/Sao_Paulo'),
                direction: $m[2] === 'c' ? IncomingMessage::IN : IncomingMessage::OUT,
                author: $m[2] === 'c' ? 'contact' : 'seller',
                sellerName: $m[2] === 's' ? 'Ana' : null,
                body: "mensagem {$m[0]}",
            ), $messages),
        ));
    }
}

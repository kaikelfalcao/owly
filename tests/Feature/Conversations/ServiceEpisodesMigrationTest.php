<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A migration que transforma conversa em atendimento, nos dois sentidos.
 */
class ServiceEpisodesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_10_06_000002_turn_conversations_into_service_episodes.php');

        // O SQLite refaz a tabela para mudar índice e coluna. Fora de teste o
        // Laravel desliga as chaves nessa hora; dentro da transação do teste
        // isso não vale, então a conferência fica para o fim (que não chega:
        // o teste desfaz tudo).
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }

    public function test_voltar_junta_os_atendimentos_no_mais_antigo_sem_perder_mensagem(): void
    {
        $organization = Organization::factory()->create();
        $this->store($organization, ['2026-10-05 10:00', '2026-10-07 09:00', '2026-10-14 09:00']);
        $oldest = DB::table('conversations')->orderBy('first_message_at')->value('id');
        $this->assertSame(3, DB::table('conversations')->count());

        $this->migration->down();

        $conversation = DB::table('conversations')->sole();
        $this->assertSame($oldest, $conversation->id);
        $this->assertSame(3, (int) $conversation->messages_count);
        $this->assertSame([$oldest], DB::table('messages')->distinct()->pluck('conversation_id')->all());
        $this->assertSame(3, DB::table('messages')->count());

        $this->migration->up();

        $this->assertSame('contact', DB::table('conversations')->value('opened_by'));
    }

    public function test_conversa_sem_mensagem_sai_na_ida(): void
    {
        $organization = Organization::factory()->create();
        $this->store($organization, ['2026-10-05 10:00']);
        $this->migration->down();
        $contact = DB::table('contacts')->insertGetId(['organization_id' => $organization->id, 'external_key' => 'wa:5511911110009']);
        DB::table('conversations')->insert(['organization_id' => $organization->id, 'contact_id' => $contact]);

        $this->migration->up();

        $this->assertSame(1, DB::table('conversations')->count());
    }

    /**
     * @param  list<string>  $times  mensagens do cliente, horário de São Paulo
     */
    private function store(Organization $organization, array $times): void
    {
        app(ConversationStore::class)->store($organization->id, 'zip', new IncomingConversation(
            contactKey: 'wa:5511911110001',
            contactName: 'Cliente Inventado',
            contactPhone: '5511911110001',
            messages: array_map(fn (string $at, int $i) => new IncomingMessage(
                externalId: "m{$i}",
                sentAt: CarbonImmutable::parse($at, 'America/Sao_Paulo'),
                direction: IncomingMessage::IN,
                author: 'contact',
                body: "mensagem {$i}",
            ), $times, array_keys($times)),
        ));
    }
}

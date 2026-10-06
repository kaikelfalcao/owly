<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_cliente_conversa_e_totais(): void
    {
        $organization = Organization::factory()->create();

        $result = $this->store($organization->id, ['a', 'b']);

        $this->assertTrue($result->created);
        $this->assertSame(2, $result->newMessages);

        $conversation = $this->inOrganization($organization, fn () => Conversation::sole());
        $this->assertSame(2, $conversation->messages_count);
        $this->assertSame('2026-09-01 12:00:00', $conversation->first_message_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 12:01:00', $conversation->last_message_at->format('Y-m-d H:i:s'));
    }

    public function test_gravar_de_novo_so_acrescenta_o_que_e_novo(): void
    {
        $organization = Organization::factory()->create();
        $this->store($organization->id, ['a', 'b']);

        $result = $this->store($organization->id, ['a', 'b', 'c']);

        $this->assertFalse($result->created);
        $this->assertSame(1, $result->newMessages);
        $this->assertSame(2, $result->knownMessages);
        $this->inOrganization($organization, function (): void {
            $this->assertSame(3, Message::count());
            $this->assertSame(3, Conversation::sole()->messages_count);
        });
    }

    public function test_o_mesmo_cliente_em_outra_empresa_e_outra_conversa(): void
    {
        $this->store(Organization::factory()->create()->id, ['a']);
        $this->store(Organization::factory()->create()->id, ['a']);

        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseCount('messages', 2);
    }

    /**
     * @param  list<string>  $ids
     */
    private function store(int $organizationId, array $ids)
    {
        $messages = array_map(fn (string $id, int $i) => new IncomingMessage(
            externalId: $id,
            sentAt: CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC')->addMinutes($i),
            direction: IncomingMessage::IN,
            author: 'contact',
            body: "mensagem {$id}",
        ), $ids, array_keys($ids));

        return app(ConversationStore::class)->store($organizationId, 'zip', new IncomingConversation(
            contactKey: 'wa:5511911112222',
            contactName: 'Cliente Inventado',
            contactPhone: '5511911112222',
            messages: $messages,
        ));
    }
}

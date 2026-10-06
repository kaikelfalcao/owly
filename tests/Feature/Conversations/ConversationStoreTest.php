<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Conversations\Data\IncomingMessage;
use App\Domains\Conversations\Data\StoreResult;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_cliente_conversa_e_totais(): void
    {
        $organization = Organization::factory()->create();

        $result = $this->store($organization->id, ['a', 'b']);

        $this->assertTrue($result->contactCreated);
        $this->assertSame(1, $result->conversationsCreated);
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

        $this->assertFalse($result->contactCreated);
        $this->assertSame(0, $result->conversationsCreated);
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

    public function test_mensagem_guarda_o_cliente_e_a_importacao_que_a_trouxe(): void
    {
        $organization = Organization::factory()->create();
        $first = $this->import($organization);
        $second = $this->import($organization);

        $this->store($organization->id, ['a', 'b'], importId: $first->id);
        $this->store($organization->id, ['a', 'b', 'c'], importId: $second->id);

        $this->inOrganization($organization, function () use ($first, $second): void {
            $contact = Contact::sole();
            $this->assertSame([$contact->id], Message::distinct()->pluck('contact_id')->all());
            // A mensagem que já existia continua com a importação que a trouxe primeiro.
            $this->assertSame(
                ['a' => $first->id, 'b' => $first->id, 'c' => $second->id],
                Message::orderBy('external_id')->pluck('import_id', 'external_id')->all(),
            );
        });
    }

    public function test_a_mesma_mensagem_de_dois_clientes_nao_se_confunde(): void
    {
        // O id externo do zip vem do minuto, do telefone e do texto: o mesmo
        // "Bom dia" da loja para dois clientes no mesmo minuto dá o mesmo id.
        $organization = Organization::factory()->create();

        $this->store($organization->id, ['bom-dia'], contactKey: 'wa:5511911110001');
        $result = $this->store($organization->id, ['bom-dia'], contactKey: 'wa:5511911110002');

        $this->assertSame(1, $result->newMessages);
        $this->assertSame(2, $this->inOrganization($organization, fn () => Message::count()));
    }

    public function test_apagar_conversa_com_mensagem_e_recusado(): void
    {
        $organization = Organization::factory()->create();
        $this->store($organization->id, ['a']);

        $this->expectException(QueryException::class);

        $this->inOrganization($organization, fn () => Conversation::sole()->delete());
    }

    public function test_apagar_o_cliente_leva_conversa_e_mensagens(): void
    {
        $organization = Organization::factory()->create();
        $this->store($organization->id, ['a', 'b']);

        $this->inOrganization($organization, fn () => Contact::sole()->delete());

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('messages', 0);
    }

    private function import(Organization $organization): Import
    {
        return $this->inOrganization($organization, fn () => Import::create([
            'format' => 'whatsapp-xlsx-zip',
            'status' => Import::DONE,
            'file_name' => 'conversas.zip',
            'file_size' => 10,
            'file_hash' => hash('sha256', (string) random_int(0, PHP_INT_MAX)),
        ]));
    }

    /**
     * @param  list<string>  $ids
     */
    private function store(int $organizationId, array $ids, string $contactKey = 'wa:5511911112222', ?int $importId = null): StoreResult
    {
        $messages = array_map(fn (string $id, int $i) => new IncomingMessage(
            externalId: $id,
            sentAt: CarbonImmutable::parse('2026-09-01 12:00:00', 'UTC')->addMinutes($i),
            direction: IncomingMessage::IN,
            author: 'contact',
            body: "mensagem {$id}",
        ), $ids, array_keys($ids));

        return app(ConversationStore::class)->store($organizationId, 'zip', new IncomingConversation(
            contactKey: $contactKey,
            contactName: 'Cliente Inventado',
            contactPhone: substr($contactKey, 3),
            messages: $messages,
        ), $importId);
    }
}

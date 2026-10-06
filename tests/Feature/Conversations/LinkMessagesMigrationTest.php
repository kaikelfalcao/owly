<?php

namespace Tests\Feature\Conversations;

use App\Domains\Accounts\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A migration que liga a mensagem ao cliente e à importação, rodada sobre
 * dados no formato de antes.
 */
class LinkMessagesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require database_path('migrations/2026_10_06_000001_link_messages_to_contacts_and_imports.php');
        $this->migration->down();
    }

    public function test_cada_mensagem_ganha_o_cliente_da_sua_conversa(): void
    {
        $organization = Organization::factory()->create();
        $ana = $this->conversation($organization, 'wa:5511911110001');
        $bia = $this->conversation($organization, 'wa:5511911110002');
        $this->message($ana, 'x1');
        $this->message($ana, 'x2');
        // O mesmo id externo em clientes diferentes era permitido e continua.
        $this->message($bia, 'x1');

        $this->migration->up();

        $this->assertSame(
            [[$ana['contact_id'], 'x1'], [$ana['contact_id'], 'x2'], [$bia['contact_id'], 'x1']],
            DB::table('messages')->orderBy('id')->get()->map(fn ($m) => [$m->contact_id, $m->external_id])->all(),
        );
    }

    public function test_importacao_so_e_preenchida_quando_nao_ha_duvida(): void
    {
        $single = Organization::factory()->create();
        $two = Organization::factory()->create();
        $failed = Organization::factory()->create();
        $import = $this->import($single, 'done');
        $this->import($single, 'pending');
        $this->import($two, 'done');
        $this->import($two, 'done');
        // Uma importação que falhou pode ter gravado parte das mensagens.
        $this->import($failed, 'done');
        $this->import($failed, 'failed');

        foreach ([$single, $two, $failed] as $organization) {
            $this->message($this->conversation($organization, 'wa:5511911110003'), 'x1');
        }

        $this->migration->up();

        $this->assertSame(
            [$single->id => $import, $two->id => null, $failed->id => null],
            DB::table('messages')->orderBy('id')->pluck('import_id', 'organization_id')->all(),
        );
    }

    /**
     * @return array{id: int, organization_id: int, contact_id: int}
     */
    private function conversation(Organization $organization, string $key): array
    {
        $contact = DB::table('contacts')->insertGetId(['organization_id' => $organization->id, 'external_key' => $key]);
        $id = DB::table('conversations')->insertGetId(['organization_id' => $organization->id, 'contact_id' => $contact]);

        return ['id' => $id, 'organization_id' => $organization->id, 'contact_id' => $contact];
    }

    /**
     * @param  array{id: int, organization_id: int, contact_id: int}  $conversation
     */
    private function message(array $conversation, string $externalId): void
    {
        DB::table('messages')->insert([
            'organization_id' => $conversation['organization_id'],
            'conversation_id' => $conversation['id'],
            'source' => 'zip',
            'external_id' => $externalId,
            'sent_at' => '2026-09-01 12:00:00',
            'direction' => 'in',
            'author' => 'contact',
        ]);
    }

    private function import(Organization $organization, string $status): int
    {
        return DB::table('imports')->insertGetId([
            'organization_id' => $organization->id,
            'format' => 'whatsapp-xlsx-zip',
            'status' => $status,
            'file_name' => 'conversas.zip',
            'file_size' => 10,
            'file_hash' => hash('sha256', (string) random_int(0, PHP_INT_MAX)),
        ]);
    }
}

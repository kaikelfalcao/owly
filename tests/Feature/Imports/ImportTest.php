<?php

namespace Tests\Feature\Imports;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Conversations\Models\Seller;
use App\Domains\Imports\Jobs\ProcessImport;
use App\Domains\Imports\Models\Import;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use App\Platform\Queue\OneAtATime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WhatsAppZip;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_visitante_nao_importa(): void
    {
        $this->get('/importar')->assertRedirect('/login');
        $this->post('/importar')->assertRedirect('/login');
    }

    public function test_tela_de_importar_abre_vazia(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/importar')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports/index')
                ->has('imports', 0)
                ->where('maxSizeMb', 50));
    }

    public function test_importar_o_zip_grava_as_conversas_e_avisa_no_sino(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/importar', ['file' => $this->upload(WhatsAppZip::sample())])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $import = Import::sole();
        $this->assertSame(Import::DONE, $import->status);
        $this->assertSame($user->organization_id, $import->organization_id);
        $this->assertSame(2, $import->stats['contacts_new']);
        // O Cliente Teste voltou depois de dois dias úteis: dois atendimentos.
        $this->assertSame(3, $import->stats['conversations_new']);
        $this->assertSame(9, $import->stats['messages_new']);
        $this->assertSame('2026-09-01T12:00:00+00:00', $import->stats['first_at']);
        $this->assertSame([], $import->stats['gaps']);

        $this->assertSame(3, Conversation::where('organization_id', $user->organization_id)->count());
        $this->assertSame(['Ana', 'Bia'], Seller::orderBy('name')->pluck('name')->all());
        $this->assertSame(9, Message::count());

        // O zip não fica guardado depois de lido.
        $this->assertNull($import->path);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $notice = $user->notifications()->sole();
        $this->assertSame('Importação concluída', $notice->data['title']);
        $this->assertSame('2 clientes novos, 3 atendimentos novos, 9 mensagens novas.', $notice->data['body']);

        $this->assertSame(['imports.finished', 'imports.started'], AuditEntry::where('action', 'like', 'imports.%')->orderByDesc('id')->pluck('action')->all());
    }

    public function test_um_zip_que_repete_o_periodo_nao_duplica_mensagens(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/importar', ['file' => $this->upload(WhatsAppZip::sample())]);

        $bigger = WhatsAppZip::sample()->sheet('5511966665555.xlsx', [
            ['2026-09-05', '11:00:00', '5511966665555', 'Outro Cliente', 'Bom dia'],
        ]);
        $this->actingAs($user)->post('/importar', ['file' => $this->upload($bigger)])->assertSessionHasNoErrors();

        [$first, $second] = Import::orderBy('id')->get()->all();
        $this->assertSame(1, $second->stats['conversations_new']);
        $this->assertSame(1, $second->stats['messages_new']);
        $this->assertSame(9, $second->stats['messages_known']);
        $this->assertSame(10, Message::count());

        // Cada mensagem fica com a importação que a trouxe primeiro.
        $this->assertSame(9, Message::where('import_id', $first->id)->count());
        $this->assertSame(1, Message::where('import_id', $second->id)->count());
    }

    public function test_importar_numa_empresa_vazia_da_o_mesmo_corte_do_recorte(): void
    {
        // A: importa com o corte. B: importa, junta tudo como logo depois da
        // migração e roda o owly:recut. Os atendimentos têm de ser os mesmos.
        [$a, $b] = [User::factory()->create(), User::factory()->create()];

        foreach ([$a, $b] as $user) {
            $this->actingAs($user)->post('/importar', ['file' => $this->upload(WhatsAppZip::sample())])->assertSessionHasNoErrors();
        }

        $this->inOrganization($b->organization_id, function (): void {
            foreach (Conversation::orderBy('first_message_at')->get()->groupBy('contact_id') as $conversations) {
                $first = $conversations->first();
                Message::where('contact_id', $first->contact_id)->update(['conversation_id' => $first->id]);
                Conversation::whereIn('id', $conversations->slice(1)->pluck('id'))->delete();
                $first->update(['messages_count' => $conversations->sum('messages_count'), 'last_message_at' => $conversations->max('last_message_at'), 'status' => Conversation::OPEN]);
            }
        });

        $this->artisan('owly:recut', ['--empresa' => $b->organization_id])->assertSuccessful();

        $episodes = fn (User $user) => $this->inOrganization($user->organization_id, fn () => Conversation::orderBy('first_message_at')->get()
            ->map(fn (Conversation $c) => [$c->opened_by, $c->status, $c->messages_count, $c->first_message_at->toIso8601String(), $c->last_message_at->toIso8601String()])
            ->all());

        $this->assertCount(3, $episodes($a));
        $this->assertSame($episodes($a), $episodes($b));
    }

    public function test_duas_importacoes_da_mesma_empresa_nao_rodam_juntas(): void
    {
        $job = new ProcessImport(7, 1, 'America/Sao_Paulo');
        $lock = collect($job->middleware())->first(fn ($middleware) => $middleware instanceof OneAtATime);

        $this->assertNotNull($lock);
        $this->assertSame('organization:7:data', $lock->key);
    }

    public function test_o_mesmo_arquivo_duas_vezes_e_recusado(): void
    {
        $user = User::factory()->create();
        $path = WhatsAppZip::sample()->path();

        $this->actingAs($user)->post('/importar', ['file' => new UploadedFile($path, 'conversas.zip', 'application/zip', null, true)]);
        $this->actingAs($user)
            ->post('/importar', ['file' => new UploadedFile($path, 'conversas.zip', 'application/zip', null, true)])
            ->assertSessionHasErrors(['file' => 'Este zip já foi importado em '.Import::sole()->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i').'.']);

        $this->assertSame(1, Import::count());
    }

    public function test_o_mesmo_zip_em_outra_empresa_entra_normalmente(): void
    {
        $path = WhatsAppZip::sample()->path();

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->post('/importar', ['file' => new UploadedFile($path, 'conversas.zip', 'application/zip', null, true)])
                ->assertSessionHasNoErrors();
        }

        $this->acrossOrganizations(function (): void {
            $this->assertSame(2, Import::where('status', Import::DONE)->count());
            $this->assertSame(6, Conversation::count());
        });
    }

    public function test_arquivo_que_nao_e_zip_e_recusado(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/importar', ['file' => UploadedFile::fake()->create('conversas.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['file' => 'O arquivo precisa ser um .zip.']);

        $this->assertSame(0, Import::count());
    }

    public function test_sem_arquivo_pede_o_zip(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/importar')
            ->assertSessionHasErrors(['file' => 'Escolha o arquivo .zip exportado do WhatsApp.']);
    }

    public function test_zip_sem_conversas_falha_e_avisa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/importar', ['file' => $this->upload(WhatsAppZip::make()->file('leia-me.txt', 'nada'))]);

        $import = Import::sole();
        $this->assertSame(Import::FAILED, $import->status);
        $this->assertSame('no_conversations', $import->error_code);
        $this->assertSame('A importação não deu certo', $user->notifications()->sole()->data['title']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'imports.failed']);

        $this->actingAs($user)->get('/importar')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('imports.0.status', 'failed')
            ->whereType('imports.0.error', 'string'));
    }

    public function test_importacao_que_falhou_pode_ser_enviada_de_novo(): void
    {
        $user = User::factory()->create();
        $path = WhatsAppZip::make()->file('leia-me.txt', 'nada')->path();

        $this->actingAs($user)->post('/importar', ['file' => new UploadedFile($path, 'a.zip', 'application/zip', null, true)]);
        $this->actingAs($user)
            ->post('/importar', ['file' => new UploadedFile($path, 'a.zip', 'application/zip', null, true)])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Import::count());
    }

    public function test_a_leitura_vai_para_a_fila(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())->post('/importar', ['file' => $this->upload(WhatsAppZip::sample())]);

        $this->assertSame(Import::PENDING, Import::sole()->status);
        Queue::assertPushed(ProcessImport::class, fn (ProcessImport $job) => $job->timezone === 'America/Sao_Paulo');
    }

    public function test_cada_empresa_ve_so_as_proprias_importacoes(): void
    {
        $this->actingAs(User::factory()->create())->post('/importar', ['file' => $this->upload(WhatsAppZip::sample())]);

        $this->actingAs(User::factory()->create())
            ->get('/importar')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('imports', 0));
    }

    public function test_auditoria_e_log_nao_levam_nome_de_arquivo_nem_conversa(): void
    {
        $this->actingAs(User::factory()->create())->post('/importar', ['file' => $this->upload(WhatsAppZip::sample(), 'Cliente Teste.zip')]);

        $stored = AuditEntry::where('action', 'like', 'imports.%')->get()->toJson();
        $this->assertStringNotContainsString('Cliente', $stored);
        $this->assertStringNotContainsString('5511988887777', $stored);
    }

    private function upload(WhatsAppZip $zip, string $name = 'conversas.zip'): UploadedFile
    {
        return new UploadedFile($zip->path(), $name, 'application/zip', null, true);
    }
}

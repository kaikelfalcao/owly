<?php

namespace Tests\Feature\Accounts;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Accounts\Jobs\ForOrganization;
use App\Domains\Accounts\Models\Organization;
use App\Domains\Conversations\Models\Contact;
use App\Domains\Imports\Jobs\ProcessImport;
use App\Domains\Imports\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * Isolamento por empresa: consulta sem empresa falha em vez de vazar.
 */
class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_sem_empresa_no_contexto_falha(): void
    {
        $this->contact(Organization::factory()->create());

        $this->expectException(RuntimeException::class);

        Contact::count();
    }

    public function test_logado_so_ve_os_dados_da_propria_empresa(): void
    {
        $user = User::factory()->create();
        $this->contact($user->organization, 'wa:5511900000001');
        $this->contact(Organization::factory()->create(), 'wa:5511900000002');

        $this->actingAs($user);

        $this->assertSame(['wa:5511900000001'], Contact::pluck('external_key')->all());
    }

    public function test_registro_novo_nasce_na_empresa_do_contexto(): void
    {
        $organization = Organization::factory()->create();

        $contact = $this->inOrganization($organization, fn () => Contact::create(['external_key' => 'wa:5511900000003']));

        $this->assertSame($organization->id, $contact->organization_id);
    }

    public function test_registro_de_outra_empresa_dentro_do_contexto_e_recusado(): void
    {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();

        $this->expectException(LogicException::class);

        $this->inOrganization($organization, fn () => Contact::create(['organization_id' => $other->id, 'external_key' => 'wa:5511900000004']));
    }

    public function test_registro_sem_empresa_e_sem_contexto_e_recusado(): void
    {
        $this->expectException(LogicException::class);

        Contact::create(['external_key' => 'wa:5511900000005']);
    }

    public function test_entre_empresas_ve_todas(): void
    {
        $this->contact(Organization::factory()->create(), 'wa:5511900000006');
        $this->contact(Organization::factory()->create(), 'wa:5511900000007');

        $this->assertSame(2, $this->acrossOrganizations(fn () => Contact::count()));
    }

    public function test_run_as_devolve_o_contexto_anterior_mesmo_com_erro(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $current = app(CurrentOrganization::class);

        $current->runAs($first->id, function () use ($current, $first, $second): void {
            try {
                $current->runAs($second->id, fn () => throw new RuntimeException('falhou'));
            } catch (RuntimeException) {
            }

            $this->assertSame($first->id, $current->id());
            $this->assertSame($first->id, Context::get('organization_id'));
        });

        $this->assertFalse($current->has());
    }

    public function test_servico_chamado_com_outra_empresa_e_erro(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(LogicException::class);

        app(CurrentOrganization::class)->ensure(Organization::factory()->create()->id, fn () => null);
    }

    public function test_job_roda_com_a_empresa_dele(): void
    {
        $organization = Organization::factory()->create();

        $seen = (new ForOrganization($organization->id))->handle(new \stdClass, fn () => app(CurrentOrganization::class)->id());

        $this->assertSame($organization->id, $seen);
        $this->assertFalse(app(CurrentOrganization::class)->has());
    }

    public function test_importacao_que_esgota_as_tentativas_falha_sem_contexto_aberto(): void
    {
        $organization = Organization::factory()->create();
        $import = $this->inOrganization($organization, fn () => Import::create([
            'format' => 'whatsapp-xlsx-zip',
            'status' => Import::RUNNING,
            'file_name' => 'conversas.zip',
            'file_size' => 10,
            'file_hash' => str_repeat('a', 64),
        ]));

        $job = new ProcessImport($organization->id, $import->id, 'America/Sao_Paulo');
        $this->assertInstanceOf(ForOrganization::class, $job->middleware()[0]);

        $job->failed(new RuntimeException('inesperado'));

        $this->assertSame(Import::FAILED, $this->inOrganization($organization, fn () => $import->fresh()->status));
    }

    private function contact(Organization $organization, string $key = 'wa:5511900000000'): Contact
    {
        return Contact::create(['organization_id' => $organization->id, 'external_key' => $key]);
    }
}

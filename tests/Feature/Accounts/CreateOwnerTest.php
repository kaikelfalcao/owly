<?php

namespace Tests\Feature\Accounts;

use App\Domains\Accounts\Models\Organization;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_company_and_the_owner(): void
    {
        $this->artisan('owly:owner', [
            'email' => 'dono@grafica.test',
            '--name' => 'Ana Dona',
            '--company' => 'Gráfica Exemplo',
        ])
            ->expectsQuestion('Senha', 'uma-senha-boa')
            ->assertSuccessful();

        $user = User::where('email', 'dono@grafica.test')->sole();
        $this->assertSame('Ana Dona', $user->name);
        $this->assertSame('Gráfica Exemplo', $user->organization->name);
        $this->assertTrue(Hash::check('uma-senha-boa', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $entry = AuditEntry::where('action', 'accounts.owner_created')->sole();
        $this->assertSame($user->organization_id, $entry->organization_id);
        $this->assertSame('console', $entry->channel);
        $this->assertNull($entry->ip);
    }

    public function test_an_existing_email_is_refused(): void
    {
        User::factory()->create(['email' => 'dono@grafica.test']);

        $this->artisan('owly:owner', [
            'email' => 'dono@grafica.test',
            '--name' => 'Outra Pessoa',
            '--company' => 'Outra Empresa',
        ])
            ->expectsQuestion('Senha', 'uma-senha-boa')
            ->assertFailed();

        $this->assertSame(1, Organization::count());
    }

    public function test_the_new_owner_can_log_in(): void
    {
        $this->artisan('owly:owner', [
            'email' => 'dono@grafica.test',
            '--name' => 'Ana Dona',
            '--company' => 'Gráfica Exemplo',
        ])->expectsQuestion('Senha', 'uma-senha-boa');

        $this->post('/login', ['email' => 'dono@grafica.test', 'password' => 'uma-senha-boa'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }
}

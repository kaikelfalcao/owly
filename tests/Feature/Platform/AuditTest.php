<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use LogicException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_in_is_audited(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $entry = AuditEntry::where('action', 'auth.login')->sole();
        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame('127.0.0.1', $entry->ip);
        $this->assertNotNull($entry->request_id);
    }

    public function test_a_failed_login_never_stores_the_typed_email(): void
    {
        $this->post('/login', ['email' => 'ninguem@exemplo.test', 'password' => 'errada']);

        $entry = AuditEntry::where('action', 'auth.failed')->sole();
        $this->assertNull($entry->user_id);
        $this->assertSame(['known_user' => false], $entry->meta);
        $this->assertStringNotContainsString('ninguem', json_encode($entry->getAttributes()));
    }

    public function test_a_wrong_password_for_a_known_user_keeps_the_login_as_the_resource_not_as_who_did_it(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'errada']);

        $entry = AuditEntry::where('action', 'auth.failed')->sole();
        $this->assertNull($entry->user_id);
        $this->assertSame($user->getMorphClass(), $entry->subject_type);
        $this->assertSame($user->id, $entry->subject_id);
        $this->assertSame($user->organization_id, $entry->organization_id);
    }

    public function test_actions_in_the_browser_keep_the_channel_and_the_browser(): void
    {
        $user = User::factory()->create();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Teste) Navegador/1.0')
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $entry = AuditEntry::where('action', 'auth.login')->sole();
        $this->assertSame('web', $entry->channel);
        $this->assertSame('Mozilla/5.0 (Teste) Navegador/1.0', $entry->user_agent);
        $this->assertSame($user->id, $entry->subject_id);
    }

    public function test_actions_outside_the_browser_have_no_ip_nor_browser(): void
    {
        $entry = app(Audit::class)->record('test.created');

        $this->assertSame('console', $entry->channel);
        $this->assertNull($entry->ip);
        $this->assertNull($entry->user_agent);
    }

    public function test_actions_in_a_queue_job_are_marked_as_such(): void
    {
        Context::add('job', 'ProcessImport');

        $entry = app(Audit::class)->record('test.created');

        $this->assertSame('queue', $entry->channel);
        $this->assertNull($entry->ip);
    }

    public function test_changing_the_email_keeps_that_it_changed_but_not_the_value(): void
    {
        $user = User::factory()->create(['email' => 'antigo@exemplo.test']);

        $this->actingAs($user)->patch('/conta/perfil', ['name' => $user->name, 'email' => 'novo@exemplo.test']);

        $entry = AuditEntry::where('action', 'accounts.email_changed')->sole();
        $this->assertSame(['email' => ['hidden' => true]], $entry->changes);
        $this->assertStringNotContainsString('exemplo.test', json_encode($entry->getAttributes()));
        $this->assertDatabaseMissing('audit_entries', ['action' => 'accounts.name_changed']);
    }

    public function test_changing_the_name_is_audited_without_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Nome Antigo']);

        $this->actingAs($user)->patch('/conta/perfil', ['name' => 'Nome Novo', 'email' => $user->email]);

        $entry = AuditEntry::where('action', 'accounts.name_changed')->sole();
        $this->assertSame(['name' => ['hidden' => true]], $entry->changes);
        $this->assertStringNotContainsString('Nome', json_encode($entry->getAttributes()));
    }

    public function test_changes_keep_codes_before_and_after(): void
    {
        $entry = app(Audit::class)->record('test.changed', changes: ['provider' => ['gemini', 'claude'], 'active' => [false, true]]);

        $this->assertSame([
            'provider' => ['from' => 'gemini', 'to' => 'claude'],
            'active' => ['from' => false, 'to' => true],
        ], $entry->fresh()->changes);
    }

    public function test_free_text_in_changes_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Audit::class)->record('test.changed', changes: ['name' => ['Maria Silva', 'Ana Souza']]);
    }

    public function test_entries_cannot_be_changed_or_deleted_in_bulk(): void
    {
        app(Audit::class)->record('test.created');

        $this->assertThrows(fn () => AuditEntry::query()->update(['action' => 'test.changed']), LogicException::class);
        $this->assertThrows(fn () => AuditEntry::query()->delete(), LogicException::class);
        $this->assertThrows(fn () => AuditEntry::query()->truncate(), LogicException::class);
        $this->assertSame(1, AuditEntry::count());
    }

    public function test_changing_the_password_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put('/conta/senha', [
                'current_password' => 'password',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ]);

        $this->assertDatabaseHas('audit_entries', ['action' => 'auth.password_changed', 'user_id' => $user->id]);
    }

    public function test_turning_off_two_factor_is_audited(): void
    {
        $user = User::factory()->create();

        event(new TwoFactorAuthenticationDisabled($user));

        $this->assertDatabaseHas('audit_entries', ['action' => 'auth.two_factor_disabled', 'user_id' => $user->id]);
    }

    public function test_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = app(Audit::class)->record('test.created');

        $this->assertThrows(fn () => $entry->update(['action' => 'test.changed']), LogicException::class);
        $this->assertThrows(fn () => $entry->delete(), LogicException::class);
    }

    public function test_free_text_in_meta_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Audit::class)->record('test.created', meta: ['cliente' => 'maria@exemplo.test']);
    }

    public function test_names_with_capitals_or_spaces_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Audit::class)->record('test.created', meta: ['cliente' => 'Maria Silva']);
    }

    public function test_codes_and_numbers_are_accepted(): void
    {
        $entry = app(Audit::class)->record('test.created', meta: [
            'provider' => 'gemini',
            'files' => 12,
            'ok' => true,
        ]);

        $this->assertSame(['provider' => 'gemini', 'files' => 12, 'ok' => true], $entry->fresh()->meta);
    }

    public function test_actions_must_follow_area_dot_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Audit::class)->record('Login');
    }
}

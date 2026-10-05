<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_a_wrong_password_for_a_known_user_keeps_the_user_id(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'errada']);

        $this->assertSame($user->id, AuditEntry::where('action', 'auth.failed')->sole()->user_id);
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

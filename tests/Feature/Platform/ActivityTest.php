<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_sees_the_company_activity_in_portuguese(): void
    {
        $user = User::factory()->create();
        AuditEntry::create(['organization_id' => $user->organization_id, 'user_id' => $user->id, 'action' => 'auth.password_changed']);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->component('settings/activity')
            ->has('entries.data', 1)
            ->where('entries.data.0.label', 'Trocou a senha')
        );
    }

    public function test_another_company_s_activity_is_never_shown(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        AuditEntry::create(['organization_id' => $other->organization_id, 'user_id' => $other->id, 'action' => 'auth.login']);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0)
        );
    }

    public function test_a_failed_login_is_flagged(): void
    {
        $user = User::factory()->create();
        AuditEntry::create(['organization_id' => $user->organization_id, 'action' => 'auth.failed']);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.tone', 'warning')
        );
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/conta/atividade')->assertRedirect('/login');
    }
}

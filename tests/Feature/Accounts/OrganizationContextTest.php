<?php

namespace Tests\Feature\Accounts;

use App\Domains\Accounts\CurrentOrganization;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class OrganizationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_audit_carries_the_company(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertSame($user->organization_id, AuditEntry::where('action', 'auth.login')->sole()->organization_id);
    }

    public function test_the_current_company_is_the_logged_in_owners(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertSame($user->organization_id, app(CurrentOrganization::class)->id());
    }

    public function test_there_is_no_current_company_without_login(): void
    {
        $this->expectException(RuntimeException::class);

        app(CurrentOrganization::class)->id();
    }

    public function test_pages_share_only_what_the_top_bar_needs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret')
            ->where('auth.organization.name', $user->organization->name)
        );
    }
}

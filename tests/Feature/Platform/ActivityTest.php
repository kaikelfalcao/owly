<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 05/10/2026 15:00 em São Paulo.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 18:00:00', 'UTC'));
    }

    public function test_the_owner_sees_the_company_activity_in_portuguese(): void
    {
        $user = User::factory()->create(['name' => 'Ana Dona']);
        $this->entry($user, 'auth.password_changed', ['subject_type' => $user->getMorphClass(), 'subject_id' => $user->id]);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->component('settings/activity')
            ->has('entries.data', 1)
            ->where('entries.data.0.label', 'Trocou a senha')
            ->where('entries.data.0.user.name', 'Ana Dona')
            ->where('entries.data.0.resource.label', 'Dono')
            ->where('entries.data.0.resource.name', 'Ana Dona')
            ->where('entries.data.0.severity', 'important')
            ->where('entries.data.0.result', 'success')
            ->where('timezone', 'America/Sao_Paulo')
        );
    }

    public function test_another_company_s_activity_is_never_shown(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Pessoa de Fora']);
        $this->entry($other, 'auth.login');

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0)
            ->has('options.users', 1)
            ->where('options.users.0.value', (string) $user->id)
        );
    }

    public function test_searching_another_company_s_user_finds_nothing(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Pessoa de Fora']);
        $this->entry($user, 'auth.login', ['user_id' => $other->id]);

        $this->actingAs($user)->get('/conta/atividade?q=Fora')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0)
        );
    }

    public function test_a_failed_login_is_flagged_as_a_failure_with_no_one_behind_it(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 'auth.failed', ['user_id' => null, 'meta' => ['known_user' => true]]);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.result', 'failure')
            ->where('entries.data.0.severity', 'important')
            ->where('entries.data.0.user', null)
            ->where('entries.data.0.meta.0.label', 'E-mail cadastrado na Owly')
            ->where('entries.data.0.meta.0.value', true)
        );
    }

    public function test_the_details_show_what_changed_without_personal_data(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 'accounts.email_changed', [
            'changes' => ['email' => ['hidden' => true]],
            'channel' => 'web',
            'user_agent' => 'Mozilla/5.0 (Teste)',
            'request_id' => 'pedido-123',
        ]);

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.severity', 'critical')
            ->where('entries.data.0.changes.0.label', 'E-mail de entrada')
            ->where('entries.data.0.changes.0.hidden', true)
            ->where('entries.data.0.channel.label', 'Navegador')
            ->where('entries.data.0.user_agent', 'Mozilla/5.0 (Teste)')
            ->where('entries.data.0.request_id', 'pedido-123')
        );
    }

    public function test_the_period_is_counted_in_the_company_timezone(): void
    {
        $user = User::factory()->create();
        // 02:30 UTC ainda é ontem (23:30) em São Paulo; 03:30 UTC já é hoje.
        $this->entry($user, 'auth.login', ['created_at' => '2026-10-05 03:30:00']);
        $this->entry($user, 'auth.logout', ['created_at' => '2026-10-05 02:30:00']);
        $this->entry($user, 'auth.password_changed', ['created_at' => '2026-09-20 12:00:00']);

        $this->actingAs($user)->get('/conta/atividade?period=today')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'auth.login')
        );

        $this->actingAs($user)->get('/conta/atividade?period=yesterday')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'auth.logout')
        );

        $this->actingAs($user)->get('/conta/atividade?period=7d')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 2)
        );

        $this->actingAs($user)->get('/conta/atividade?period=custom&from=2026-09-20&to=2026-09-20')
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.action', 'auth.password_changed')
                ->where('filters.period', 'custom')
            );
    }

    public function test_filters_work_together(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 'auth.login');
        $this->entry($user, 'auth.failed', ['user_id' => null]);
        $this->entry($user, 'auth.two_factor_disabled');
        $this->entry($user, 'auth.two_factor_failed', ['user_id' => null]);

        $this->actingAs($user)->get('/conta/atividade?result=failure')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 2)
        );

        $this->actingAs($user)->get('/conta/atividade?severity=critical')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'auth.two_factor_disabled')
        );

        $this->actingAs($user)->get('/conta/atividade?severity=normal')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'auth.login')
        );

        $this->actingAs($user)->get('/conta/atividade?user=none&action=auth.failed')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
        );

        $this->actingAs($user)->get("/conta/atividade?user={$user->id}&result=failure")->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 0)
        );
    }

    public function test_one_search_looks_at_name_action_resource_ip_and_request_id(): void
    {
        $user = User::factory()->create(['name' => 'Ana Dona']);
        $this->entry($user, 'auth.login', ['ip' => '200.10.20.30']);
        $this->entry($user, 'auth.password_changed', ['request_id' => 'abc-123-def']);
        $this->entry($user, 'auth.failed', ['user_id' => null, 'subject_type' => $user->getMorphClass(), 'subject_id' => $user->id]);

        $search = fn (string $q) => $this->actingAs($user)->get('/conta/atividade?q='.urlencode($q));

        $search('200.10')->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.action', 'auth.login'));
        $search('abc-123')->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.action', 'auth.password_changed'));
        $search('senha')->assertInertia(fn (Assert $page) => $page->has('entries.data', 2));
        $search('ana')->assertInertia(fn (Assert $page) => $page->has('entries.data', 3));
        $search("#{$user->id}")->assertInertia(fn (Assert $page) => $page->has('entries.data', 1)->where('entries.data.0.action', 'auth.failed'));
        $search('%')->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));
    }

    public function test_the_activity_of_one_resource_can_be_opened_alone(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 'auth.login', ['subject_type' => $user->getMorphClass(), 'subject_id' => $user->id]);
        $this->entry($user, 'auth.login', ['subject_type' => $user->getMorphClass(), 'subject_id' => $user->id + 100]);
        $this->entry($user, 'audit.exported');

        $this->actingAs($user)->get("/conta/atividade?resource=user&resource_id={$user->id}")->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.resource.id', $user->id)
        );
    }

    public function test_the_list_is_paginated_newest_first(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 30) as $minutes) {
            $this->entry($user, 'auth.login', ['created_at' => now()->subMinutes($minutes)]);
        }

        $this->actingAs($user)->get('/conta/atividade')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 25)
            ->where('entries.total', 30)
            ->where('entries.data.0.at', now()->subMinute()->toIso8601String())
        );

        $this->actingAs($user)->get('/conta/atividade?page=2&period=today')->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 5)
            ->where('entries.prev_page_url', fn (string $url) => str_contains($url, 'period=today'))
        );
    }

    public function test_an_invalid_filter_goes_back_to_the_clean_list_with_the_reason(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/conta/atividade?period=custom&from=2026-10-05&to=2026-10-01')
            ->assertRedirect('/conta/atividade')
            ->assertSessionHasErrors(['to' => 'O último dia precisa ser igual ou depois do primeiro.']);
    }

    public function test_the_export_brings_every_filtered_row_and_is_audited(): void
    {
        $user = User::factory()->create(['name' => '=Ana Dona']);
        $other = User::factory()->create();
        $this->entry($user, 'auth.login', ['ip' => '200.10.20.30']);
        $this->entry($user, 'auth.failed', ['user_id' => null]);
        $this->entry($other, 'auth.login', ['ip' => '99.99.99.99']);

        $response = $this->actingAs($user)->get('/conta/atividade/exportar?action=auth.login');

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Entrou na Owly', $csv);
        $this->assertStringContainsString('200.10.20.30', $csv);
        $this->assertStringContainsString("'=Ana Dona", $csv);
        $this->assertStringNotContainsString('99.99.99.99', $csv);
        $this->assertStringNotContainsString('senha errada', $csv);

        $audit = AuditEntry::where('action', 'audit.exported')->sole();
        $this->assertSame($user->organization_id, $audit->organization_id);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame(['rows' => 1, 'period' => 'all', 'filtered' => true], $audit->meta);
    }

    public function test_the_export_never_keeps_the_searched_text(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/conta/atividade/exportar?q=maria')->assertOk();

        $this->assertStringNotContainsString('maria', json_encode(AuditEntry::where('action', 'audit.exported')->sole()->getAttributes()));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/conta/atividade')->assertRedirect('/login');
        $this->get('/conta/atividade/exportar')->assertRedirect('/login');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(User $user, string $action, array $attributes = []): AuditEntry
    {
        return AuditEntry::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'action' => $action,
            'created_at' => now(),
            ...$attributes,
        ]);
    }
}

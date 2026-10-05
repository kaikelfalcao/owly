<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/conta/horario')->assertRedirect('/login');
        $this->put('/conta/horario')->assertRedirect('/login');
    }

    public function test_a_tela_mostra_o_horario_padrao(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/conta/horario')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/business-hours')
                ->where('hours.mon', ['08:00', '18:00'])
                ->where('hours.sat', ['08:00', '12:00'])
                ->where('hours.sun', null)
                ->where('nationalHolidays', true)
                ->where('holidays', []));
    }

    public function test_salva_horario_e_feriados_e_audita_sem_o_nome_do_feriado(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/conta/horario')
            ->put('/conta/horario', [
                'hours' => $this->hours(['sat' => null, 'fri' => ['09:00', '17:30']]),
                'national_holidays' => false,
                'holidays' => [
                    ['date' => '2026-11-30', 'name' => ' Aniversário da cidade '],
                    ['date' => '2026-02-17', 'name' => 'Carnaval'],
                ],
            ])
            ->assertRedirect('/conta/horario')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Horário salvo. O painel já conta por ele.');

        $organization = $user->organization->fresh();
        $this->assertNull($organization->business_hours['sat']);
        $this->assertSame(['09:00', '17:30'], $organization->business_hours['fri']);
        $this->assertFalse($organization->national_holidays);
        $this->assertSame([
            ['date' => '2026-02-17', 'name' => 'Carnaval'],
            ['date' => '2026-11-30', 'name' => 'Aniversário da cidade'],
        ], $organization->holidays);

        $entry = AuditEntry::where('action', 'accounts.business_hours_changed')->sole();
        $this->assertSame(['from' => '08:00-12:00', 'to' => 'closed'], $entry->changes['sat']);
        $this->assertSame(['from' => '08:00-18:00', 'to' => '09:00-17:30'], $entry->changes['fri']);
        $this->assertSame(['from' => 0, 'to' => 2], $entry->changes['holidays']);
        $this->assertSame(['from' => true, 'to' => false], $entry->changes['national_holidays']);
        $this->assertArrayNotHasKey('mon', $entry->changes);
        $this->assertStringNotContainsString('Carnaval', json_encode($entry->toArray()));
    }

    public function test_salvar_sem_mudar_nada_nao_audita(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/conta/horario', ['hours' => $this->hours(), 'national_holidays' => true, 'holidays' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditEntry::where('action', 'accounts.business_hours_changed')->count());
    }

    public function test_recusa_horario_e_feriado_invalidos(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/conta/horario', [
                'hours' => $this->hours(['mon' => ['18:00', '08:00'], 'tue' => ['8h', '18:00']]),
                'national_holidays' => true,
                'holidays' => [
                    ['date' => '2026-12-24', 'name' => ''],
                    ['date' => '2026-12-24', 'name' => 'Véspera'],
                ],
            ])
            ->assertSessionHasErrors([
                'hours.tue.0' => 'Use o formato 08:00.',
                'holidays.0.name' => 'Dê um nome para o feriado.',
                'holidays.0.date' => 'Essa data já está na lista.',
            ]);

        $this->put('/conta/horario', [
            'hours' => $this->hours(['mon' => ['18:00', '08:00']]),
            'national_holidays' => true,
            'holidays' => [],
        ])->assertSessionHasErrors(['hours.mon' => 'O fechamento precisa ser depois da abertura.']);
    }

    /**
     * @param  array<string, array{0: string, 1: string}|null>  $override
     * @return array<string, array{0: string, 1: string}|null>
     */
    private function hours(array $override = []): array
    {
        return $override + [
            'mon' => ['08:00', '18:00'],
            'tue' => ['08:00', '18:00'],
            'wed' => ['08:00', '18:00'],
            'thu' => ['08:00', '18:00'],
            'fri' => ['08:00', '18:00'],
            'sat' => ['08:00', '12:00'],
            'sun' => null,
        ];
    }
}

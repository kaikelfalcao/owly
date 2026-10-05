<?php

namespace Tests\Feature\Insights;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WhatsAppZip;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/dashboard/sem-resposta')->assertRedirect('/login');
    }

    public function test_sem_conversas_o_painel_convida_a_importar(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('hasData', false)
                ->where('unanswered', 0));
    }

    public function test_o_painel_le_as_conversas_importadas(): void
    {
        $user = $this->importing(WhatsAppZip::sample());

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('dashboard')
                ->where('hasData', true)
                ->where('period.key', '30')
                ->where('totals.conversations', 2)
                ->where('unanswered', 1)
                ->where('response.medianSeconds', 1050)
                ->where('response.answered', 2)
                ->where('outOfHours.turns', 1)
                ->where('quotes.sent', 2)
                ->where('quotes.stalled', 1)
                ->where('sales', 1)
                ->where('topics.0.key', 'cartao')
                ->where('sellers.0.name', 'Ana')
                ->where('sellers.0.medianSeconds', 300)
                ->where('hoursConfigured', false));
    }

    public function test_mensagem_da_sexta_a_noite_conta_so_o_expediente(): void
    {
        // 2026-09-04 é sexta e 2026-09-07 é feriado (Independência): a resposta vem na terça.
        $user = $this->importing(WhatsAppZip::make()->sheet('5511955554444.xlsx', [
            ['2026-09-04', '19:00:00', '5511955554444', 'Cliente Sexta', 'Oi, vocês fazem convite?'],
            ['2026-09-08', '08:05:00', WhatsAppZip::COMPANY, '', "*Ana:*\nBom dia! Fazemos sim."],
        ]));

        // No horário padrão o sábado abre das 8h às 12h: 4 h + 5 min.
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('response.medianSeconds', 4 * 3600 + 300)
                ->where('outOfHours.turns', 1));

        $hours = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri'], ['08:00', '18:00']) + ['sat' => null, 'sun' => null];
        $this->put('/conta/horario', ['hours' => $hours, 'national_holidays' => true, 'holidays' => []])
            ->assertSessionHasNoErrors();

        // Sábado fechado: só os 5 minutos da terça.
        $this->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('response.medianSeconds', 300)
                ->where('response.withinHourShare', 1)
                ->where('hoursConfigured', true));
    }

    public function test_a_lista_mostra_as_conversas_por_tras_do_numero(): void
    {
        $user = $this->importing(WhatsAppZip::sample());

        $this->actingAs($user)
            ->get('/dashboard/tempo-de-resposta?periodo=tudo')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('insights/show')
                ->where('period.key', 'tudo')
                ->where('total', 2)
                ->where('conversations.0.contact', '⭐ Maria Inventada')
                ->where('conversations.0.seconds', 1800)
                ->where('conversations.0.seller', 'Bia'));

        $this->get('/dashboard/procuram?filtro=banner')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('title', 'Procuram: Banner e lona')
                ->where('total', 1));

        $this->get('/dashboard/vendedora?filtro=Ana')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('total', 1)
                ->where('conversations.0.contact', 'Cliente Teste'));
    }

    public function test_leitura_tema_ou_vendedora_desconhecidos_dao_404(): void
    {
        $user = $this->importing(WhatsAppZip::sample());

        $this->actingAs($user)->get('/dashboard/nao-existe')->assertNotFound();
        $this->get('/dashboard/procuram?filtro=foguete')->assertNotFound();
        $this->get('/dashboard/vendedora?filtro=Zeca')->assertNotFound();
    }

    public function test_a_outra_empresa_nao_ve_as_conversas(): void
    {
        $this->importing(WhatsAppZip::sample());

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('hasData', false));

        $this->get('/dashboard/vendedora?filtro=Ana')->assertNotFound();
        $this->get('/dashboard/sem-resposta')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('total', 0));
    }

    private function importing(WhatsAppZip $zip): User
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/importar', ['file' => new UploadedFile($zip->path(), 'conversas.zip', 'application/zip', null, true)])
            ->assertSessionHasNoErrors();

        return $user;
    }
}

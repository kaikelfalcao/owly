<?php

namespace Tests\Feature\Insights;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;
use App\Domains\Imports\Events\ImportFinished;
use App\Domains\Insights\Models\Opportunity;
use App\Models\User;
use App\Platform\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WhatsAppZip;
use Tests\TestCase;

/**
 * Oportunidades guardadas: nascem da importação, o dono decide, e a decisão
 * sobrevive a importações novas e ao owly:recut (I9). No zip de exemplo, o
 * Cliente Teste recebe "R$ 90" no dia 1 e fecha no dia 4, já em outro
 * atendimento; a Maria recebe a tabela em PDF e não responde.
 */
class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_importar_guarda_as_oportunidades_e_reimportar_nao_duplica(): void
    {
        $user = $this->withImport();

        [$won, $open] = $this->opportunities($user);
        $client = Conversation::whereHas('contact', fn ($q) => $q->where('phone', '5511988887777'))->orderBy('first_message_at')->get();

        $this->assertSame(Opportunity::WON, $won->status);
        $this->assertSame($client[0]->id, $won->conversation_id);
        $this->assertSame($client[1]->id, Message::find($won->closing_message_id)->conversation_id);
        $this->assertSame('Ana', $won->seller_id ? Message::find($won->anchor_message_id)->seller->name : null);
        $this->assertSame(Opportunity::OPEN, $open->status);
        $this->assertSame(Opportunity::RULE, $open->source);

        // Outro zip com as mesmas conversas: nada novo.
        $this->import($user, WhatsAppZip::sample()->sheet('extra.xlsx', []));

        $this->assertSame(2, $this->inOrganization($user->organization_id, fn () => Opportunity::count()));
    }

    public function test_a_importacao_avisa_quando_termina(): void
    {
        Event::fake([ImportFinished::class]);
        $user = $this->withImport();

        Event::assertDispatched(ImportFinished::class, fn (ImportFinished $e) => $e->organizationId === $user->organization_id);
        $this->assertSame(0, $this->inOrganization($user->organization_id, fn () => Opportunity::count()));

        // Quem perdeu o aviso (importações antigas) recalcula pelo comando.
        $this->artisan('owly:insights', ['--empresa' => $user->organization_id])->assertSuccessful();
        $this->artisan('owly:insights', ['--empresa' => $user->organization_id])->assertSuccessful();

        $this->assertSame(2, $this->inOrganization($user->organization_id, fn () => Opportunity::count()));
    }

    public function test_o_atendimento_mostra_onde_abriu_e_onde_fechou(): void
    {
        $user = $this->withImport();
        [$won, $open] = $this->opportunities($user);
        [$first, $second] = Conversation::whereHas('contact', fn ($q) => $q->where('phone', '5511988887777'))->orderBy('first_message_at')->get()->all();

        $this->actingAs($user)->get("/conversas/{$first->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('panels.opportunities.opportunities', 1)
                ->where('panels.opportunities.opportunities.0.id', $won->id)
                ->where('panels.opportunities.opportunities.0.anchorConversationId', $first->id)
                ->where('panels.opportunities.opportunities.0.closingConversationId', $second->id)
                ->where('panels.opportunities.opportunities.0.bornWon', false)
                ->has('panels.opportunities.reasons', 6));

        $this->actingAs($user)->get("/conversas/{$second->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('panels.opportunities.opportunities.0.id', $won->id)
                ->where('panels.opportunities.opportunities.0.status', 'won'));

        $this->actingAs($user)->get("/conversas/{$open->conversation_id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('panels.opportunities.opportunities.0.id', $open->id)
                ->where('panels.opportunities.opportunities.0.status', 'open')
                ->where('panels.opportunities.opportunities.0.decided', false));
    }

    public function test_o_dono_marca_ganhou_perdeu_descartou_e_desfaz(): void
    {
        $user = $this->withImport();
        [, $open] = $this->opportunities($user);

        $this->actingAs($user)->post("/oportunidades/{$open->id}/perdeu", [])->assertSessionHasErrors(['reason' => 'Escolha o motivo da perda.']);
        $this->actingAs($user)->post("/oportunidades/{$open->id}/perdeu", ['reason' => 'caro'])->assertSessionHasErrors('reason');

        $this->actingAs($user)->post("/oportunidades/{$open->id}/perdeu", ['reason' => 'price'])->assertSessionHasNoErrors();
        $open->refresh();
        $this->assertSame([Opportunity::LOST, 'price', $user->id], [$open->status, $open->loss_reason, $open->decided_by]);
        $this->assertNotNull($open->decided_at);
        $this->assertNotNull($open->closed_at);

        // Decidida não muda de novo sem desfazer.
        $this->actingAs($user)->post("/oportunidades/{$open->id}/ganhou")->assertSessionHasErrors('opportunity');

        $this->actingAs($user)->post("/oportunidades/{$open->id}/reabrir")->assertSessionHasNoErrors();
        $open->refresh();
        $this->assertSame([Opportunity::OPEN, null, null], [$open->status, $open->loss_reason, $open->closed_at]);
        $this->assertNotNull($open->decided_at, 'reaberta continua travada: quem fecha agora é o dono');

        $this->actingAs($user)->post("/oportunidades/{$open->id}/descartar")->assertSessionHasNoErrors();
        $this->assertSame(Opportunity::DISCARDED, $open->refresh()->status);
        $this->assertNull($open->loss_reason);

        $this->actingAs($user)->post("/oportunidades/{$open->id}/reabrir");
        $this->actingAs($user)->post("/oportunidades/{$open->id}/ganhou")->assertSessionHasNoErrors();
        $this->assertSame(Opportunity::WON, $open->refresh()->status);
        $this->assertNull($open->closing_message_id);

        $actions = AuditEntry::where('subject_id', $open->id)->orderBy('id')->get();
        $this->assertSame(
            ['insights.opportunity_lost', 'insights.opportunity_reopened', 'insights.opportunity_discarded', 'insights.opportunity_reopened', 'insights.opportunity_won'],
            $actions->pluck('action')->all(),
        );
        $this->assertSame(['reason' => 'price'], $actions[0]->meta);
        $this->assertSame(['status' => ['from' => 'open', 'to' => 'lost']], $actions[0]->changes);
    }

    public function test_decisao_do_dono_sobrevive_a_importacao_e_ao_recut(): void
    {
        $user = $this->withImport();
        [$won, $open] = $this->opportunities($user);

        $this->actingAs($user)->post("/oportunidades/{$open->id}/perdeu", ['reason' => 'deadline']);
        $this->actingAs($user)->post("/oportunidades/{$won->id}/reabrir");
        $this->actingAs($user)->post("/oportunidades/{$won->id}/descartar")->assertSessionHasNoErrors();

        // A Maria recebe outro preço e diz que fecha; o Cliente Teste pede outra coisa.
        $this->import($user, (new WhatsAppZip)
            ->sheet('#U2b50 Maria Inventada.xlsx', [
                ['2026-09-02', '10:00:00', '5511977776666', '', 'Vocês fazem banner?'],
                ['2026-09-02', '10:40:00', WhatsAppZip::COMPANY, '', "*Bia:*\nCom desconto, R$ 120"],
                ['2026-09-02', '10:45:00', '5511977776666', '', 'Fechado, pode fazer'],
            ]));

        $this->artisan('owly:recut', ['--empresa' => $user->organization_id])->assertSuccessful();
        $this->artisan('owly:insights', ['--empresa' => $user->organization_id])->assertSuccessful();

        [$wonAfter, $openAfter] = [$won->fresh(), $open->fresh()];
        $this->assertSame([Opportunity::DISCARDED, Opportunity::LOST, 'deadline'], [$wonAfter->status, $openAfter->status, $openAfter->loss_reason]);
        // O preço e a venda da Maria antes da decisão entram na perdida. A
        // descartada só segura o atendimento dela: o "pode fazer" do Cliente
        // Teste no atendimento seguinte vira uma venda.
        $this->inOrganization($user->organization_id, function () use ($open, $won) {
            $this->assertSame(1, Opportunity::where('contact_id', $open->contact_id)->count());
            $this->assertSame([Opportunity::DISCARDED, Opportunity::WON], Opportunity::where('contact_id', $won->contact_id)->orderBy('opened_at')->pluck('status')->all());
        });
    }

    public function test_oportunidade_da_regra_que_deixou_de_ser_esperada_sai(): void
    {
        $user = $this->withImport();
        $greeting = Message::where('body', 'Vocês fazem banner?')->firstOrFail();

        $stale = $this->inOrganization($user->organization_id, fn () => Opportunity::create([
            'organization_id' => $user->organization_id,
            'contact_id' => $greeting->contact_id,
            'conversation_id' => $greeting->conversation_id,
            'anchor_message_id' => $greeting->id,
            'status' => Opportunity::OPEN,
            'opened_at' => $greeting->sent_at,
            'source' => Opportunity::RULE,
        ]));

        $this->artisan('owly:insights', ['--empresa' => $user->organization_id])->assertSuccessful();

        $this->assertNull($stale->fresh());
        $this->assertSame(2, $this->inOrganization($user->organization_id, fn () => Opportunity::count()));
    }

    public function test_descartada_fica_fora_das_contas(): void
    {
        $user = $this->withImport();
        [, $open] = $this->opportunities($user);

        $countable = fn () => $this->inOrganization($user->organization_id, fn () => Opportunity::countable()->count());
        $this->assertSame(2, $countable());

        $this->actingAs($user)->post("/oportunidades/{$open->id}/descartar");
        $this->assertSame(1, $countable());

        $this->actingAs($user)->post("/oportunidades/{$open->id}/reabrir");
        $this->actingAs($user)->post("/oportunidades/{$open->id}/perdeu", ['reason' => 'other']);
        $this->assertSame(2, $countable(), 'perdida conta; só a descartada sai');
    }

    public function test_visitante_e_outra_empresa_nao_decidem(): void
    {
        $user = $this->withImport();
        [, $open] = $this->opportunities($user);

        $this->app['auth']->forgetGuards();
        $this->post("/oportunidades/{$open->id}/ganhou")->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->post("/oportunidades/{$open->id}/ganhou")
            ->assertNotFound();

        $this->assertSame(Opportunity::OPEN, $open->fresh()->status);
    }

    /**
     * @return array{0: Opportunity, 1: Opportunity} a ganha do Cliente Teste e a aberta da Maria
     */
    private function opportunities(User $user): array
    {
        return $this->inOrganization($user->organization_id, fn () => [
            Opportunity::where('status', Opportunity::WON)->sole(),
            Opportunity::where('status', Opportunity::OPEN)->sole(),
        ]);
    }

    private function withImport(): User
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->import($user, WhatsAppZip::sample());

        return $user;
    }

    private function import(User $user, WhatsAppZip $zip): void
    {
        $this->actingAs($user)->post('/importar', [
            'file' => new UploadedFile($zip->path(), 'conversas.zip', 'application/zip', null, true),
        ])->assertSessionHasNoErrors();
    }
}

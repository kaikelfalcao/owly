<?php

namespace Tests\Unit\Insights;

use App\Domains\Conversations\Data\MessageFact;
use App\Domains\Insights\Data\DecidedOpportunity;
use App\Domains\Insights\Data\ExpectedOpportunity;
use App\Domains\Insights\Rules\OpportunityRule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OpportunityRuleTest extends TestCase
{
    public function test_orcamento_abre_uma_oportunidade_de_quem_mandou(): void
    {
        $expected = $this->expected([
            $this->client(1, '01 09:00', 'Quanto custa?'),
            $this->quote(2, '01 09:05', 'Custa R$ 90', seller: 7),
        ]);

        $this->assertSame([[2, 'open', null, 7, 10]], $this->rows($expected));
    }

    public function test_documento_tambem_e_orcamento_mas_resposta_automatica_nao(): void
    {
        $expected = $this->expected([
            new MessageFact(1, $this->at('01 09:00'), 'out', 'bot', body: 'Tabela: R$ 10', conversationId: 10),
            new MessageFact(2, $this->at('01 09:05'), 'out', 'seller', 'Ana', 'tabela.pdf', 'document', conversationId: 10, sellerId: 7),
        ]);

        $this->assertSame([[2, 'open', null, 7, 10]], $this->rows($expected));
    }

    public function test_reenvio_e_ajuste_de_preco_entram_na_aberta(): void
    {
        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90'),
            $this->client(2, '01 09:10', 'E com 1000?'),
            $this->quote(3, '01 09:20', 'R$ 150'),
        ]);

        $this->assertSame([[1, 'open', null, 7, 10]], $this->rows($expected));
    }

    public function test_venda_fecha_a_aberta_mesmo_em_outro_atendimento(): void
    {
        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90', conversation: 10),
            $this->client(2, '04 20:00', 'Fechado, pode fazer', conversation: 11),
        ]);

        $this->assertSame([[1, 'won', 2, 7, 10]], $this->rows($expected));
        $this->assertEquals($this->at('04 20:00'), $expected[0]->closedAt);
    }

    public function test_venda_sem_orcamento_antes_nasce_ganha_uma_vez_por_atendimento(): void
    {
        $expected = $this->expected([
            $this->client(1, '01 09:00', 'Segue o comprovante', conversation: 10),
            $this->client(2, '01 09:01', 'Pode fazer', conversation: 10),
            $this->client(3, '08 09:00', 'Paguei o outro', conversation: 11),
        ]);

        $this->assertSame([[1, 'won', 1, null, 10], [3, 'won', 3, null, 11]], $this->rows($expected));
    }

    public function test_venda_depois_de_ganha_no_mesmo_atendimento_nao_abre_outra(): void
    {
        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90'),
            $this->client(2, '01 09:10', 'Fechado'),
            $this->client(3, '01 09:30', 'Segue o comprovante'),
            $this->quote(4, '02 09:00', 'R$ 40 o frete'),
        ]);

        // O orçamento depois da venda abre outra: a primeira já fechou.
        $this->assertSame([[1, 'won', 2, 7, 10], [4, 'open', null, 7, 10]], $this->rows($expected));
    }

    public function test_perdida_pelo_dono_segura_os_orcamentos_ate_a_decisao(): void
    {
        $decided = [1 => new DecidedOpportunity(1, 'lost', $this->at('05 10:00'))];

        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90'),
            $this->quote(2, '02 09:00', 'R$ 80'),
            $this->client(3, '03 09:00', 'Pode fazer'),
            $this->quote(4, '08 09:00', 'R$ 70', conversation: 11),
        ], $decided);

        // Antes da decisão, nem o reenvio nem a venda mexem: quem fecha é o dono.
        $this->assertSame([[4, 'open', null, 7, 11]], $this->rows($expected));
    }

    public function test_aberta_de_novo_pelo_dono_segura_tudo_e_a_regra_nao_fecha(): void
    {
        $decided = [1 => new DecidedOpportunity(1, 'open')];

        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90'),
            $this->client(2, '04 09:00', 'Pode fazer'),
            $this->quote(3, '20 09:00', 'R$ 70', conversation: 12),
        ], $decided);

        $this->assertSame([], $this->rows($expected));
    }

    public function test_descartada_segura_orcamentos_e_vendas_do_mesmo_atendimento(): void
    {
        $decided = [1 => new DecidedOpportunity(1, 'discarded', $this->at('01 12:00'))];

        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'Segunda via: R$ 90', conversation: 10),
            $this->quote(2, '01 09:10', 'Corrigido: R$ 95', conversation: 10),
            $this->client(3, '01 15:00', 'Segue o comprovante', conversation: 10),
            $this->quote(4, '08 09:00', 'R$ 300', conversation: 11),
        ], $decided);

        $this->assertSame([[4, 'open', null, 7, 11]], $this->rows($expected));
    }

    public function test_ganha_pelo_dono_conta_como_venda_do_atendimento(): void
    {
        $decided = [1 => new DecidedOpportunity(1, 'won', $this->at('01 10:00'))];

        $expected = $this->expected([
            $this->quote(1, '01 09:05', 'R$ 90', conversation: 10),
            $this->client(2, '01 11:00', 'Segue o comprovante', conversation: 10),
        ], $decided);

        $this->assertSame([], $this->rows($expected));
    }

    /**
     * @param  list<MessageFact>  $messages
     * @param  array<int, DecidedOpportunity>  $decided
     * @return list<ExpectedOpportunity>
     */
    private function expected(array $messages, array $decided = []): array
    {
        return OpportunityRule::expected($messages, $decided);
    }

    /**
     * [âncora, situação, mensagem de venda, vendedora, atendimento]
     *
     * @param  list<ExpectedOpportunity>  $expected
     * @return list<array{int, string, int|null, int|null, int}>
     */
    private function rows(array $expected): array
    {
        return array_map(fn (ExpectedOpportunity $o) => [$o->anchorMessageId, $o->status, $o->closingMessageId, $o->sellerId, $o->conversationId], $expected);
    }

    private function quote(int $id, string $at, string $body, int $seller = 7, int $conversation = 10): MessageFact
    {
        return new MessageFact($id, $this->at($at), 'out', 'seller', 'Ana', $body, conversationId: $conversation, sellerId: $seller);
    }

    private function client(int $id, string $at, string $body, int $conversation = 10): MessageFact
    {
        return new MessageFact($id, $this->at($at), 'in', 'contact', body: $body, conversationId: $conversation);
    }

    /** "04 20:00" = 04/09/2026 20:00 UTC. */
    private function at(string $dayTime): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-'.$dayTime.':00', 'UTC');
    }
}

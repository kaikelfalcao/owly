<?php

namespace Tests\Unit\Insights;

use App\Domains\Conversations\Data\MessageFact;
use App\Domains\Conversations\Data\Timeline;
use App\Domains\Insights\Rules\ClientTurns;
use App\Domains\Insights\Rules\ClosingMessage;
use App\Domains\Insights\Rules\Median;
use App\Domains\Insights\Rules\QuoteMessage;
use App\Domains\Insights\Rules\SaleSignal;
use App\Domains\Insights\Rules\Topics;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessageRulesTest extends TestCase
{
    public function test_vez_do_cliente_junta_mensagens_seguidas_e_ignora_robo_e_sistema(): void
    {
        $turns = ClientTurns::of(new Timeline(7, [
            $this->contact(1, '09:00', 'Oi'),
            $this->contact(2, '09:01', 'Quanto custa?'),
            new MessageFact(3, $this->at('09:01:30'), 'out', 'bot', body: 'Já vamos responder'),
            new MessageFact(4, $this->at('09:02'), 'in', 'system', event: 'missed_call'),
            new MessageFact(5, $this->at('09:10'), 'out', 'seller', 'Ana', 'R$ 90'),
            $this->contact(6, '09:20', 'Obrigado'),
        ]));

        $this->assertCount(2, $turns);
        $this->assertSame(7, $turns[0]->conversationId);
        $this->assertEquals($this->at('09:00'), $turns[0]->startedAt);
        $this->assertEquals($this->at('09:01'), $turns[0]->lastContactAt);
        $this->assertEquals($this->at('09:10'), $turns[0]->answeredAt);
        $this->assertSame('Ana', $turns[0]->answeredBy);
        $this->assertNull($turns[1]->answeredAt);
        $this->assertSame('Obrigado', $turns[1]->lastContactBody);
    }

    #[DataProvider('closings')]
    public function test_mensagem_de_encerramento(string $body, bool $closing): void
    {
        $this->assertSame($closing, ClosingMessage::is($body));
    }

    /** @return array<string, array{string, bool}> */
    public static function closings(): array
    {
        return [
            'ok' => ['ok', true],
            'obrigada!' => ['Obrigada!!', true],
            'joinha' => ['👍', true],
            'muito obrigado' => ['muito obrigado 🙏', true],
            'pergunta' => ['ok, e o prazo?', false],
            'pedido' => ['Quero 500 cartões', false],
        ];
    }

    public function test_orcamento_e_preco_ou_documento_da_empresa(): void
    {
        $this->assertTrue(QuoteMessage::is(new MessageFact(1, $this->at('10:00'), 'out', 'seller', 'Ana', 'Fica R$ 90,00')));
        $this->assertTrue(QuoteMessage::is(new MessageFact(1, $this->at('10:00'), 'out', 'seller', 'Ana', 'orcamento.pdf', 'document')));
        $this->assertFalse(QuoteMessage::is(new MessageFact(1, $this->at('10:00'), 'out', 'bot', body: 'Cartões a partir de R$ 50')));
        $this->assertFalse(QuoteMessage::is($this->contact(1, '10:00', 'Paguei R$ 90')));
    }

    public function test_sinal_de_venda_vem_do_cliente(): void
    {
        $this->assertTrue(SaleSignal::is($this->contact(1, '10:00', 'Fechado, pode fazer')));
        $this->assertTrue(SaleSignal::is($this->contact(1, '10:00', 'fiz o pix agora')));
        $this->assertTrue(SaleSignal::is($this->contact(1, '10:00', 'Segue comprovante')));
        $this->assertFalse(SaleSignal::is($this->contact(1, '10:00', 'Vou pensar')));
        $this->assertFalse(SaleSignal::is(new MessageFact(1, $this->at('10:00'), 'out', 'seller', 'Ana', 'Pode fazer o pix')));
    }

    public function test_temas_e_mediana(): void
    {
        $topics = new Topics([
            'banner' => ['label' => 'Banner', 'pattern' => '/\bbanner/iu'],
            'cartao' => ['label' => 'Cartão', 'pattern' => '/cart(ã|a)o|cart(õ|o)es/iu'],
        ]);

        $this->assertSame(['banner', 'cartao'], $topics->in('Banner e 500 cartões'));
        $this->assertSame([], $topics->in('Oi'));
        $this->assertSame('Cartão', $topics->label('cartao'));
        $this->assertFalse($topics->has('foguete'));

        $this->assertNull(Median::of([]));
        $this->assertSame(20.0, (float) Median::of([30, 10, 20]));
        $this->assertSame(15.0, (float) Median::of([10, 20]));
    }

    private function contact(int $id, string $time, string $body): MessageFact
    {
        return new MessageFact($id, $this->at($time), 'in', 'contact', body: $body);
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("2026-09-14 {$time}", 'UTC');
    }
}

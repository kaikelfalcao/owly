<?php

namespace Tests\Unit\Conversations;

use App\Domains\Accounts\Data\WorkingCalendar;
use App\Domains\Conversations\Rules\ConversationCut;
use App\Domains\Conversations\Rules\CutMessage;
use App\Domains\Conversations\Rules\CutSegment;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Os exemplos da regra de corte (docs/arquitetura.md, "Atendimentos").
 * Calendário: segunda a sexta das 8h às 18h, sábado das 8h às 12h, domingo
 * fechado; 12/10/2026 é feriado nacional e 21/10/2026 é feriado da empresa.
 */
class ConversationCutTest extends TestCase
{
    private const TZ = 'America/Sao_Paulo';

    public function test_cliente_fala_segunda_e_terca_e_um_atendimento(): void
    {
        $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:05', 's'], ['06/10 09:00', 'c']], [3]);
    }

    public function test_cliente_some_um_dia_util_e_volta_em_outro_atendimento(): void
    {
        $segments = $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:10', 's'], ['07/10 09:00', 'c']], [2, 1]);

        $this->assertSame('2026-10-07 09:00', $segments[1]->firstAt->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame(ConversationCut::CONTACT, $segments[1]->openedBy);
    }

    public function test_cliente_volta_semanas_depois(): void
    {
        $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:10', 's'], ['03/11 09:00', 'c']], [2, 1]);
    }

    public function test_vendedora_responde_e_cliente_nao_fica_aberto_ate_passar_um_dia_util(): void
    {
        $cut = $this->cut();
        $segments = $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:30', 's']], [2]);

        // O relógio parte da mensagem da vendedora.
        $this->assertFalse($cut->isClosed($segments[0]->lastPersonAt, $this->at('06/10 17:00')));
        $this->assertTrue($cut->isClosed($segments[0]->lastPersonAt, $this->at('07/10 08:00')));
    }

    public function test_noite_entre_dois_dias_nao_corta(): void
    {
        $this->assertCut([['06/10 17:50', 'c'], ['06/10 17:58', 's'], ['07/10 08:05', 'c']], [3]);
    }

    public function test_sabado_com_expediente_conta_como_dia_inteiro(): void
    {
        $this->assertCut([['16/10 17:50', 'c'], ['16/10 17:55', 's'], ['19/10 08:10', 'c']], [2, 1]);
    }

    public function test_com_sabado_fechado_sexta_e_segunda_ficam_juntas(): void
    {
        $this->assertCut([['16/10 17:50', 'c'], ['16/10 17:55', 's'], ['19/10 08:10', 'c']], [3], saturday: false);
    }

    public function test_sabado_de_manha_e_segunda_ficam_juntos(): void
    {
        $this->assertCut([['17/10 11:00', 'c'], ['19/10 09:00', 's'], ['19/10 09:20', 'c']], [3]);
    }

    public function test_feriado_nacional_nao_conta(): void
    {
        // Domingo fechado e segunda 12/10 feriado.
        $this->assertCut([['10/10 11:00', 'c'], ['13/10 09:00', 'c']], [2]);
    }

    public function test_feriado_da_empresa_nao_conta(): void
    {
        $this->assertCut([['20/10 15:00', 'c'], ['22/10 09:00', 'c']], [2]);
        $this->assertCut([['20/10 15:00', 'c'], ['22/10 09:00', 'c']], [1, 1], holidays: []);
    }

    public function test_resposta_depois_do_corte_vai_para_o_atendimento_novo(): void
    {
        $segments = $this->assertCut([['05/10 10:00', 'c'], ['07/10 09:00', 'c'], ['07/10 09:30', 's']], [1, 2]);

        $this->assertSame([2, 3], $segments[1]->keys);
    }

    public function test_cobranca_da_vendedora_segura_o_atendimento(): void
    {
        // O cliente ficou calado terça, quarta e quinta, mas a vendedora não.
        $this->assertCut([
            ['05/10 10:00', 'c'],
            ['05/10 10:20', 's'],
            ['06/10 15:00', 's'],
            ['08/10 09:00', 's'],
            ['09/10 14:00', 'c'],
        ], [5]);
    }

    public function test_vendedora_nunca_abre_atendimento(): void
    {
        // Cobrança semanas depois: entra no último atendimento, não abre outro.
        $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:20', 's'], ['03/11 09:00', 's']], [3]);
    }

    public function test_robo_e_aviso_entram_mas_nao_seguram_o_atendimento(): void
    {
        // A resposta automática de terça não zera o relógio: quarta inteira
        // passa sem pessoa desde segunda, e o cliente de quinta abre outro.
        $this->assertCut([['05/10 10:00', 'c'], ['06/10 19:00', 'b'], ['07/10 20:00', 'x'], ['08/10 09:00', 'c']], [3, 1]);
    }

    public function test_ligacao_perdida_do_cliente_conta_como_mensagem_dele(): void
    {
        $segments = $this->assertCut([['05/10 10:00', 'c'], ['05/10 10:05', 's'], ['07/10 09:00', 'm']], [2, 1]);

        $this->assertSame(ConversationCut::CONTACT, $segments[1]->openedBy);
    }

    public function test_empresa_comeca_e_cliente_responde_no_mesmo_atendimento(): void
    {
        $segments = $this->assertCut([['05/10 09:00', 's'], ['05/10 11:00', 'c'], ['05/10 11:10', 's']], [3]);

        $this->assertSame(ConversationCut::COMPANY, $segments[0]->openedBy);
    }

    public function test_empresa_comeca_e_cliente_nunca_escreve(): void
    {
        $segments = $this->assertCut([['05/10 09:00', 's'], ['06/10 10:00', 's']], [2]);

        $this->assertSame(ConversationCut::COMPANY, $segments[0]->openedBy);
        $this->assertTrue($this->cut()->isClosed($segments[0]->lastPersonAt, $this->at('08/10 09:00')));
    }

    public function test_empresa_comeca_e_cliente_so_aparece_depois_de_um_dia_util(): void
    {
        $segments = $this->assertCut([['05/10 09:00', 's'], ['07/10 10:00', 'c']], [1, 1]);

        $this->assertSame([ConversationCut::COMPANY, ConversationCut::CONTACT], array_map(fn (CutSegment $s) => $s->openedBy, $segments));
    }

    public function test_historico_que_comeca_pelo_robo_fecha_pelo_tempo(): void
    {
        // Sem mensagem de pessoa, o relógio parte do começo.
        $segments = $this->assertCut([['05/10 09:00', 'b'], ['07/10 10:00', 'c']], [1, 1]);

        $this->assertSame(ConversationCut::COMPANY, $segments[0]->openedBy);
    }

    public function test_responsavel_e_quem_mais_escreveu_e_no_empate_quem_respondeu_primeiro(): void
    {
        $segments = $this->cut()->segments([
            $this->message(1, '05/10 10:00', 'c'),
            $this->message(2, '05/10 10:05', 's', seller: 7),
            $this->message(3, '05/10 10:06', 's', seller: 9),
            $this->message(4, '05/10 10:07', 's', seller: 9),
        ]);
        $this->assertSame(9, $segments[0]->sellerId());

        $tie = $this->cut()->segments([
            $this->message(1, '05/10 10:00', 'c'),
            $this->message(2, '05/10 10:05', 's', seller: 7),
            $this->message(3, '05/10 10:06', 's', seller: 9),
        ]);
        $this->assertSame(7, $tie[0]->sellerId());
    }

    /**
     * @param  list<array{0: string, 1: string}>  $messages  [dia/mês hora:minuto, quem] com c, s, b (robô), x (aviso), m (ligação perdida do cliente)
     * @param  list<int>  $sizes  mensagens por atendimento
     * @param  list<string>  $holidays
     * @return list<CutSegment>
     */
    private function assertCut(array $messages, array $sizes, bool $saturday = true, array $holidays = ['2026-10-21']): array
    {
        $segments = $this->cut($saturday, $holidays)->segments(array_map(
            fn (array $m, int $i) => $this->message($i + 1, $m[0], $m[1]),
            $messages,
            array_keys($messages),
        ));

        $this->assertSame($sizes, array_map(fn (CutSegment $s) => count($s->keys), $segments));

        return $segments;
    }

    /**
     * @param  list<string>  $holidays
     */
    private function cut(bool $saturday = true, array $holidays = ['2026-10-21']): ConversationCut
    {
        $week = array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri'], ['08:00', '18:00']);

        return new ConversationCut(new WorkingCalendar(
            organizationId: 1,
            timezone: self::TZ,
            hours: [...$week, 'sat' => $saturday ? ['08:00', '12:00'] : null, 'sun' => null],
            holidays: $holidays,
            nationalHolidays: true,
            configured: true,
        ));
    }

    private function message(int $id, string $at, string $who, ?int $seller = null): CutMessage
    {
        return match ($who) {
            'c' => new CutMessage($id, $this->at($at), 'contact', 'in'),
            's' => new CutMessage($id, $this->at($at), 'seller', 'out', sellerId: $seller),
            'b' => new CutMessage($id, $this->at($at), 'bot', 'out', 'auto_reply'),
            'x' => new CutMessage($id, $this->at($at), 'system', 'out', 'deleted'),
            'm' => new CutMessage($id, $this->at($at), 'system', 'in', 'missed_call'),
        };
    }

    private function at(string $dayMonthTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('d/m/Y H:i', str_replace(' ', '/2026 ', $dayMonthTime), self::TZ)->utc();
    }
}

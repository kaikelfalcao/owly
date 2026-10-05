<?php

namespace Tests\Unit\Insights;

use App\Domains\Insights\Data\Period;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function test_o_periodo_termina_na_mensagem_mais_recente(): void
    {
        $latest = CarbonImmutable::parse('2026-09-30 22:00', 'UTC');

        $period = Period::make('7', $latest, 'America/Sao_Paulo');

        $this->assertSame('7', $period->key);
        // 7 dias no fuso da empresa: de 24/09 00h (03h UTC) até a última mensagem.
        $this->assertSame('2026-09-24 03:00:00', $period->since->format('Y-m-d H:i:s'));
        $this->assertTrue($period->contains($latest));
        $this->assertFalse($period->contains(CarbonImmutable::parse('2026-09-24 02:59', 'UTC')));
    }

    public function test_chave_desconhecida_vira_30_dias_e_tudo_nao_tem_inicio(): void
    {
        $latest = CarbonImmutable::parse('2026-09-30 12:00', 'UTC');

        $this->assertSame('30', Period::make('999', $latest, 'UTC')->key);
        $this->assertSame('30', Period::make(null, $latest, 'UTC')->key);
        $this->assertNull(Period::make('tudo', $latest, 'UTC')->since);
        $this->assertTrue(Period::make('tudo', $latest, 'UTC')->contains(CarbonImmutable::parse('2020-01-01', 'UTC')));
    }
}

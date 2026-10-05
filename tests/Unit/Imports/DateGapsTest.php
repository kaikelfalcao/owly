<?php

namespace Tests\Unit\Imports;

use App\Domains\Imports\Rules\DateGaps;
use PHPUnit\Framework\TestCase;

class DateGapsTest extends TestCase
{
    public function test_dias_seguidos_nao_tem_buraco(): void
    {
        $this->assertSame([], DateGaps::find(['2026-09-01', '2026-09-02', '2026-09-03']));
    }

    public function test_um_dia_sem_mensagem_nao_conta(): void
    {
        $this->assertSame([], DateGaps::find(['2026-09-01', '2026-09-03']));
    }

    public function test_dois_dias_ou_mais_sem_mensagem_viram_buraco(): void
    {
        $this->assertSame(
            [['from' => '2026-09-02', 'to' => '2026-09-04', 'days' => 3]],
            DateGaps::find(['2026-09-05', '2026-09-01', '2026-09-01']),
        );
    }

    public function test_um_dia_so_nao_tem_buraco(): void
    {
        $this->assertSame([], DateGaps::find(['2026-09-01']));
    }
}

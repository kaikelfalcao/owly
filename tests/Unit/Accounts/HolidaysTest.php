<?php

namespace Tests\Unit\Accounts;

use App\Domains\Accounts\Rules\Holidays;
use PHPUnit\Framework\TestCase;

class HolidaysTest extends TestCase
{
    public function test_pascoa_de_alguns_anos(): void
    {
        $this->assertSame('2025-04-20', Holidays::easter(2025)->toDateString());
        $this->assertSame('2026-04-05', Holidays::easter(2026)->toDateString());
        $this->assertSame('2027-03-28', Holidays::easter(2027)->toDateString());
    }

    public function test_nacionais_de_2026_com_sexta_feira_santa_e_sem_carnaval(): void
    {
        $days = Holidays::national(2026);

        $this->assertCount(10, $days);
        $this->assertSame('Sexta-feira Santa', $days['2026-04-03']);
        $this->assertSame('Independência', $days['2026-09-07']);
        $this->assertSame('Consciência Negra', $days['2026-11-20']);
        $this->assertArrayNotHasKey('2026-02-17', $days);
        $this->assertSame(array_keys($days), collect($days)->keys()->sort()->values()->all());
    }
}

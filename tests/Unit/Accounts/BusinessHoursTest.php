<?php

namespace Tests\Unit\Accounts;

use App\Domains\Accounts\Rules\BusinessHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BusinessHoursTest extends TestCase
{
    private const TZ = 'America/Sao_Paulo';

    public function test_sexta_as_19h_respondida_segunda_as_8h05_sao_5_minutos(): void
    {
        $hours = $this->weekdays();

        $this->assertSame(300, $hours->secondsBetween($this->at('2026-09-11 19:00'), $this->at('2026-09-14 08:05')));
    }

    public function test_conta_so_a_parte_dentro_do_expediente(): void
    {
        $hours = $this->weekdays();

        // 17:30 de quinta até 8:30 de sexta: meia hora em cada dia.
        $this->assertSame(3600, $hours->secondsBetween($this->at('2026-09-10 17:30'), $this->at('2026-09-11 08:30')));
        $this->assertSame(0, $hours->secondsBetween($this->at('2026-09-11 08:30'), $this->at('2026-09-11 08:00')));
    }

    public function test_feriado_nacional_conta_como_fechado(): void
    {
        $hours = $this->weekdays();

        // 07/09/2026 é segunda e feriado: da sexta às 19h à terça às 8h10 são 10 minutos.
        $this->assertFalse($hours->isOpen($this->at('2026-09-07 10:00')));
        $this->assertSame(600, $hours->secondsBetween($this->at('2026-09-04 19:00'), $this->at('2026-09-08 08:10')));
    }

    public function test_feriados_nacionais_podem_ser_desligados(): void
    {
        $hours = new BusinessHours($this->week(), self::TZ, nationalHolidays: false);

        $this->assertTrue($hours->isOpen($this->at('2026-09-07 10:00')));
    }

    public function test_feriado_da_empresa_conta_como_fechado(): void
    {
        $hours = new BusinessHours($this->week(), self::TZ, ['2026-09-15']);

        $this->assertFalse($hours->isOpen($this->at('2026-09-15 10:00')));
        $this->assertTrue($hours->isOpen($this->at('2026-09-16 10:00')));
    }

    public function test_aberto_respeita_o_fuso_da_empresa(): void
    {
        $hours = $this->weekdays();

        $this->assertTrue($hours->isOpen($this->at('2026-09-14 08:00')));
        $this->assertFalse($hours->isOpen($this->at('2026-09-14 18:00')));
        $this->assertFalse($hours->isOpen($this->at('2026-09-13 10:00')));
        // 11h UTC são 8h em São Paulo.
        $this->assertTrue($hours->isOpen(CarbonImmutable::parse('2026-09-14 11:00', 'UTC')));
    }

    public function test_sem_nenhum_dia_aberto_conta_tudo(): void
    {
        $hours = new BusinessHours(array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], null), self::TZ);

        $this->assertTrue($hours->isOpen($this->at('2026-09-13 03:00')));
        $this->assertSame(86400, $hours->secondsBetween($this->at('2026-09-12 10:00'), $this->at('2026-09-13 10:00')));
    }

    public function test_dias_uteis_entre_contam_so_as_datas_do_meio(): void
    {
        $hours = $this->weekdays();

        // Segunda e terça seguidas: nenhuma data no meio, por mais horas que passem.
        $this->assertSame(0, $hours->workingDaysBetween($this->at('2026-09-14 08:00'), $this->at('2026-09-15 17:59')));
        // Terça 18h e quinta 8h: quarta inteira no meio.
        $this->assertSame(1, $hours->workingDaysBetween($this->at('2026-09-15 18:00'), $this->at('2026-09-17 08:00')));
        // Sexta e segunda: sábado e domingo fechados.
        $this->assertSame(0, $hours->workingDaysBetween($this->at('2026-09-11 17:00'), $this->at('2026-09-14 08:00')));
        // De trás para frente não conta nada.
        $this->assertSame(0, $hours->workingDaysBetween($this->at('2026-09-17 08:00'), $this->at('2026-09-14 08:00')));
    }

    public function test_dias_uteis_pulam_feriados_e_param_no_limite(): void
    {
        // 07/09/2026 é feriado nacional (segunda); 08/09 cadastrado pela empresa.
        $hours = new BusinessHours($this->week(), self::TZ, ['2026-09-08']);

        $this->assertSame(0, $hours->workingDaysBetween($this->at('2026-09-04 10:00'), $this->at('2026-09-09 10:00')));
        $this->assertFalse($hours->isWorkingDay($this->at('2026-09-07 10:00')));
        $this->assertSame(1, $hours->workingDaysBetween($this->at('2026-09-01 10:00'), $this->at('2026-12-01 10:00'), limit: 1));
    }

    public function test_sem_nenhum_dia_aberto_toda_data_e_util(): void
    {
        $hours = new BusinessHours(array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], null), self::TZ);

        $this->assertSame(2, $hours->workingDaysBetween($this->at('2026-09-11 10:00'), $this->at('2026-09-14 10:00')));
    }

    private function weekdays(): BusinessHours
    {
        return new BusinessHours($this->week(), self::TZ);
    }

    /**
     * @return array<string, array{0: string, 1: string}|null>
     */
    private function week(): array
    {
        return array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri'], ['08:00', '18:00']) + ['sat' => null, 'sun' => null];
    }

    private function at(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, self::TZ)->utc();
    }
}

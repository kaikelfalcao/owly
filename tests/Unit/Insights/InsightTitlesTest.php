<?php

namespace Tests\Unit\Insights;

use App\Domains\Insights\Services\Dashboard;
use PHPUnit\Framework\TestCase;

class InsightTitlesTest extends TestCase
{
    /** A trilha da conversa aberta a partir do painel usa os mesmos nomes. */
    public function test_a_trilha_da_conversa_usa_os_nomes_das_leituras_do_painel(): void
    {
        $front = file_get_contents(dirname(__DIR__, 3).'/resources/js/lib/insights.ts');

        foreach (Dashboard::INSIGHTS as $key => $title) {
            $name = preg_match('/^[a-z]+$/', $key) ? $key : "'{$key}'";

            $this->assertStringContainsString("{$name}: '{$title}'", $front, "Leitura {$key}");
        }
    }
}

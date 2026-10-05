<?php

namespace Tests\Feature\Ai;

use App\Domains\Ai\Services\AskAboutConversation;
use App\Domains\Conversations\Data\Transcript;
use Tests\TestCase;

class PromptTest extends TestCase
{
    public function test_conversa_enorme_perde_o_comeco_e_guarda_o_fim(): void
    {
        $lines = [];

        for ($i = 1; $i <= 2000; $i++) {
            $lines[] = ['id' => $i, 'at' => '01/09/2026 09:00', 'who' => 'Cliente', 'text' => "mensagem número {$i} ".str_repeat('x', 40)];
        }

        $prompt = app(AskAboutConversation::class)->prompt(new Transcript(1, $lines, []), 'Resuma', null);

        $this->assertLessThan(AskAboutConversation::MAX_CHARS + 500, mb_strlen($prompt->content));
        $this->assertStringContainsString('só a parte mais recente', $prompt->content);
        $this->assertStringContainsString('mensagem número 2000 ', $prompt->content);
        $this->assertStringNotContainsString('mensagem número 1 ', $prompt->content);
    }

    public function test_instrucoes_pedem_portugues_e_proibem_adivinhar_dados(): void
    {
        $this->assertStringContainsString('português do Brasil', AskAboutConversation::INSTRUCTIONS);
        $this->assertStringContainsString('não tente adivinhar', AskAboutConversation::INSTRUCTIONS);
    }
}

<?php

namespace Tests\Unit\Ai;

use App\Domains\Ai\Services\Redactor;
use PHPUnit\Framework\TestCase;

class RedactorTest extends TestCase
{
    private function mask(string $text, array $names = []): string
    {
        return (new Redactor)->mask($text, $names);
    }

    public function test_esconde_telefones_em_varios_formatos(): void
    {
        foreach (['+55 (11) 98888-7777', '11 98888-7777', '5511988887777', '(11) 3333-4444', '98888 7777'] as $phone) {
            $this->assertSame('ligue [telefone] hoje', $this->mask("ligue {$phone} hoje"), $phone);
        }
    }

    public function test_esconde_email_cpf_cnpj_e_cep(): void
    {
        $this->assertSame(
            'e-mail [e-mail], CPF [CPF], CNPJ [CNPJ], CEP [CEP]',
            $this->mask('e-mail joana.teste@exemplo.com.br, CPF 123.456.789-09, CNPJ 12.345.678/0001-90, CEP 01310-100'),
        );
    }

    public function test_cpf_e_cnpj_sem_pontuacao_tambem(): void
    {
        $this->assertSame('[CPF] e [CNPJ]', $this->mask('12345678909 e 12345678000190'));
    }

    public function test_esconde_o_nome_do_cliente_inteiro_e_por_partes(): void
    {
        $this->assertSame(
            'Oi [cliente], tudo bem? Falei com [cliente] ontem.',
            $this->mask('Oi Maria Inventada, tudo bem? Falei com maria ontem.', ['Maria Inventada']),
        );
    }

    public function test_telefone_entre_parenteses_e_nome_que_aparece_no_marcador(): void
    {
        $this->assertSame('O [cliente] ([telefone]) comprou?', $this->mask('O Cliente Teste (11 98888-7777) comprou?', ['Cliente Teste']));
    }

    public function test_nao_esconde_pedaco_de_outra_palavra(): void
    {
        $this->assertSame('Mariana pediu', $this->mask('Mariana pediu', ['Maria']));
    }

    public function test_emoji_no_nome_nao_atrapalha(): void
    {
        $this->assertSame('Olá [cliente]', $this->mask('Olá Regina', ['⭐ Regina']));
    }

    public function test_preco_e_quantidade_continuam(): void
    {
        $text = '1000 panfletos por R$ 240,00, pronto em 3 dias';

        $this->assertSame($text, $this->mask($text));
    }
}

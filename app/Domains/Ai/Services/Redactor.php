<?php

namespace App\Domains\Ai\Services;

/**
 * Máscara de dados pessoais antes de qualquer texto sair para um provedor:
 * e-mail, CPF, CNPJ, CEP, telefone e os nomes de cliente que a gente conhece.
 * Vem sempre ligada na primeira entrega (docs/ia.md).
 */
class Redactor
{
    private const PATTERNS = [
        '[e-mail]' => '/[\p{L}0-9._%+-]+@[\p{L}0-9.-]+\.[\p{L}]{2,}/u',
        '[CNPJ]' => '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/',
        '[CPF]' => '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
        '[CEP]' => '/\b\d{5}-\d{3}\b/',
        // +55 (75) 99999-0000, 75 9999-0000, 5575999990000…
        '[telefone]' => '/(?<!\w)(?:\+?55[\s.-]?)?(?:(?:\(\d{2}\)|\d{2})[\s.-]?)?9?\d{4}[\s.-]?\d{4}(?!\w)/',
    ];

    /**
     * @param  list<string>  $names  nomes a esconder (o cliente da conversa)
     */
    public function mask(string $text, array $names = []): string
    {
        foreach (self::PATTERNS as $label => $pattern) {
            $text = (string) preg_replace($pattern, $label, $text);
        }

        foreach ($this->nameParts($names) as $part) {
            $text = (string) preg_replace('/(?<![\p{L}\[])'.preg_quote($part, '/').'(?![\p{L}\]])/iu', '[cliente]', $text);
        }

        return $text;
    }

    /**
     * O nome inteiro e cada parte com 3 letras ou mais ("Maria", "Souza"),
     * do maior para o menor, para "Maria Souza" virar um [cliente] só.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function nameParts(array $names): array
    {
        $parts = [];

        foreach ($names as $name) {
            $name = trim((string) preg_replace('/[^\p{L}\s\'-]/u', ' ', $name));

            if ($name === '') {
                continue;
            }

            $parts[] = preg_replace('/\s+/', ' ', $name);

            foreach (preg_split('/\s+/', $name) ?: [] as $word) {
                if (mb_strlen($word) >= 3 && ! in_array(mb_strtolower($word), ['dos', 'das', 'del', 'von'], true)) {
                    $parts[] = $word;
                }
            }
        }

        $parts = array_values(array_unique($parts));
        usort($parts, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $parts;
    }
}

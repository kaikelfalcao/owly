<?php

namespace App\Domains\Insights\Rules;

/**
 * O que o cliente procura, por palavras. A lista vem de
 * config('owly.insights.topics'), porque muda de ramo para ramo.
 */
final class Topics
{
    /**
     * @param  array<string, array{label: string, pattern: string}>  $topics
     */
    public function __construct(private readonly array $topics) {}

    /**
     * @return list<string> chaves dos temas citados no texto
     */
    public function in(string $text): array
    {
        $found = [];

        foreach ($this->topics as $key => $topic) {
            if (preg_match($topic['pattern'], $text) === 1) {
                $found[] = $key;
            }
        }

        return $found;
    }

    public function label(string $key): string
    {
        return $this->topics[$key]['label'] ?? $key;
    }

    public function has(string $key): bool
    {
        return isset($this->topics[$key]);
    }
}

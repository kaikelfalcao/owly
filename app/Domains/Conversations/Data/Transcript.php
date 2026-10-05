<?php

namespace App\Domains\Conversations\Data;

/**
 * Uma conversa em linhas de texto: hora, quem falou e o que disse.
 */
final readonly class Transcript
{
    /**
     * @param  list<array{id: int, at: string, who: string, text: string}>  $lines  hora no fuso da empresa
     * @param  list<string>  $contactNames  nomes pelos quais o cliente aparece
     */
    public function __construct(
        public int $conversationId,
        public array $lines,
        public array $contactNames,
    ) {}

    public function has(int $messageId): bool
    {
        foreach ($this->lines as $line) {
            if ($line['id'] === $messageId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{id: int, at: string, who: string, text: string}|null
     */
    public function line(int $messageId): ?array
    {
        foreach ($this->lines as $line) {
            if ($line['id'] === $messageId) {
                return $line;
            }
        }

        return null;
    }
}

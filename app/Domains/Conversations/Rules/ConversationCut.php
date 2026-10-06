<?php

namespace App\Domains\Conversations\Rules;

use App\Domains\Accounts\Data\WorkingCalendar;
use Carbon\CarbonImmutable;

/**
 * Corta o histórico de um cliente em atendimentos (docs/arquitetura.md,
 * "Atendimentos"). Só o cliente abre; depois de aberto, mensagem de qualquer
 * pessoa segura o atendimento; ele termina quando passa um dia útil inteiro
 * sem mensagem de pessoa. A primeira mensagem do histórico abre mesmo quando
 * é da empresa, porque toda mensagem precisa estar num atendimento.
 */
final class ConversationCut
{
    public const CONTACT = 'contact';

    public const COMPANY = 'company';

    public function __construct(private readonly WorkingCalendar $calendar) {}

    /**
     * @param  list<CutMessage>  $messages  em ordem (sent_at, id); a primeira abre o primeiro atendimento
     * @return list<CutSegment>
     */
    public function segments(array $messages): array
    {
        $segments = [];
        $current = null;

        foreach ($messages as $message) {
            if ($current === null || ($message->fromClient() && $this->silent($current->lastPersonAt, $message->at))) {
                $current = new CutSegment($message->fromClient() ? self::CONTACT : self::COMPANY, $message->at);
                $segments[] = $current;
            }

            $current->add($message);
        }

        return $segments;
    }

    /**
     * Fechado quando passou um dia útil inteiro entre a última mensagem de
     * pessoa e a referência (a mensagem mais recente da empresa no Owly).
     */
    public function isClosed(CarbonImmutable $lastPersonAt, CarbonImmutable $reference): bool
    {
        return $this->silent($lastPersonAt, $reference);
    }

    private function silent(CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->calendar->workingDaysBetween($from, $to, limit: 1) >= 1;
    }
}

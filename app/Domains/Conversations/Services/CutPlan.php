<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Models\Contact;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Rules\CutSegment;
use Carbon\CarbonImmutable;

/**
 * O que o corte vai fazer com os atendimentos de um cliente, antes de
 * gravar. O owly:recut --dry-run para aqui.
 */
final readonly class CutPlan
{
    /**
     * @param  list<CutSegment>  $segments  os atendimentos calculados, em ordem
     * @param  array<int, int|null>  $targets  atendimento calculado => atendimento que fica com ele (null: novo)
     * @param  array<int, Conversation>  $scope  os atendimentos que entraram no cálculo, por id
     * @param  list<int>  $leftovers  atendimentos que sobram e serão apagados
     * @param  array<int, array{0: int, 1: int}>  $moves  mensagem gravada que muda de lugar => [atendimento de hoje, atendimento calculado]
     * @param  list<array<string, mixed>>  $newRows  mensagens novas, ainda sem atendimento
     * @param  array<int, int>  $openings  atendimento que sobra => mensagem que o abria
     */
    public function __construct(
        public Contact $contact,
        public array $segments,
        public array $targets,
        public array $scope,
        public array $leftovers,
        public array $moves,
        public array $newRows,
        public array $openings,
        public CarbonImmutable $reference,
    ) {}

    /**
     * Atendimentos que já existiam e perderam mensagem ou vão ser apagados:
     * o histórico deles foi refeito.
     *
     * @return list<int>
     */
    public function changed(): array
    {
        $changed = array_fill_keys($this->leftovers, true);

        foreach ($this->moves as [$from]) {
            $changed[$from] = true;
        }

        return array_keys($changed);
    }

    public function isEmpty(): bool
    {
        return $this->segments === [] && $this->leftovers === [];
    }
}

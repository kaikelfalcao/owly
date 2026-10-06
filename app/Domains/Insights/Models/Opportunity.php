<?php

namespace App\Domains\Insights\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * O cliente recebeu preço e ainda pode comprar (docs/arquitetura.md,
 * "Oportunidades"). Aponta para mensagens de Conversas pelo id.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contact_id
 * @property int|null $conversation_id onde abriu
 * @property int $anchor_message_id a mensagem que deu origem: a identidade no recálculo
 * @property int|null $closing_message_id a mensagem de venda
 * @property int|null $seller_id quem mandou o orçamento
 * @property string $status open | won | lost | discarded
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $loss_reason
 * @property int|null $value_cents
 * @property string $source rule | owner
 * @property int|null $decided_by
 * @property Carbon|null $decided_at preenchido, a regra não mexe mais na linha
 */
#[Fillable(['organization_id', 'contact_id', 'conversation_id', 'anchor_message_id', 'closing_message_id', 'seller_id', 'status', 'opened_at', 'closed_at', 'loss_reason', 'value_cents', 'source', 'decided_by', 'decided_at'])]
class Opportunity extends Model
{
    use BelongsToOrganization;

    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    /** A identificação estava errada: não era oportunidade. Fora de toda métrica. */
    public const DISCARDED = 'discarded';

    public const RULE = 'rule';

    public const OWNER = 'owner';

    /** Motivos de perda, com o nome da tela. */
    public const LOSS_REASONS = [
        'price' => 'Preço',
        'deadline' => 'Prazo',
        'competitor' => 'Comprou em outro lugar',
        'no_reply' => 'Parou de responder',
        'gave_up' => 'Desistiu',
        'other' => 'Outro motivo',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Toda métrica de oportunidade, conversão e perda parte daqui: a
     * descartada fica de fora, sem exceção (invariante I4).
     *
     * @param  Builder<self>  $query
     */
    public function scopeCountable(Builder $query): void
    {
        $query->where('status', '!=', self::DISCARDED);
    }

    public function decided(): bool
    {
        return $this->decided_at !== null;
    }
}

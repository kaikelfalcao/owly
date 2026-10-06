<?php

namespace App\Domains\Conversations\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Um atendimento: um trecho do histórico com um cliente, com começo e fim
 * (docs/arquitetura.md, "Atendimentos"). O corte decide quais mensagens
 * estão nele; venham do zip ou da API.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contact_id
 * @property int|null $seller_id responsável: a vendedora com mais mensagens
 * @property string $opened_by contact | company
 * @property string $status open | closed
 * @property Carbon $first_message_at
 * @property Carbon $last_message_at
 * @property int $messages_count
 * @property-read Contact $contact
 */
#[Fillable(['organization_id', 'contact_id', 'seller_id', 'opened_by', 'status', 'first_message_at', 'last_message_at', 'messages_count'])]
class Conversation extends Model
{
    use BelongsToOrganization;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'first_message_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}

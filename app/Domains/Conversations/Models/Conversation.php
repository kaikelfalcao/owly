<?php

namespace App\Domains\Conversations\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * O histórico de mensagens com um cliente, venha do zip ou da API.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contact_id
 * @property Carbon|null $first_message_at
 * @property Carbon|null $last_message_at
 * @property int $messages_count
 * @property-read Contact $contact
 */
#[Fillable(['organization_id', 'contact_id', 'first_message_at', 'last_message_at', 'messages_count'])]
class Conversation extends Model
{
    use BelongsToOrganization;

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

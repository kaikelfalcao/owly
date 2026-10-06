<?php

namespace App\Domains\Conversations\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $conversation_id
 * @property int $contact_id
 * @property int|null $import_id
 * @property string $source
 * @property string $external_id
 * @property Carbon $sent_at
 * @property string $direction
 * @property string $author
 * @property int|null $seller_id
 * @property string|null $body
 * @property string|null $media_type
 * @property string|null $media_name
 * @property string|null $event
 * @property string|null $quoted
 * @property-read Seller|null $seller
 */
class Message extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}

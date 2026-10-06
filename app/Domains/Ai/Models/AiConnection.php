<?php

namespace App\Domains\Ai\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A chave de um provedor de IA ligada à empresa. A chave fica cifrada e
 * nunca vai inteira para a tela.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property string $provider
 * @property string $label
 * @property string $api_key
 * @property string $key_hint
 * @property string $model
 * @property bool $is_default
 * @property Carbon|null $last_checked_at
 * @property Carbon $created_at
 */
class AiConnection extends Model
{
    use BelongsToOrganization;

    protected $guarded = ['id'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_default' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Domains\Conversations\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Quem responde pela empresa. No zip, é o nome que assina a mensagem
 * ("*Nome:*" no começo).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 */
#[Fillable(['organization_id', 'name'])]
class Seller extends Model
{
    use BelongsToOrganization;
}

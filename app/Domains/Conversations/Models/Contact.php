<?php

namespace App\Domains\Conversations\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Quem escreve para a empresa pelo WhatsApp.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $external_key
 * @property string|null $phone
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'external_key', 'phone', 'name'])]
class Contact extends Model
{
    use BelongsToOrganization;

    /**
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Nome para a tela: o nome do contato, ou o telefone formatado.
     */
    public function displayName(): string
    {
        return $this->name ?: ($this->phone ? self::formatPhone($this->phone) : 'Cliente sem nome');
    }

    /** "5575999990000" -> "+55 75 99999-0000" */
    public static function formatPhone(string $digits): string
    {
        if (preg_match('/^55(\d{2})(\d{4,5})(\d{4})$/', $digits, $m)) {
            return "+55 {$m[1]} {$m[2]}-{$m[3]}";
        }

        return '+'.$digits;
    }
}

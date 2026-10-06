<?php

namespace App\Domains\Ai\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Uma pergunta feita à IA sobre uma conversa, com a resposta e o consumo.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property int|null $ai_connection_id
 * @property int $conversation_id
 * @property int|null $message_id
 * @property string $question
 * @property string|null $answer
 * @property string $provider
 * @property string $model
 * @property string $status done | failed
 * @property string|null $error_code
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $duration_ms
 * @property Carbon $created_at
 */
class AiQuestion extends Model
{
    use BelongsToOrganization;

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $guarded = ['id'];
}

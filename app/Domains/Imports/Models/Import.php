<?php

namespace App\Domains\Imports\Models;

use App\Domains\Accounts\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Um zip enviado e o que ele trouxe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property string $format
 * @property string $status pending | running | done | failed
 * @property string $file_name
 * @property int $file_size
 * @property string $file_hash
 * @property string|null $path
 * @property array<string, mixed>|null $stats
 * @property list<array{file: string, code: string}>|null $problems
 * @property string|null $error_code
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class Import extends Model
{
    use BelongsToOrganization;

    public const PENDING = 'pending';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'problems' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::PENDING, self::RUNNING], true);
    }
}

<?php

namespace App\Platform\Queue;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Middleware de job: uma tarefa por vez mexendo nos dados de uma empresa
 * (importação, recálculo de oportunidades). Quem chega com outra rodando
 * volta para a fila e tenta de novo depois.
 *
 * Diferente do WithoutOverlapping do Laravel, entra direto quando o mesmo
 * processo já está com a trava: uma tarefa disparada de dentro de outra (na
 * fila síncrona, por exemplo) já está dentro do trecho protegido.
 */
class OneAtATime
{
    /** @var array<string, true> travas que este processo segura agora */
    private static array $held = [];

    public function __construct(
        public readonly string $key,
        public readonly int $releaseAfter = 30,
        public readonly int $expireAfter = 660,
    ) {}

    public static function organization(int $organizationId, int $timeout): self
    {
        return new self("organization:{$organizationId}:data", expireAfter: $timeout + 60);
    }

    public function handle(object $job, Closure $next): mixed
    {
        if (isset(self::$held[$this->key])) {
            return $next($job);
        }

        $lock = Cache::lock('one-at-a-time:'.$this->key, $this->expireAfter);

        if (! $lock->get()) {
            if (method_exists($job, 'release')) {
                $job->release($this->releaseAfter);
            }

            return null;
        }

        self::$held[$this->key] = true;

        try {
            return $next($job);
        } finally {
            unset(self::$held[$this->key]);
            $lock->release();
        }
    }
}

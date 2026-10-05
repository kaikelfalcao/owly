<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Contracts\ConversationPanels;
use Closure;

class PanelRegistry implements ConversationPanels
{
    /** @var array<string, Closure> */
    private array $panels = [];

    public function register(string $key, Closure $props): void
    {
        $this->panels[$key] = $props;
    }

    public function for(int $organizationId, int $conversationId): array
    {
        return array_map(fn (Closure $props) => $props($organizationId, $conversationId), $this->panels);
    }
}

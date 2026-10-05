<?php

namespace App\Domains\Ai;

use App\Domains\Ai\Adapters\Gemini\Gemini;
use App\Domains\Ai\Contracts\AiProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Os provedores que o assistente mostra. Os que ainda não têm adaptador
 * aparecem como "em breve", para o dono saber que poderá somar outros.
 */
class ProviderCatalog
{
    private const PROVIDERS = [
        'gemini' => [
            'name' => 'Gemini',
            'company' => 'Google',
            'adapter' => Gemini::class,
            'keyUrl' => 'https://aistudio.google.com/apikey',
        ],
        'claude' => ['name' => 'Claude', 'company' => 'Anthropic', 'adapter' => null],
        'openai' => ['name' => 'ChatGPT', 'company' => 'OpenAI', 'adapter' => null],
    ];

    public function __construct(private readonly Container $container) {}

    public function provider(string $id): AiProvider
    {
        $adapter = self::PROVIDERS[$id]['adapter'] ?? null;

        if ($adapter === null) {
            throw new InvalidArgumentException("Provedor sem adaptador: {$id}");
        }

        return $this->container->make($adapter);
    }

    public function available(string $id): bool
    {
        return (self::PROVIDERS[$id]['adapter'] ?? null) !== null;
    }

    public function name(string $id): string
    {
        return self::PROVIDERS[$id]['name'] ?? $id;
    }

    /**
     * @return list<array{id: string, name: string, company: string, available: bool, keyUrl: string|null}>
     */
    public function forScreen(): array
    {
        $list = [];

        foreach (self::PROVIDERS as $id => $provider) {
            $list[] = [
                'id' => $id,
                'name' => $provider['name'],
                'company' => $provider['company'],
                'available' => $provider['adapter'] !== null,
                'keyUrl' => $provider['keyUrl'] ?? null,
            ];
        }

        return $list;
    }

    /**
     * @return list<string>
     */
    public function availableIds(): array
    {
        return array_keys(array_filter(self::PROVIDERS, fn ($p) => $p['adapter'] !== null));
    }
}

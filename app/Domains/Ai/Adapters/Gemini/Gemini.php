<?php

namespace App\Domains\Ai\Adapters\Gemini;

use App\Domains\Ai\Contracts\AiProvider;
use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Data\Completion;
use App\Domains\Ai\Data\ModelOption;
use App\Domains\Ai\Data\Prompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Gemini pela API do Google AI Studio (generativelanguage.googleapis.com).
 * A chave vai no cabeçalho, nunca na URL, para não parar em log de proxy.
 */
class Gemini implements AiProvider
{
    public const BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function models(string $apiKey): array
    {
        $response = $this->send(fn () => $this->client($apiKey)->get('/models', ['pageSize' => 200]));
        $models = [];

        foreach ($response->json('models', []) as $model) {
            $id = str_replace('models/', '', (string) ($model['name'] ?? ''));

            // Só modelos de texto que respondem a generateContent; ficam de
            // fora os de imagem, áudio e embedding.
            if (! in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true)
                || ! str_starts_with($id, 'gemini-')
                || preg_match('/(image|tts|audio|live|embedding|vision)/', $id)) {
                continue;
            }

            $models[] = new ModelOption($id, (string) ($model['displayName'] ?? $id));
        }

        if ($models === []) {
            throw new AiFailed('no_models');
        }

        return $models;
    }

    public function generate(string $apiKey, string $model, Prompt $prompt): Completion
    {
        $response = $this->send(fn () => $this->client($apiKey)->timeout(90)->post("/models/{$model}:generateContent", [
            'systemInstruction' => ['parts' => [['text' => $prompt->instructions]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt->content]]]],
            'generationConfig' => ['temperature' => 0.2],
        ]));

        $text = collect($response->json('candidates.0.content.parts', []))->pluck('text')->filter()->implode('');

        if (trim($text) === '') {
            throw new AiFailed('blocked');
        }

        return new Completion(
            text: trim($text),
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0),
        );
    }

    /**
     * Prefere o "flash" mais novo: rápido e barato para ler conversa.
     *
     * @param  list<ModelOption>  $models
     */
    public function suggest(array $models): ?string
    {
        $ids = array_map(fn (ModelOption $m) => $m->id, $models);
        $stable = array_values(array_filter($ids, fn ($id) => ! preg_match('/(preview|exp|latest|lite)/', $id)));
        $flash = array_values(array_filter($stable, fn ($id) => str_contains($id, 'flash')));
        $pool = $flash ?: ($stable ?: $ids);
        rsort($pool, SORT_NATURAL);

        return $pool[0] ?? null;
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::baseUrl((string) config('owly.ai.gemini_url', self::BASE))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(20);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            throw new AiFailed('unavailable');
        }

        if ($response->successful()) {
            return $response;
        }

        $reason = (string) $response->json('error.details.0.reason', '');

        throw new AiFailed(match (true) {
            $reason === 'API_KEY_INVALID', in_array($response->status(), [401, 403], true) => 'invalid_key',
            $response->status() === 400 && str_contains((string) $response->json('error.message'), 'API key') => 'invalid_key',
            $response->status() === 429 => 'quota',
            $response->status() >= 500 => 'unavailable',
            default => 'unexpected',
        });
    }
}

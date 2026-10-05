<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

/**
 * Respostas falsas da API do Gemini. Nenhum teste chama a rede.
 */
final class FakeGemini
{
    public const KEY = 'AIzaFakeKeyForTests0000000000000000abcd';

    public static function ok(string $answer = 'O cliente fechou o pedido às 20:00.'): void
    {
        Http::fake([
            '*/models?*' => Http::response(self::models()),
            '*/models' => Http::response(self::models()),
            '*:generateContent' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $answer]]]]],
                'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 40],
            ]),
        ]);
    }

    public static function invalidKey(): void
    {
        Http::fake(['*' => Http::response([
            'error' => [
                'code' => 400,
                'message' => 'API key not valid. Please pass a valid API key.',
                'status' => 'INVALID_ARGUMENT',
                'details' => [['reason' => 'API_KEY_INVALID']],
            ],
        ], 400)]);
    }

    public static function quotaOnGenerate(): void
    {
        Http::fake([
            '*:generateContent' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429),
            '*' => Http::response(self::models()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function models(): array
    {
        return ['models' => [
            ['name' => 'models/gemini-2.5-flash', 'displayName' => 'Gemini 2.5 Flash', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
            ['name' => 'models/gemini-2.5-pro', 'displayName' => 'Gemini 2.5 Pro', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-3-flash-preview', 'displayName' => 'Gemini 3 Flash Preview', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-2.5-flash-image', 'displayName' => 'Nano Banana', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/text-embedding-004', 'displayName' => 'Embedding', 'supportedGenerationMethods' => ['embedContent']],
        ]];
    }
}

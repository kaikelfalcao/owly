<?php

namespace Tests\Feature\Ai;

use App\Domains\Ai\Adapters\Gemini\Gemini;
use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Data\Prompt;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGemini;
use Tests\TestCase;

class GeminiTest extends TestCase
{
    public function test_lista_so_modelos_de_texto_e_sugere_o_flash_estavel(): void
    {
        FakeGemini::ok();
        $gemini = new Gemini;

        $models = $gemini->models(FakeGemini::KEY);

        $this->assertSame(['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-3-flash-preview'], array_map(fn ($m) => $m->id, $models));
        $this->assertSame('gemini-2.5-flash', $gemini->suggest($models));
    }

    public function test_a_chave_vai_no_cabecalho_e_nunca_na_url(): void
    {
        FakeGemini::ok();

        (new Gemini)->generate(FakeGemini::KEY, 'gemini-2.5-flash', new Prompt('instruções', 'conversa'));

        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', FakeGemini::KEY)
            && ! str_contains($request->url(), FakeGemini::KEY)
            && str_ends_with($request->url(), '/models/gemini-2.5-flash:generateContent')
            && $request['systemInstruction']['parts'][0]['text'] === 'instruções');
    }

    public function test_devolve_o_texto_e_o_consumo(): void
    {
        FakeGemini::ok('Resposta inventada');

        $completion = (new Gemini)->generate(FakeGemini::KEY, 'gemini-2.5-flash', new Prompt('a', 'b'));

        $this->assertSame(['Resposta inventada', 1200, 40], [$completion->text, $completion->inputTokens, $completion->outputTokens]);
    }

    public function test_chave_recusada(): void
    {
        FakeGemini::invalidKey();

        $this->assertFailsWith('invalid_key', fn () => (new Gemini)->models('chave-errada-000000000000'));
    }

    public function test_limite_de_uso(): void
    {
        FakeGemini::quotaOnGenerate();

        $this->assertFailsWith('quota', fn () => (new Gemini)->generate(FakeGemini::KEY, 'gemini-2.5-flash', new Prompt('a', 'b')));
    }

    public function test_provedor_fora_do_ar(): void
    {
        Http::fake(['*' => Http::response('', 503)]);

        $this->assertFailsWith('unavailable', fn () => (new Gemini)->models(FakeGemini::KEY));
    }

    public function test_resposta_vazia_e_recusa(): void
    {
        Http::fake(['*' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]])]);

        $this->assertFailsWith('blocked', fn () => (new Gemini)->generate(FakeGemini::KEY, 'gemini-2.5-flash', new Prompt('a', 'b')));
    }

    private function assertFailsWith(string $reason, callable $call): void
    {
        try {
            $call();
            $this->fail('Deveria ter falhado.');
        } catch (AiFailed $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}

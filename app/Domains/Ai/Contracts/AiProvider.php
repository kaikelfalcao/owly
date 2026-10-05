<?php

namespace App\Domains\Ai\Contracts;

use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Data\Completion;
use App\Domains\Ai\Data\ModelOption;
use App\Domains\Ai\Data\Prompt;

/**
 * Um provedor de IA. Quem pergunta não sabe qual está ligado; provedor novo
 * é mais um adaptador em Adapters/ e uma linha em ProviderCatalog.
 */
interface AiProvider
{
    /**
     * Os modelos que a chave pode usar. Serve também para testar a chave.
     *
     * @return list<ModelOption>
     *
     * @throws AiFailed
     */
    public function models(string $apiKey): array;

    /**
     * O modelo que o assistente já deixa escolhido.
     *
     * @param  list<ModelOption>  $models
     */
    public function suggest(array $models): ?string;

    /**
     * @throws AiFailed
     */
    public function generate(string $apiKey, string $model, Prompt $prompt): Completion;
}

<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Data\ModelOption;
use App\Domains\Ai\Models\AiConnection;
use App\Domains\Ai\ProviderCatalog;
use App\Platform\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O assistente de conexão: testa a chave, lista os modelos e grava a conexão.
 * A primeira conexão da empresa vira a padrão.
 */
class ConnectProvider
{
    public function __construct(
        private readonly ProviderCatalog $catalog,
        private readonly Audit $audit,
    ) {}

    /**
     * @return array{models: list<array{id: string, name: string}>, suggested: string|null}
     */
    public function verify(string $provider, string $apiKey): array
    {
        $models = $this->models($provider, $apiKey);

        return [
            'models' => array_map(fn (ModelOption $m) => $m->toArray(), $models),
            'suggested' => $this->catalog->provider($provider)->suggest($models),
        ];
    }

    public function connect(int $organizationId, int $userId, string $provider, string $apiKey, string $model, string $label): AiConnection
    {
        $models = array_map(fn (ModelOption $m) => $m->id, $this->models($provider, $apiKey));

        if (! in_array($model, $models, true)) {
            throw ValidationException::withMessages(['model' => 'Escolha um dos modelos da lista.']);
        }

        $connection = DB::transaction(function () use ($organizationId, $userId, $provider, $apiKey, $model, $label) {
            $first = ! AiConnection::where('organization_id', $organizationId)->exists();

            return AiConnection::create([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'provider' => $provider,
                'label' => $label,
                'api_key' => $apiKey,
                'key_hint' => substr($apiKey, -4),
                'model' => $model,
                'is_default' => $first,
                'last_checked_at' => now(),
            ]);
        });

        $this->audit->record('ai.connection_created', $connection, ['provider' => $provider]);

        return $connection;
    }

    public function makeDefault(AiConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            AiConnection::where('organization_id', $connection->organization_id)->update(['is_default' => false]);
            $connection->update(['is_default' => true]);
        });

        $this->audit->record('ai.connection_default', $connection, ['provider' => $connection->provider]);
    }

    public function remove(AiConnection $connection): void
    {
        DB::transaction(function () use ($connection) {
            $connection->delete();

            // Sem padrão, a mais antiga que sobrou assume.
            if ($connection->is_default) {
                AiConnection::where('organization_id', $connection->organization_id)
                    ->oldest('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });

        $this->audit->record('ai.connection_removed', $connection, ['provider' => $connection->provider]);
    }

    /**
     * @return list<ModelOption>
     */
    private function models(string $provider, string $apiKey): array
    {
        if (! $this->catalog->available($provider)) {
            throw ValidationException::withMessages(['provider' => 'Esse provedor ainda não está disponível.']);
        }

        try {
            return $this->catalog->provider($provider)->models($apiKey);
        } catch (AiFailed $e) {
            throw ValidationException::withMessages(['api_key' => $e->getMessage()]);
        }
    }
}

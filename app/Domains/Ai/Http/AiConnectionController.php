<?php

namespace App\Domains\Ai\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Ai\Models\AiConnection;
use App\Domains\Ai\ProviderCatalog;
use App\Domains\Ai\Services\ConnectProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiConnectionController extends Controller
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly ProviderCatalog $catalog,
        private readonly ConnectProvider $connect,
    ) {}

    public function index(): Response
    {
        $connections = AiConnection::where('organization_id', $this->organization->id())
            ->orderByDesc('is_default')
            ->oldest('id')
            ->get()
            ->map(fn (AiConnection $c) => [
                'id' => $c->id,
                'provider' => $c->provider,
                'providerName' => $this->catalog->name($c->provider),
                'label' => $c->label,
                'model' => $c->model,
                'keyHint' => '••••'.$c->key_hint,
                'isDefault' => $c->is_default,
                'createdAt' => $c->created_at->toIso8601String(),
            ]);

        return Inertia::render('ai/index', ['connections' => $connections]);
    }

    public function create(): Response
    {
        return Inertia::render('ai/connect', ['providers' => $this->catalog->forScreen()]);
    }

    /**
     * Passo "testar" do assistente: a chave não é guardada aqui, só testada.
     */
    public function verify(Request $request): RedirectResponse
    {
        $data = $this->validateKey($request);

        Inertia::flash('verified', $this->connect->verify($data['provider'], $data['api_key']));

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateKey($request, [
            'model' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:80'],
        ]);

        $this->connect->connect(
            $this->organization->id(),
            $request->user()->id,
            $data['provider'],
            $data['api_key'],
            $data['model'],
            trim($data['label']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'IA conectada. Já dá para perguntar sobre as conversas.']);

        return to_route('ai.index');
    }

    public function makeDefault(int $connection): RedirectResponse
    {
        $this->connect->makeDefault($this->find($connection));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agora as perguntas vão para esta conexão.']);

        return back();
    }

    public function destroy(int $connection): RedirectResponse
    {
        $this->connect->remove($this->find($connection));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexão removida. A chave foi apagada da Owly.']);

        return back();
    }

    private function find(int $id): AiConnection
    {
        return AiConnection::where('organization_id', $this->organization->id())->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    private function validateKey(Request $request, array $extra = []): array
    {
        return $request->validate([
            'provider' => ['required', Rule::in($this->catalog->availableIds())],
            'api_key' => ['required', 'string', 'min:20', 'max:200', 'regex:/^\S+$/'],
            ...$extra,
        ], [
            'provider.required' => 'Escolha um provedor.',
            'provider.in' => 'Esse provedor ainda não está disponível.',
            'api_key.required' => 'Cole a chave do provedor.',
            'api_key.min' => 'A chave parece incompleta. Copie de novo, inteira.',
            'api_key.max' => 'Isso não parece uma chave. Copie só a chave.',
            'api_key.regex' => 'A chave não tem espaços. Copie de novo, só a chave.',
            'model.required' => 'Escolha um modelo.',
            'label.required' => 'Dê um nome para a conexão.',
            'label.max' => 'Use um nome com até 80 letras.',
        ]);
    }
}

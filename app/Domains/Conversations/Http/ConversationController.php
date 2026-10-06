<?php

namespace App\Domains\Conversations\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Conversations\Contracts\ConversationPanels;
use App\Domains\Conversations\Services\ConversationQueries;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationQueries $queries,
        private readonly CurrentOrganization $organization,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('busca')->toString() ?: null;

        return Inertia::render('conversations/index', [
            'conversations' => $this->queries->list($this->organization->id(), $search),
            'search' => $search,
        ]);
    }

    public function show(Request $request, int $conversation, ConversationPanels $panels): Response
    {
        $organizationId = $this->organization->id();
        $page = $this->queries->show($organizationId, $conversation);

        return Inertia::render('conversations/show', [
            ...$page,
            'panels' => $panels->for($organizationId, $conversation),
            'origin' => self::origin($request),
        ]);
    }

    /**
     * De onde o dono veio (a lista com a busca, ou uma leitura do painel com
     * o período e o filtro), para a trilha e o anterior/seguinte levarem de
     * volta ao mesmo lugar. Só repassa: quem entende a leitura é o painel.
     *
     * @return array<string, string>|null
     */
    private static function origin(Request $request): ?array
    {
        $text = fn (string $key, string $pattern) => preg_match($pattern, $value = $request->string($key)->toString()) ? $value : null;

        $insight = $text('painel', '/^[a-z-]{1,40}$/');

        if ($insight !== null) {
            return array_filter([
                'painel' => $insight,
                'periodo' => $text('periodo', '/^[a-z0-9]{1,10}$/'),
                'filtro' => $text('filtro', '/^.{1,100}$/u'),
            ]);
        }

        $list = array_filter([
            'busca' => $text('busca', '/^.{1,100}$/u'),
            'page' => $text('page', '/^[1-9]\d{0,5}$/'),
        ]);

        return $list === [] ? null : $list;
    }
}

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

    public function show(int $conversation, ConversationPanels $panels): Response
    {
        $organizationId = $this->organization->id();
        $page = $this->queries->show($organizationId, $conversation);

        return Inertia::render('conversations/show', [
            ...$page,
            'panels' => $panels->for($organizationId, $conversation),
        ]);
    }
}

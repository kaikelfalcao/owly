<?php

namespace App\Domains\Insights\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Insights\Models\Opportunity;
use App\Domains\Insights\Services\DecideOpportunity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly DecideOpportunity $decide,
        private readonly CurrentOrganization $organization,
    ) {}

    public function win(Request $request, int $opportunity): RedirectResponse
    {
        return $this->decided($request, $opportunity, Opportunity::WON, 'Marcada como ganha.');
    }

    public function lose(Request $request, int $opportunity): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(array_keys(Opportunity::LOSS_REASONS))],
        ], [
            'reason.required' => 'Escolha o motivo da perda.',
            'reason.in' => 'Escolha um dos motivos da lista.',
        ]);

        return $this->decided($request, $opportunity, Opportunity::LOST, 'Marcada como perdida.', $data['reason']);
    }

    public function discard(Request $request, int $opportunity): RedirectResponse
    {
        return $this->decided($request, $opportunity, Opportunity::DISCARDED, 'Marcada como "não era oportunidade". Ela sai das contas.');
    }

    public function reopen(Request $request, int $opportunity): RedirectResponse
    {
        return $this->decided($request, $opportunity, Opportunity::OPEN, 'Oportunidade aberta de novo.');
    }

    private function decided(Request $request, int $opportunity, string $status, string $message, ?string $reason = null): RedirectResponse
    {
        $this->decide->decide($this->organization->id(), $request->user()->id, $opportunity, $status, $reason);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

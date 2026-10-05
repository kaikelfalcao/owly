<?php

namespace App\Domains\Insights\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Insights\Services\Dashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly Dashboard $dashboard,
        private readonly CurrentOrganization $organization,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('dashboard', $this->dashboard->overview(
            $this->organization->calendar(),
            $request->string('periodo')->toString() ?: null,
        ));
    }

    public function show(Request $request, string $insight): Response
    {
        return Inertia::render('insights/show', $this->dashboard->detail(
            $this->organization->calendar(),
            $insight,
            $request->string('periodo')->toString() ?: null,
            $request->string('filtro')->toString() ?: null,
        ));
    }
}

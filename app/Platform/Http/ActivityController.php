<?php

namespace App\Platform\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Audit\AuditCatalog;
use App\Platform\Audit\AuditEntry;
use App\Platform\Audit\AuditSearch;
use App\Platform\Audit\Outcome;
use App\Platform\Audit\Severity;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Minha conta › Atividade: a auditoria da empresa, mais recente primeiro,
 * com filtros, detalhes de cada registro e exportação do que foi filtrado.
 * Só leitura: a aplicação não tem rota que altere ou apague a auditoria.
 */
class ActivityController extends Controller
{
    private const PER_PAGE = 25;

    public function index(ActivityRequest $request, CurrentOrganization $current): Response
    {
        $organization = $current->get();
        $filters = $request->filters();
        $users = $this->users($organization->id);
        $row = new ActivityRow($users);

        $entries = (new AuditSearch($organization->id, $organization->timezone))
            ->query($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditEntry $entry) => $row->toArray($entry));

        return Inertia::render('settings/activity', [
            'entries' => $entries,
            'filters' => (object) $filters,
            'timezone' => $organization->timezone,
            'options' => [
                'users' => $users->map(fn (string $name, int $id) => ['value' => (string) $id, 'label' => $name])->values(),
                'actions' => collect(AuditCatalog::actions())->map(fn (string $label, string $code) => ['value' => $code, 'label' => $label])->values(),
                'resources' => collect(AuditCatalog::resources())->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])->values(),
                'results' => collect(Outcome::cases())->map(fn (Outcome $outcome) => ['value' => $outcome->value, 'label' => $outcome->label()]),
                'severities' => collect(Severity::cases())->map(fn (Severity $severity) => ['value' => $severity->value, 'label' => $severity->label()]),
            ],
        ]);
    }

    /**
     * Planilha (CSV) com tudo o que os filtros acham, não só a página aberta.
     * A própria exportação entra na auditoria antes de o arquivo sair.
     */
    public function export(ActivityRequest $request, CurrentOrganization $current, Audit $audit): StreamedResponse
    {
        $organization = $current->get();
        $filters = $request->filters();
        $query = (new AuditSearch($organization->id, $organization->timezone))->query($filters);
        $row = new ActivityRow($this->users($organization->id));

        // Guarda o tamanho e o tipo de filtro, nunca o texto buscado.
        $audit->record('audit.exported', organizationId: $organization->id, meta: [
            'rows' => (clone $query)->count(),
            'period' => $filters['period'] ?? 'all',
            'filtered' => Arr::except($filters, ['period', 'from', 'to']) !== [],
        ]);

        $timezone = $organization->timezone;
        $filename = 'atividade-owly-'.now($timezone)->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $row, $timezone) {
            $out = fopen('php://output', 'w');
            // BOM e ponto e vírgula: o Excel em português abre com acentos e colunas certos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ActivityRow::csvHeader(), ';', '"', '');

            foreach ($query->lazyByIdDesc(500) as $entry) {
                fputcsv($out, $row->toCsv($entry, $timezone), ';', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Quem pode aparecer na tela: só os logins da própria empresa.
     *
     * @return Collection<int, string>
     */
    private function users(int $organizationId): Collection
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}

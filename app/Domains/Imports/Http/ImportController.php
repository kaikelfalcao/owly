<?php

namespace App\Domains\Imports\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Imports\Data\ImportFailed;
use App\Domains\Imports\Models\Import;
use App\Domains\Imports\Services\StartImport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(CurrentOrganization $organization): Response
    {
        $imports = Import::where('organization_id', $organization->id())
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Import $import) => [
                'id' => $import->id,
                'status' => $import->status,
                'fileName' => $import->file_name,
                'fileSize' => $import->file_size,
                'stats' => $import->stats,
                'problems' => $import->problems ?? [],
                'error' => $import->error_code ? (ImportFailed::MESSAGES[$import->error_code] ?? ImportFailed::MESSAGES['unexpected']) : null,
                'createdAt' => $import->created_at->toIso8601String(),
                'finishedAt' => $import->finished_at?->toIso8601String(),
            ]);

        return Inertia::render('imports/index', [
            'imports' => $imports,
            'maxSizeMb' => (int) (config('owly.imports.max_kb') / 1024),
        ]);
    }

    public function store(Request $request, StartImport $start, CurrentOrganization $organization): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:'.config('owly.imports.max_kb')],
        ], [
            'file.required' => 'Escolha o arquivo .zip exportado do WhatsApp.',
            'file.mimes' => 'O arquivo precisa ser um .zip.',
            'file.max' => 'O arquivo passa do tamanho máximo.',
            'file.uploaded' => 'Não deu para enviar o arquivo. Ele pode ser grande demais para o servidor.',
        ]);

        $start->handle(
            $request->file('file'),
            $organization->id(),
            $request->user()->id,
            $organization->get()->timezone,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recebemos o zip. Avisamos no sino quando terminar.']);

        return back();
    }
}

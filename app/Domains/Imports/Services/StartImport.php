<?php

namespace App\Domains\Imports\Services;

use App\Domains\Imports\Contracts\Importer;
use App\Domains\Imports\Jobs\ProcessImport;
use App\Domains\Imports\Models\Import;
use App\Platform\Audit\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda o zip, registra a importação e manda processar em segundo plano.
 */
class StartImport
{
    public function __construct(
        private readonly Importer $importer,
        private readonly Audit $audit,
    ) {}

    public function handle(UploadedFile $file, int $organizationId, int $userId, string $timezone): Import
    {
        $hash = hash_file('sha256', $file->getRealPath());

        $previous = Import::where('organization_id', $organizationId)
            ->where('file_hash', $hash)
            ->where('status', '!=', Import::FAILED)
            ->latest('id')
            ->first();

        if ($previous) {
            throw ValidationException::withMessages([
                'file' => 'Este zip já foi importado em '.$previous->created_at->setTimezone($timezone)->format('d/m/Y \à\s H:i').'.',
            ]);
        }

        $import = Import::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'format' => $this->importer->format(),
            'status' => Import::PENDING,
            'file_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'path' => $file->storeAs("imports/{$organizationId}", Str::uuid().'.zip', 'local'),
        ]);

        $this->audit->record('imports.started', $import, ['size' => $import->file_size]);

        ProcessImport::dispatch($import->id, $timezone)->afterCommit();

        return $import;
    }
}

<?php

namespace App\Domains\Imports\Services;

use App\Domains\Imports\Contracts\ImportHealth;
use App\Domains\Imports\Data\ImportSummary;
use App\Domains\Imports\Models\Import;

class LatestImportHealth implements ImportHealth
{
    public function latest(int $organizationId): ?ImportSummary
    {
        $import = Import::where('organization_id', $organizationId)
            ->where('status', Import::DONE)
            ->latest('id')
            ->first();

        if ($import === null) {
            return null;
        }

        return new ImportSummary(
            finishedAt: ($import->finished_at ?? $import->created_at)->toIso8601String(),
            firstAt: $import->stats['first_at'] ?? null,
            lastAt: $import->stats['last_at'] ?? null,
            gaps: $import->stats['gaps'] ?? [],
            problems: count($import->problems ?? []),
        );
    }
}

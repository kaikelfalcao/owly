<?php

namespace App\Domains\Imports\Contracts;

use App\Domains\Imports\Data\ImportSummary;

interface ImportHealth
{
    public function latest(int $organizationId): ?ImportSummary;
}

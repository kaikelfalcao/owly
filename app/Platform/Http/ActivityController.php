<?php

namespace App\Platform\Http;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditEntry;
use App\Platform\Audit\AuditLabels;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minha conta › Atividade: a auditoria da empresa, mais recente primeiro.
 */
class ActivityController extends Controller
{
    public function __invoke(): Response
    {
        $entries = AuditEntry::query()
            ->where('organization_id', Context::get('organization_id'))
            ->latest('id')
            ->paginate(30)
            ->through(fn (AuditEntry $entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'label' => AuditLabels::for($entry->action),
                'tone' => AuditLabels::tone($entry->action),
                'ip' => $entry->ip,
                'at' => $entry->created_at->toIso8601String(),
            ]);

        return Inertia::render('settings/activity', [
            'entries' => $entries,
        ]);
    }
}

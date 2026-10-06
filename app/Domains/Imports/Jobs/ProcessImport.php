<?php

namespace App\Domains\Imports\Jobs;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Accounts\Jobs\ForOrganization;
use App\Domains\Conversations\Contracts\ConversationStore;
use App\Domains\Imports\Contracts\Importer;
use App\Domains\Imports\Data\ImportFailed;
use App\Domains\Imports\Models\Import;
use App\Domains\Imports\Rules\DateGaps;
use App\Models\User;
use App\Platform\Audit\Audit;
use App\Platform\Notifications\Notice;
use App\Platform\Telemetry\Telemetry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

/**
 * Lê o zip e grava as conversas. Pode rodar de novo sem duplicar nada: a
 * gravação ignora mensagens que já existem.
 */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public int $timeout = 600;

    public function __construct(public int $organizationId, public int $importId, public string $timezone) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ForOrganization($this->organizationId)];
    }

    public function handle(Importer $importer, ConversationStore $store, Audit $audit, Telemetry $telemetry): void
    {
        $import = Import::findOrFail($this->importId);
        Context::add('organization_id', $import->organization_id);
        Context::add('import_id', $import->id);

        $import->update(['status' => Import::RUNNING, 'started_at' => now(), 'error_code' => null]);
        $started = hrtime(true);
        $span = $telemetry->tracer()->spanBuilder('import')->setAttribute('owly.import_id', $import->id)->startSpan();
        $scope = $span->activate();

        try {
            $reading = $importer->read(Storage::disk('local')->path((string) $import->path), $this->timezone);

            $stats = ['files' => $reading->files, 'conversations_new' => 0, 'conversations_updated' => 0, 'messages_new' => 0, 'messages_known' => 0];
            $days = [];
            $first = null;
            $last = null;

            foreach ($reading->conversations as $conversation) {
                $result = $store->store($import->organization_id, 'zip', $conversation);

                if ($result->created) {
                    $stats['conversations_new']++;
                } elseif ($result->newMessages > 0) {
                    $stats['conversations_updated']++;
                }

                $stats['messages_new'] += $result->newMessages;
                $stats['messages_known'] += $result->knownMessages;

                foreach ($conversation->messages as $message) {
                    $days[$message->sentAt->setTimezone($this->timezone)->toDateString()] = true;
                    $first = $first === null || $message->sentAt < $first ? $message->sentAt : $first;
                    $last = $last === null || $message->sentAt > $last ? $message->sentAt : $last;
                }
            }

            $stats['first_at'] = $first?->toIso8601String();
            $stats['last_at'] = $last?->toIso8601String();
            $stats['gaps'] = DateGaps::find(array_keys($days));

            $import->update([
                'status' => Import::DONE,
                'stats' => $stats,
                'problems' => $reading->problems,
                'finished_at' => now(),
            ]);
            $this->discardFile($import);

            $audit->record('imports.finished', $import, [
                'files' => $stats['files'],
                'conversations_new' => $stats['conversations_new'],
                'messages_new' => $stats['messages_new'],
                'problems' => count($reading->problems),
            ], userId: $import->user_id, organizationId: $import->organization_id);

            Log::info('importação concluída', ['import_id' => $import->id, 'messages_new' => $stats['messages_new'], 'problems' => count($reading->problems)]);

            $this->notify($import, new Notice(
                kind: 'import',
                title: 'Importação concluída',
                body: self::summary($stats),
                url: '/importar',
            ));
            $span->setAttribute('owly.messages_new', $stats['messages_new']);
            $this->measure($telemetry, $started, 'done');
        } catch (ImportFailed $e) {
            $this->fail($import, $e->reason, $audit);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->reason);
            $this->measure($telemetry, $started, 'failed');
        } finally {
            $scope->detach();
            $span->end();
            Context::forget('import_id');
        }
    }

    /**
     * Esgotou as tentativas por um erro inesperado.
     */
    public function failed(Throwable $e): void
    {
        // O middleware não envolve o failed(): a empresa entra aqui à mão.
        app(CurrentOrganization::class)->runAs($this->organizationId, function (): void {
            $import = Import::find($this->importId);

            if ($import !== null && $import->status !== Import::FAILED) {
                $this->fail($import, 'unexpected', app(Audit::class));
            }
        });

        Log::error('importação falhou', ['import_id' => $this->importId, 'error' => $e::class]);
    }

    /**
     * "12 conversas novas, 340 mensagens." para o sino.
     *
     * @param  array<string, mixed>  $stats
     */
    public static function summary(array $stats): string
    {
        $conversations = (int) $stats['conversations_new'];
        $messages = (int) $stats['messages_new'];

        if ($messages === 0) {
            return 'Nenhuma mensagem nova: tudo já estava na Owly.';
        }

        return sprintf(
            '%d %s, %d %s.',
            $conversations,
            $conversations === 1 ? 'conversa nova' : 'conversas novas',
            $messages,
            $messages === 1 ? 'mensagem nova' : 'mensagens novas',
        );
    }

    private function fail(Import $import, string $code, Audit $audit): void
    {
        $import->update(['status' => Import::FAILED, 'error_code' => $code, 'finished_at' => now()]);
        $this->discardFile($import);

        $audit->record('imports.failed', $import, ['code' => $code], userId: $import->user_id, organizationId: $import->organization_id);

        $this->notify($import, new Notice(
            kind: 'failed',
            title: 'A importação não deu certo',
            body: ImportFailed::MESSAGES[$code] ?? ImportFailed::MESSAGES['unexpected'],
            url: '/importar',
        ));
    }

    private function discardFile(Import $import): void
    {
        if ($import->path !== null) {
            Storage::disk('local')->delete($import->path);
            $import->update(['path' => null]);
        }
    }

    private function notify(Import $import, Notice $notice): void
    {
        User::find($import->user_id)?->notify($notice);
    }

    private function measure(Telemetry $telemetry, int $started, string $outcome): void
    {
        $telemetry->histogram('owly.imports.duration', 's', 'Duração das importações')
            ->record((hrtime(true) - $started) / 1e9, ['outcome' => $outcome]);
    }
}

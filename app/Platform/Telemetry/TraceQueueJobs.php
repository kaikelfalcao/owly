<?php

namespace App\Platform\Telemetry;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

/**
 * Cada tarefa da fila vira um rastro, com duração medida. O request_id da
 * requisição que disparou a tarefa já vem no contexto do Laravel.
 */
class TraceQueueJobs
{
    /** @var array<string, array{span: SpanInterface, scope: ScopeInterface, started: int}> */
    private array $running = [];

    public function __construct(private readonly Telemetry $telemetry) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            JobProcessing::class => 'started',
            JobProcessed::class => 'finished',
            JobFailed::class => 'failed',
        ];
    }

    public function started(JobProcessing $event): void
    {
        $name = $this->name($event->job);

        $span = $this->telemetry->tracer()
            ->spanBuilder("job {$name}")
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttribute('messaging.destination.name', $event->job->getQueue())
            ->setAttribute('owly.job', $name)
            ->setAttribute('owly.job.attempt', $event->job->attempts())
            ->startSpan();

        Context::add('job', $name);

        $this->running[$this->key($event->job)] = [
            'span' => $span,
            'scope' => $span->activate(),
            'started' => hrtime(true),
        ];
    }

    public function finished(JobProcessed $event): void
    {
        $this->end($event->job, 'ok');
    }

    public function failed(JobFailed $event): void
    {
        $span = $this->running[$this->key($event->job)]['span'] ?? null;
        $span?->recordException($event->exception)->setStatus(StatusCode::STATUS_ERROR);

        $this->end($event->job, 'failed');
    }

    private function end(Job $job, string $outcome): void
    {
        $running = $this->running[$this->key($job)] ?? null;

        if ($running === null) {
            return;
        }

        unset($this->running[$this->key($job)]);

        $running['scope']->detach();
        $running['span']->end();

        $this->telemetry
            ->histogram('owly.queue.job.duration', 's', 'Duração das tarefas da fila')
            ->record((hrtime(true) - $running['started']) / 1e9, [
                'owly.job' => $this->name($job),
                'owly.job.outcome' => $outcome,
            ]);

        Context::forget('job');
        $this->telemetry->flush();
    }

    private function key(Job $job): string
    {
        return $job->getConnectionName().':'.$job->getJobId();
    }

    private function name(Job $job): string
    {
        return class_basename($job->resolveName());
    }
}

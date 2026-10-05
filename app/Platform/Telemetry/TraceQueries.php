<?php

namespace App\Platform\Telemetry;

use Illuminate\Database\Events\QueryExecuted;
use OpenTelemetry\API\Trace\SpanKind;

/**
 * Cada consulta ao banco vira uma etapa do rastro em andamento. Vai o SQL
 * com os `?`, nunca os valores: eles podem ter dado de cliente.
 */
class TraceQueries
{
    public function __construct(private readonly Telemetry $telemetry) {}

    public function handle(QueryExecuted $event): void
    {
        $ended = (int) (microtime(true) * 1e9);
        $started = $ended - (int) ($event->time * 1e6);
        $operation = strtoupper(strtok(ltrim($event->sql), " \n\t(") ?: 'QUERY');

        $this->telemetry->tracer()
            ->spanBuilder("db {$operation}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($started)
            ->setAttribute('db.system.name', $event->connection->getDriverName())
            ->setAttribute('db.operation.name', $operation)
            ->setAttribute('db.query.text', $event->sql)
            ->startSpan()
            ->end($ended);
    }
}

<?php

namespace App\Platform\Telemetry;

use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\Noop\NoopMeterProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;

/**
 * Ponto único de rastros e métricas. O resto do código pede o tracer e o
 * meter aqui e não sabe para onde os dados vão (Grafana, outro serviço ou
 * lugar nenhum, quando não há endereço configurado).
 */
class Telemetry
{
    /** @var array<string, HistogramInterface> */
    private array $histograms = [];

    public function __construct(
        private readonly TracerProviderInterface $tracers = new NoopTracerProvider,
        private readonly MeterProviderInterface $meters = new NoopMeterProvider,
    ) {}

    /**
     * Falso quando não há para onde mandar: nada é medido.
     */
    public function enabled(): bool
    {
        return ! $this->tracers instanceof NoopTracerProvider;
    }

    public function tracer(): TracerInterface
    {
        return $this->tracers->getTracer('owly');
    }

    public function meter(): MeterInterface
    {
        return $this->meters->getMeter('owly');
    }

    /**
     * Histograma reaproveitado entre chamadas, para não recriar o
     * instrumento a cada requisição.
     */
    public function histogram(string $name, string $unit, string $description): HistogramInterface
    {
        return $this->histograms[$name] ??= $this->meter()->createHistogram($name, $unit, $description);
    }

    /**
     * Envia o que estiver pendente. Chamado no fim da requisição e de cada
     * tarefa da fila.
     */
    public function flush(): void
    {
        if (method_exists($this->tracers, 'forceFlush')) {
            $this->tracers->forceFlush();
        }

        if (method_exists($this->meters, 'forceFlush')) {
            $this->meters->forceFlush();
        }
    }
}

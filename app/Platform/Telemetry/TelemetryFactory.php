<?php

namespace App\Platform\Telemetry;

use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Monta a Telemetry a partir da configuração. Sem endereço OTLP, tudo vira
 * no-op: nada é medido nem enviado, e os testes nunca chamam a rede.
 */
class TelemetryFactory
{
    /**
     * @param  array{endpoint: ?string, service: string, environment: string}  $config
     */
    public static function fromConfig(array $config): Telemetry
    {
        $endpoint = rtrim((string) $config['endpoint'], '/');

        if ($endpoint === '') {
            return new Telemetry;
        }

        $transports = new OtlpHttpTransportFactory;

        return self::make(
            new SpanExporter($transports->create("{$endpoint}/v1/traces", ContentTypes::PROTOBUF, timeout: 2.0)),
            new MetricExporter($transports->create("{$endpoint}/v1/metrics", ContentTypes::PROTOBUF, timeout: 2.0)),
            $config['service'],
            $config['environment'],
        );
    }

    /**
     * Também usado pelos testes, com exportadores em memória.
     */
    public static function make(
        SpanExporterInterface $spans,
        MetricExporterInterface $metrics,
        string $service = 'owly',
        string $environment = 'testing',
    ): Telemetry {
        $resource = ResourceInfo::create(Attributes::create([
            'service.name' => $service,
            'deployment.environment.name' => $environment,
        ]));

        // Os rastros saem em lote no flush do fim da requisição, e não um
        // envio por consulta ao banco.
        $tracers = new TracerProvider((new BatchSpanProcessorBuilder($spans))->build(), resource: $resource);

        $meters = (new MeterProviderBuilder)
            ->setResource($resource)
            ->addReader(new ExportingReader($metrics))
            ->build();

        return new Telemetry($tracers, $meters);
    }
}

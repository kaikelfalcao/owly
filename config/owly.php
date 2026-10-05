<?php

return [

    /*
    | Rastros e métricas (docs/observabilidade.md). Sem endereço, a
    | telemetria fica desligada e nada sai da aplicação.
    */
    'telemetry' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
        'service' => env('OTEL_SERVICE_NAME', 'owly'),
        'environment' => env('APP_ENV', 'production'),
    ],

];

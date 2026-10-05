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

    /*
    | Importação de conversas (docs/escopo.md, passo 4).
    */
    'imports' => [
        'format' => env('OWLY_IMPORT_FORMAT', 'whatsapp_xlsx_zip'),
        // O servidor também precisa aceitar arquivos deste tamanho
        // (upload_max_filesize e post_max_size do PHP).
        'max_kb' => (int) env('OWLY_IMPORT_MAX_KB', 51200),
    ],

];

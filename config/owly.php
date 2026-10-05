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

    /*
    | IA (docs/ia.md). O endereço do Gemini só muda para um proxy da empresa
    | ou um servidor falso em dev.
    */
    'ai' => [
        'gemini_url' => env('OWLY_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    /*
    | Painel (docs/insights.md). Temas que o cliente procura, por palavras.
    | O padrão é de gráfica rápida; outro ramo troca a lista.
    */
    'insights' => [
        'stalled_quote_days' => 2,
        'topics' => [
            'cartao' => ['label' => 'Cartão de visita', 'pattern' => '/cart(ã|a)o|cart(õ|o)es/iu'],
            'panfleto' => ['label' => 'Panfleto e flyer', 'pattern' => '/panfleto|flyer|folheto/iu'],
            'banner' => ['label' => 'Banner e lona', 'pattern' => '/banner|lona|faixa/iu'],
            'adesivo' => ['label' => 'Adesivo e etiqueta', 'pattern' => '/adesivo|etiqueta|r(ó|o)tulo/iu'],
            'convite' => ['label' => 'Convite', 'pattern' => '/convite/iu'],
            'camisa' => ['label' => 'Camisa e brinde', 'pattern' => '/camis(a|eta)|caneca|brinde|sacola/iu'],
            'placa' => ['label' => 'Placa e fachada', 'pattern' => '/placa|fachada|ps\b|acr(í|i)lico/iu'],
            'impressao' => ['label' => 'Impressão e cópia', 'pattern' => '/impress(ã|a)o|imprimir|c(ó|o)pia|xerox|encaderna/iu'],
        ],
    ],

];

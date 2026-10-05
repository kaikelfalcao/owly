<?php

namespace App\Domains\Imports;

use App\Domains\Imports\Adapters\WhatsAppXlsxZip\WhatsAppXlsxZip;
use App\Domains\Imports\Contracts\Importer;
use Illuminate\Support\ServiceProvider;

class ImportsServiceProvider extends ServiceProvider
{
    /**
     * Formatos aceitos. Formato novo (exportação nativa do WhatsApp, outro
     * CRM) entra aqui como mais um adaptador.
     */
    private const FORMATS = [
        'whatsapp_xlsx_zip' => WhatsAppXlsxZip::class,
    ];

    public function register(): void
    {
        $this->app->bind(Importer::class, fn ($app) => $app->make(
            self::FORMATS[$app['config']->get('owly.imports.format')] ?? WhatsAppXlsxZip::class,
        ));
    }
}

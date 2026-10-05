<?php

namespace App\Domains\Imports\Contracts;

use App\Domains\Imports\Data\ImportFailed;
use App\Domains\Imports\Data\ImportReading;

/**
 * Um formato de arquivo que vira conversas. Cada formato é um adaptador em
 * `Adapters/`; hoje só o zip de planilhas do WhatsApp.
 */
interface Importer
{
    /** Código curto do formato, gravado na importação. */
    public function format(): string;

    /**
     * Lê o arquivo inteiro. Datas saem em UTC, interpretadas no fuso da empresa.
     *
     * @throws ImportFailed quando o arquivo não serve
     */
    public function read(string $path, string $timezone): ImportReading;
}

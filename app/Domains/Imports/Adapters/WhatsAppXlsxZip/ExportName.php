<?php

namespace App\Domains\Imports\Adapters\WhatsAppXlsxZip;

/**
 * A ferramenta de exportação troca emojis do nome do arquivo por "#Uxxxx".
 */
final class ExportName
{
    /** "#U2b50#Ufe0f Regina.xlsx" -> "⭐️ Regina" */
    public static function decode(string $fileName): string
    {
        $name = (string) preg_replace('/\.xlsx$/i', '', $fileName);

        return trim((string) preg_replace_callback(
            '/#U([0-9a-f]{4,5})/i',
            fn (array $m) => mb_chr((int) hexdec($m[1])),
            $name,
        ));
    }
}

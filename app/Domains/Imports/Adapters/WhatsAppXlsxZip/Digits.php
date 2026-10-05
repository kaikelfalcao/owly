<?php

namespace App\Domains\Imports\Adapters\WhatsAppXlsxZip;

final class Digits
{
    public static function of(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }
}

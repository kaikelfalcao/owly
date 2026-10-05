<?php

namespace App\Domains\Imports\Adapters\WhatsAppXlsxZip;

/**
 * Descobre o telefone da empresa no zip: quem assina mensagens com "*Nome:*"
 * é a empresa. Sem nenhuma assinatura, fica o telefone presente em mais
 * planilhas (a empresa está em todas).
 */
final class CompanyPhone
{
    /**
     * @param  array<string, list<array<string, string>>>  $sheets
     */
    public static function detect(array $sheets): string
    {
        $signed = [];
        $presence = [];

        foreach ($sheets as $rows) {
            $phones = [];

            foreach ($rows as $row) {
                $phone = Digits::of($row['UserPhone']);

                if ($phone === '') {
                    continue;
                }

                $phones[$phone] = true;

                if (preg_match(MessageRow::SIGNATURE, MessageRow::cleanBody($row['MessageBody']))) {
                    $signed[$phone] = ($signed[$phone] ?? 0) + 1;
                }
            }

            foreach (array_keys($phones) as $phone) {
                $presence[$phone] = ($presence[$phone] ?? 0) + 1;
            }
        }

        $pool = $signed !== [] ? $signed : $presence;
        arsort($pool);

        return (string) (array_key_first($pool) ?? '');
    }
}

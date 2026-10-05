<?php

namespace App\Domains\Imports\Adapters\WhatsAppXlsxZip;

use App\Domains\Conversations\Data\IncomingConversation;
use App\Domains\Imports\Contracts\Importer;
use App\Domains\Imports\Data\ImportFailed;
use App\Domains\Imports\Data\ImportReading;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;
use ZipArchive;

/**
 * O zip da ferramenta de exportação do WhatsApp: um .xlsx por cliente (nome
 * do arquivo = telefone ou nome do contato), aba "Chat", colunas Date1,
 * Date2, Time, UserPhone, UserName, MessageBody, MediaType, MediaLink,
 * MediaCaption, QuotedMessage…
 *
 * O telefone da empresa não vem escrito em lugar nenhum: é o que assina
 * mensagens ("*Nome:*") ou, sem assinatura, o que aparece em mais planilhas.
 */
class WhatsAppXlsxZip implements Importer
{
    private const COLUMNS = ['Date2', 'Time', 'UserPhone', 'UserName', 'MessageBody', 'MediaType', 'MediaLink', 'MediaCaption', 'QuotedMessage'];

    public function format(): string
    {
        return 'whatsapp_xlsx_zip';
    }

    public function read(string $path, string $timezone): ImportReading
    {
        $sheets = [];
        $problems = [];

        foreach ($this->xlsxEntries($path) as $name => $contents) {
            try {
                $rows = $this->rows($contents);
            } catch (Throwable) {
                $problems[] = ['file' => $name, 'code' => 'unreadable_file'];

                continue;
            }

            if ($rows === []) {
                $problems[] = ['file' => $name, 'code' => 'empty_file'];

                continue;
            }

            $sheets[$name] = $rows;
        }

        if ($sheets === [] && $problems === []) {
            throw new ImportFailed('no_conversations');
        }

        $companyPhone = CompanyPhone::detect($sheets);
        $conversations = [];

        foreach ($sheets as $name => $rows) {
            $conversation = $this->conversation($name, $rows, $companyPhone, $timezone);

            if ($conversation->messages === []) {
                $problems[] = ['file' => $name, 'code' => 'no_messages'];

                continue;
            }

            $conversations[] = $conversation;
        }

        return new ImportReading($conversations, $problems, count($sheets) + count($problems));
    }

    /**
     * @return iterable<string, string> nome do arquivo => conteúdo do .xlsx
     */
    private function xlsxEntries(string $path): iterable
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new ImportFailed('invalid_zip');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);

                if (str_starts_with($entry, '__MACOSX') || ! str_ends_with(strtolower($entry), '.xlsx')) {
                    continue;
                }

                yield basename($entry) => (string) $zip->getFromIndex($i);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private function rows(string $contents): array
    {
        // O OpenSpout lê de arquivo; a planilha vive no disco só o tempo da leitura.
        $file = tempnam(sys_get_temp_dir(), 'owly-xlsx-');
        file_put_contents($file, $contents);
        $reader = new Reader;

        try {
            $reader->open($file);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;

                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(self::cellText(...), $row->toArray());

                    if ($header === null) {
                        $header = $values;

                        continue;
                    }

                    $record = [];

                    foreach (self::COLUMNS as $column) {
                        $index = array_search($column, $header, true);
                        $record[$column] = $index === false ? '' : ($values[$index] ?? '');
                    }

                    $rows[] = $record;
                }

                break; // só a primeira aba ("Chat")
            }

            return $rows;
        } finally {
            $reader->close();
            @unlink($file);
        }
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function conversation(string $fileName, array $rows, string $companyPhone, string $timezone): IncomingConversation
    {
        $fileContact = ExportName::decode($fileName);
        $fileIsPhone = (bool) preg_match('/^[+\d\s()-]+$/', $fileContact);

        $phone = null;
        $name = null;

        foreach ($rows as $row) {
            $rowPhone = Digits::of($row['UserPhone']);

            if ($rowPhone !== '' && $rowPhone !== $companyPhone) {
                $phone ??= $rowPhone;
                $name ??= $row['UserName'] !== '' ? $row['UserName'] : null;
            }
        }

        $phone ??= $fileIsPhone ? Digits::of($fileContact) : null;
        $name ??= $fileIsPhone ? null : $fileContact;

        return new IncomingConversation(
            contactKey: $phone ? "wa:{$phone}" : 'file:'.mb_substr($fileContact, 0, 150),
            contactName: $name,
            contactPhone: $phone,
            messages: MessageRow::toMessages($rows, $companyPhone, $timezone),
        );
    }

    private static function cellText(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s');
        }

        return $value === null ? '' : (string) $value;
    }
}

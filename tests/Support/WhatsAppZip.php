<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use ZipArchive;

/**
 * Monta um zip no formato da ferramenta de exportação do WhatsApp, com dados
 * inventados: uma planilha por cliente, aba "Chat".
 */
final class WhatsAppZip
{
    public const COMPANY = '5511900000000';

    private const HEADER = ['Date1', 'Date2', 'Time', 'UserPhone', 'UserName', 'MessageBody', 'MediaType', 'MediaLink', 'MediaCaption', 'QuotedMessage'];

    /** @var array<string, list<list<string>>> */
    private array $sheets = [];

    /** @var array<string, string> */
    private array $raw = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Uma planilha. Cada mensagem: [data Y-m-d, hora H:i:s, telefone, nome, texto, tipo de mídia?, link da mídia?].
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5?: string, 6?: string}>  $messages
     */
    public function sheet(string $fileName, array $messages): self
    {
        $this->sheets[$fileName] = array_map(fn (array $m) => [
            $m[0], $m[0], $m[1], $m[2], $m[3], $m[4], $m[5] ?? '', $m[6] ?? '', '', '',
        ], $messages);

        return $this;
    }

    /** Um arquivo qualquer dentro do zip (planilha quebrada, por exemplo). */
    public function file(string $name, string $contents): self
    {
        $this->raw[$name] = $contents;

        return $this;
    }

    /** O exemplo de sempre: dois clientes, uma vendedora que assina e o robô. */
    public static function sample(): self
    {
        return self::make()
            ->sheet('5511988887777.xlsx', [
                ['2026-09-01', '09:00:00', '5511988887777', 'Cliente Teste', 'Oi, quanto custa 500 cartões?'],
                ['2026-09-01', '09:05:00', self::COMPANY, '', "*Ana:*\nBom dia! Custa R$ 90."],
                ['2026-09-01', '09:06:00', '5511988887777', 'Cliente Teste', 'Missed voice call'],
                ['2026-09-04', '20:00:00', '5511988887777', 'Cliente Teste', 'Fechado, pode fazer'],
                ['2026-09-04', '20:00:05', self::COMPANY, '', 'Estamos fora do horário de atendimento.'],
            ])
            ->sheet('#U2b50 Maria Inventada.xlsx', [
                ['2026-09-02', '10:00:00', '5511977776666', '', 'Vocês fazem banner?'],
                ['2026-09-02', '10:30:00', self::COMPANY, '', "*Bia:*\nFazemos sim"],
                ['2026-09-02', '10:31:00', self::COMPANY, '', '2026_09_02_103100_foto.jpg', 'image', 'foto.jpg'],
                ['2026-09-02', '10:32:00', self::COMPANY, '', 'tabela.pdf', 'document', 'tabela.pdf'],
            ]);
    }

    public function save(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $temp = [];

        foreach ($this->sheets as $name => $rows) {
            $file = tempnam(sys_get_temp_dir(), 'owly-test-');
            $writer = new Writer;
            $writer->openToFile($file);
            $writer->getCurrentSheet()->setName('Chat');
            $writer->addRow(Row::fromValues(self::HEADER));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
            $zip->addFile($file, $name);
            $temp[] = $file;
        }

        foreach ($this->raw as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        foreach ($temp as $file) {
            @unlink($file);
        }

        return $path;
    }

    public function path(): string
    {
        return $this->save(tempnam(sys_get_temp_dir(), 'owly-zip-').'.zip');
    }
}

<?php

namespace App\Domains\Imports\Adapters\WhatsAppXlsxZip;

use App\Domains\Conversations\Data\IncomingMessage;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Uma linha da planilha vira uma mensagem. Regras do formato:
 *
 * - a vendedora assina com "*Nome:*" e uma quebra de linha no começo;
 * - mídia vem com o corpo em base64 (a miniatura): fica só a legenda e o
 *   nome do arquivo, que não vem no zip;
 * - "Missed voice call" e "This message has been deleted" são avisos, não texto;
 * - a resposta automática de fora do horário é do robô, não de uma pessoa.
 */
final class MessageRow
{
    public const SIGNATURE = '/^\*([^*\n]{1,40}):\*\s*\n/u';

    private const BOT = ['/fora do hor[aá]rio/iu', '/^notification_template$/i'];

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<IncomingMessage>
     */
    public static function toMessages(array $rows, string $companyPhone, string $timezone): array
    {
        $messages = [];
        $seen = [];

        foreach ($rows as $row) {
            $sentAt = self::sentAt($row, $timezone);

            if ($sentAt === null) {
                continue;
            }

            // Id estável: o mesmo conteúdo no mesmo minuto gera o mesmo id, e
            // repetições idênticas ganham um número. Assim um zip que cobre o
            // mesmo período de outro não duplica nada.
            $key = substr(sha1(implode('|', [
                $sentAt->format('Y-m-d H:i:s'),
                Digits::of($row['UserPhone']),
                mb_substr($row['MessageBody'], 0, 500),
                $row['MediaLink'],
            ])), 0, 32);
            $n = $seen[$key] ?? 0;
            $seen[$key] = $n + 1;

            $messages[] = self::message($row, $companyPhone, $n ? "{$key}-{$n}" : $key, $sentAt);
        }

        return $messages;
    }

    public static function cleanBody(string $body): string
    {
        return (string) preg_replace('/^\x{200E}/u', '', str_replace('\n', "\n", $body));
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function message(array $row, string $companyPhone, string $id, CarbonImmutable $sentAt): IncomingMessage
    {
        $body = self::cleanBody($row['MessageBody']);
        $mediaType = $row['MediaType'] !== '' ? mb_strtolower($row['MediaType']) : null;
        $outbound = Digits::of($row['UserPhone']) === $companyPhone;

        if ($mediaType !== null && self::looksLikeBase64($body)) {
            $body = self::cleanBody($row['MediaCaption']);
        }

        // Só o nome do arquivo de mídia, sem legenda.
        if (preg_match('/^\s*\d{4}_\d{2}_\d{2}_\d{6}_\w+\.\w+\s*$/', $body)) {
            $body = '';
        }

        preg_match('/"([^"]+)"/', $row['MediaLink'], $link);
        $mediaName = $link[1] ?? ($row['MediaLink'] !== '' ? basename($row['MediaLink']) : null);

        // Documento costuma vir com o próprio nome do arquivo como texto.
        if ($mediaName !== null && trim($body) === $mediaName) {
            $body = '';
        }
        $quoted = $row['QuotedMessage'] !== '' ? self::cleanBody($row['QuotedMessage']) : null;
        $direction = $outbound ? IncomingMessage::OUT : IncomingMessage::IN;

        $make = fn (string $author, ?string $seller, ?string $text, ?string $event = null) => new IncomingMessage(
            externalId: $id,
            sentAt: $sentAt,
            direction: $direction,
            author: $author,
            sellerName: $seller,
            body: $text === '' ? null : $text,
            mediaType: $mediaType,
            mediaName: $mediaName !== null ? mb_substr($mediaName, 0, 250) : null,
            event: $event,
            quoted: $quoted,
        );

        if (preg_match('/^missed (voice|video) call$/i', trim($body))) {
            return $make('system', null, null, 'missed_call');
        }

        if (preg_match('/^this message has been deleted$/i', trim($body))) {
            return $make('system', null, null, 'deleted');
        }

        if (! $outbound) {
            return $make('contact', null, $body);
        }

        if (preg_match(self::SIGNATURE, $body, $signature) && count(preg_split('/\s+/', trim($signature[1])) ?: []) <= 3) {
            return $make('seller', trim($signature[1]), mb_substr($body, mb_strlen($signature[0])));
        }

        foreach (self::BOT as $pattern) {
            if (preg_match($pattern, $body)) {
                return $make('bot', null, $body, str_contains(strtolower($body), 'notification_template') ? 'template' : 'auto_reply');
            }
        }

        // Mensagem da empresa sem assinatura (mídia ou enviada pelo celular):
        // é de alguém da equipe, sem nome.
        return $make('seller', null, $body);
    }

    /**
     * @param  array<string, string>  $row
     */
    private static function sentAt(array $row, string $timezone): ?CarbonImmutable
    {
        if ($row['Date2'] === '' || $row['Time'] === '') {
            return null;
        }

        $date = substr($row['Date2'], 0, 10);
        $time = strlen($row['Time']) > 8 ? substr($row['Time'], -8) : $row['Time'];

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        $time = str_pad($time, 8, '0', STR_PAD_LEFT);

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", $timezone)?->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private static function looksLikeBase64(string $value): bool
    {
        return strlen($value) > 200 && ! preg_match('/\s/', substr($value, 0, 200));
    }
}

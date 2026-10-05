<?php

namespace App\Domains\Conversations\Data;

use Carbon\CarbonImmutable;

/**
 * Uma mensagem já lida de alguma origem (zip, API), pronta para gravar.
 * Quem lê o formato monta isto; Conversas não sabe de onde veio.
 */
final readonly class IncomingMessage
{
    public const IN = 'in';

    public const OUT = 'out';

    /**
     * @param  string  $externalId  id estável dentro da conversa; reimportar não duplica
     * @param  string  $author  contact | seller | bot | system
     * @param  string|null  $event  missed_call | deleted | auto_reply | template
     */
    public function __construct(
        public string $externalId,
        public CarbonImmutable $sentAt,
        public string $direction,
        public string $author,
        public ?string $sellerName = null,
        public ?string $body = null,
        public ?string $mediaType = null,
        public ?string $mediaName = null,
        public ?string $event = null,
        public ?string $quoted = null,
    ) {}
}

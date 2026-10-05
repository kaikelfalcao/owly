<?php

namespace App\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Um aviso no sino do topo. Cada domínio manda os seus (importação
 * concluída, falha da IA…) com um `kind` que escolhe o ícone na tela.
 *
 * O texto aparece só para o dono, dentro da Owly; mesmo assim, use "Conversa
 * #id" e números, sem nome nem telefone de cliente.
 */
class Notice extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: ?string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}

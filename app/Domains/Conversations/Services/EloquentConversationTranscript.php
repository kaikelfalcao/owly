<?php

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Contracts\ConversationTranscript;
use App\Domains\Conversations\Data\Transcript;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\Message;

class EloquentConversationTranscript implements ConversationTranscript
{
    public function for(int $organizationId, int $conversationId, string $timezone): Transcript
    {
        $conversation = Conversation::where('organization_id', $organizationId)
            ->with('contact')
            ->findOrFail($conversationId);

        $lines = $conversation->messages()
            ->with('seller')
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => [
                'id' => $message->id,
                'at' => $message->sent_at->setTimezone($timezone)->format('d/m/Y H:i'),
                'who' => match ($message->author) {
                    'contact' => 'Cliente',
                    'seller' => $message->seller?->name ? 'Vendedora '.$message->seller->name : 'Equipe',
                    'bot' => 'Resposta automática',
                    default => 'Aviso',
                },
                'text' => self::text($message),
            ])
            ->values()
            ->all();

        return new Transcript(
            conversationId: $conversation->id,
            lines: $lines,
            contactNames: array_values(array_filter([$conversation->contact->name])),
        );
    }

    private static function text(Message $message): string
    {
        $parts = [];

        if ($message->event === 'missed_call') {
            return '(ligação perdida)';
        }

        if ($message->event === 'deleted') {
            return '(mensagem apagada)';
        }

        if ($message->media_type !== null) {
            $parts[] = "(arquivo {$message->media_type})";
        }

        if ($message->quoted !== null) {
            $parts[] = '(respondendo: "'.mb_strimwidth(str_replace("\n", ' ', $message->quoted), 0, 120, '…').'")';
        }

        if ($message->body !== null) {
            $parts[] = $message->body;
        }

        return implode(' ', $parts);
    }
}

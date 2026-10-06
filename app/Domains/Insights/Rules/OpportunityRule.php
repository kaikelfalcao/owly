<?php

namespace App\Domains\Insights\Rules;

use App\Domains\Conversations\Data\MessageFact;
use App\Domains\Insights\Data\DecidedOpportunity;
use App\Domains\Insights\Data\ExpectedOpportunity;
use App\Domains\Insights\Models\Opportunity;

/**
 * Anda pelo histórico inteiro do cliente e diz quais oportunidades a regra
 * espera (docs/arquitetura.md, "Oportunidades"):
 *
 * - orçamento da empresa sem oportunidade aberta abre uma; os seguintes
 *   (reenvio, ajuste de preço) entram nela;
 * - venda do cliente fecha a aberta como ganha, mesmo em outro atendimento;
 * - venda sem oportunidade aberta e sem outra ganha no mesmo atendimento
 *   nasce ganha (o painel conta vendas sem orçamento antes);
 * - a decisão do dono vence: a oportunidade decidida segura os orçamentos
 *   até a hora da decisão (aberta, para sempre) e a descartada segura os
 *   do mesmo atendimento, para o dono não descartar duas vezes.
 */
final class OpportunityRule
{
    /**
     * @param  list<MessageFact>  $messages  o histórico do cliente, em ordem
     * @param  array<int, DecidedOpportunity>  $decided  por mensagem âncora
     * @return list<ExpectedOpportunity>
     */
    public static function expected(array $messages, array $decided): array
    {
        /** @var list<array<string, mixed>> $expected */
        $expected = [];
        // A que segura os orçamentos agora: índice em $expected ou a decidida.
        $current = null;
        $won = [];
        $discarded = [];

        foreach ($messages as $message) {
            if ($current instanceof DecidedOpportunity && $current->closedAt !== null && $message->at >= $current->closedAt) {
                $current = null;
            }

            $decision = $decided[$message->id] ?? null;

            if ($decision !== null) {
                if ($decision->status === Opportunity::DISCARDED) {
                    $discarded[$message->conversationId] = true;
                } else {
                    $current = $decision;
                }

                if ($decision->status === Opportunity::WON) {
                    $won[$message->conversationId] = true;
                }

                continue;
            }

            if (QuoteMessage::is($message)) {
                if ($current === null && ! isset($discarded[$message->conversationId])) {
                    $expected[] = [
                        'anchor' => $message,
                        'status' => Opportunity::OPEN,
                        'closing' => null,
                    ];
                    $current = array_key_last($expected);
                }

                continue;
            }

            if (! SaleSignal::is($message)) {
                continue;
            }

            if (is_int($current)) {
                $expected[$current]['status'] = Opportunity::WON;
                $expected[$current]['closing'] = $message;
                $won[$message->conversationId] = true;
                $current = null;
            } elseif ($current === null && ! isset($won[$message->conversationId]) && ! isset($discarded[$message->conversationId])) {
                $expected[] = [
                    'anchor' => $message,
                    'status' => Opportunity::WON,
                    'closing' => $message,
                ];
                $won[$message->conversationId] = true;
            }
        }

        return array_map(fn (array $row) => new ExpectedOpportunity(
            anchorMessageId: $row['anchor']->id,
            conversationId: $row['anchor']->conversationId,
            sellerId: $row['anchor']->sellerId,
            status: $row['status'],
            openedAt: $row['anchor']->at,
            closingMessageId: $row['closing']?->id,
            closedAt: $row['closing']?->at,
        ), $expected);
    }
}

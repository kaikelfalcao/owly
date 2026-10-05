<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Data\Prompt;
use App\Domains\Ai\Models\AiConnection;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Ai\ProviderCatalog;
use App\Domains\Conversations\Contracts\ConversationTranscript;
use App\Domains\Conversations\Data\Transcript;
use App\Platform\Audit\Audit;
use App\Platform\Telemetry\Telemetry;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Perguntar à IA sobre uma conversa ou uma mensagem dela. A conversa e a
 * pergunta saem mascaradas; a resposta e o consumo ficam guardados.
 */
class AskAboutConversation
{
    /** Teto do texto da conversa enviado, para segurar custo em conversa enorme. */
    public const MAX_CHARS = 60000;

    public const INSTRUCTIONS = <<<'TXT'
        Você é a Owly, assistente que lê conversas de venda pelo WhatsApp de uma empresa e ajuda o dono a entender o atendimento.
        Responda em português do Brasil, em poucas frases ou tópicos curtos, sem enrolação.
        Quando ajudar, cite o trecho com a data e a hora da linha.
        Dados pessoais foram trocados por marcadores como [cliente], [telefone] e [e-mail]: não tente adivinhar o que havia ali.
        Se a conversa não tem o que a pergunta pede, diga isso em vez de inventar.
        TXT;

    public function __construct(
        private readonly ConversationTranscript $transcripts,
        private readonly ProviderCatalog $catalog,
        private readonly Redactor $redactor,
        private readonly Audit $audit,
        private readonly Telemetry $telemetry,
    ) {}

    public function ask(int $organizationId, int $userId, string $timezone, int $conversationId, string $question, ?int $messageId): AiQuestion
    {
        $transcript = $this->transcripts->for($organizationId, $conversationId, $timezone);

        if ($messageId !== null && ! $transcript->has($messageId)) {
            throw ValidationException::withMessages(['message_id' => 'Essa mensagem não é desta conversa.']);
        }

        $connection = AiConnection::where('organization_id', $organizationId)->where('is_default', true)->first()
            ?? throw ValidationException::withMessages(['question' => 'Conecte uma IA antes de perguntar.']);

        $prompt = $this->prompt($transcript, $question, $messageId);
        $started = hrtime(true);
        $span = $this->telemetry->tracer()->spanBuilder('ai.generate')
            ->setAttribute('owly.provider', $connection->provider)
            ->startSpan();

        $record = [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'ai_connection_id' => $connection->id,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'question' => $question,
            'provider' => $connection->provider,
            'model' => $connection->model,
        ];

        try {
            $completion = $this->catalog->provider($connection->provider)->generate($connection->api_key, $connection->model, $prompt);
            $record += [
                'status' => AiQuestion::DONE,
                'answer' => $completion->text,
                'input_tokens' => $completion->inputTokens,
                'output_tokens' => $completion->outputTokens,
            ];
            $span->setAttribute('owly.input_tokens', $completion->inputTokens)
                ->setAttribute('owly.output_tokens', $completion->outputTokens);
        } catch (AiFailed $e) {
            $record += ['status' => AiQuestion::FAILED, 'error_code' => $e->reason];
            $span->setAttribute('owly.error', $e->reason);
        } finally {
            $span->end();
        }

        $duration = (hrtime(true) - $started) / 1e9;
        $answer = AiQuestion::create($record + ['duration_ms' => (int) round($duration * 1000)]);

        $this->audit->record('ai.question_asked', $answer, [
            'provider' => $answer->provider,
            'ok' => $answer->status === AiQuestion::DONE,
            'input_tokens' => $answer->input_tokens,
            'output_tokens' => $answer->output_tokens,
        ]);

        $this->telemetry->histogram('owly.ai.duration', 's', 'Duração das perguntas à IA')
            ->record($duration, ['provider' => $answer->provider, 'outcome' => $answer->status]);

        Log::info('pergunta à IA', ['ai_question_id' => $answer->id, 'status' => $answer->status, 'error' => $answer->error_code]);

        return $answer;
    }

    /**
     * Pergunta e conversa mascaradas. Conversa longa perde o começo: o fim
     * costuma ser o que importa (a resposta, o fechamento).
     */
    public function prompt(Transcript $transcript, string $question, ?int $messageId): Prompt
    {
        $mask = fn (string $text) => $this->redactor->mask($text, $transcript->contactNames);
        $lines = array_map(fn (array $line) => "{$line['at']} · {$line['who']}: ".$mask($line['text']), $transcript->lines);

        $body = '';
        $cut = false;

        foreach (array_reverse($lines) as $line) {
            if (mb_strlen($body) + mb_strlen($line) > self::MAX_CHARS) {
                $cut = true;
                break;
            }

            $body = $line."\n".$body;
        }

        $content = 'Conversa (data hora · quem: texto)'.($cut ? ', só a parte mais recente' : '').":\n".$body;

        if ($messageId !== null && ($focus = $transcript->line($messageId))) {
            $content .= "\nMensagem em foco: {$focus['at']} · {$focus['who']}: ".$mask($focus['text'])."\n";
        }

        $content .= "\nPergunta do dono: ".$mask($question);

        return new Prompt(self::INSTRUCTIONS, $content);
    }
}

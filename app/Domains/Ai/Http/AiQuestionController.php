<?php

namespace App\Domains\Ai\Http;

use App\Domains\Accounts\CurrentOrganization;
use App\Domains\Ai\Data\AiFailed;
use App\Domains\Ai\Models\AiQuestion;
use App\Domains\Ai\Services\AskAboutConversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiQuestionController extends Controller
{
    public function store(Request $request, int $conversation, AskAboutConversation $ask, CurrentOrganization $organization): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'message_id' => ['nullable', 'integer'],
        ], [
            'question.required' => 'Escreva a pergunta.',
            'question.max' => 'Use uma pergunta com até 1000 letras.',
        ]);

        $answer = $ask->ask(
            $organization->id(),
            $request->user()->id,
            $organization->get()->timezone,
            $conversation,
            trim($data['question']),
            $data['message_id'] ?? null,
        );

        if ($answer->status === AiQuestion::FAILED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => AiFailed::MESSAGES[$answer->error_code] ?? AiFailed::MESSAGES['unexpected']]);
        }

        return back();
    }
}

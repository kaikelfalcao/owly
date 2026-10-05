<?php

use App\Domains\Ai\Http\AiConnectionController;
use App\Domains\Ai\Http\AiQuestionController;
use App\Domains\Conversations\Http\ConversationController;
use App\Domains\Imports\Http\ImportController;
use App\Platform\Http\NotificationController;
use Illuminate\Support\Facades\Route;

// Sem página pública: o site de marketing e o cadastro vivem fora daqui.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('conversas', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversas/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('conversations.show');

    Route::post('conversas/{conversation}/perguntas', [AiQuestionController::class, 'store'])->whereNumber('conversation')->middleware('throttle:20,1')->name('ai.questions.store');

    Route::get('ia', [AiConnectionController::class, 'index'])->name('ai.index');
    Route::get('ia/conectar', [AiConnectionController::class, 'create'])->name('ai.create');
    Route::post('ia/conectar/testar', [AiConnectionController::class, 'verify'])->middleware('throttle:10,1')->name('ai.verify');
    Route::post('ia/conexoes', [AiConnectionController::class, 'store'])->middleware('throttle:10,1')->name('ai.store');
    Route::post('ia/conexoes/{connection}/padrao', [AiConnectionController::class, 'makeDefault'])->whereNumber('connection')->name('ai.makeDefault');
    Route::delete('ia/conexoes/{connection}', [AiConnectionController::class, 'destroy'])->whereNumber('connection')->name('ai.destroy');

    Route::get('importar', [ImportController::class, 'index'])->name('imports.index');
    Route::post('importar', [ImportController::class, 'store'])->middleware('throttle:10,1')->name('imports.store');

    Route::post('notificacoes/lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notificacoes/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

require __DIR__.'/settings.php';

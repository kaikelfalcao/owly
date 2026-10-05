<?php

use App\Platform\Http\NotificationController;
use Illuminate\Support\Facades\Route;

// Sem página pública: o site de marketing e o cadastro vivem fora daqui.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('notificacoes/lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notificacoes/{id}', [NotificationController::class, 'open'])->name('notifications.open');
});

require __DIR__.'/settings.php';

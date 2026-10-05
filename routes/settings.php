<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Platform\Http\ActivityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Minha conta. Não há "excluir conta": o dono é a empresa, e encerrar a
// empresa vai ser um fluxo próprio quando existir o site de vendas.
Route::middleware(['auth'])->prefix('conta')->group(function () {
    Route::redirect('/', '/conta/perfil');

    Route::get('perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('perfil', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('seguranca', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('senha', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('aparencia', 'settings/appearance')->name('appearance.edit');

    Route::get('atividade', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('atividade/exportar', [ActivityController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('activity.export');
});

<?php

use Illuminate\Support\Facades\Route;

// Sem página pública: o site de marketing e o cadastro vivem fora daqui.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

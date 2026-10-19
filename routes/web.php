<?php

use App\Http\Controllers\ThreadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('new-chat', 'NewChat')->name('dashboard');

    Route::post('/thread/store', [ThreadController::class, 'store'])->name('thread.store');

});

require __DIR__.'/settings.php';

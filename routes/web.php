<?php

use App\Http\Controllers\ThreadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('new-chat', 'NewChat')->name('dashboard');

    Route::post('/threads', [ThreadController::class, 'store'])->name('threads.store');
    Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');
    Route::post('/threads/{thread}/messages', [ThreadController::class, 'storeMessage'])->name('threads.messages.store');

});

require __DIR__.'/settings.php';

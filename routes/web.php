<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ThreadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('new-chat', 'NewChat')->name('dashboard');

    Route::post('/threads', [ThreadController::class, 'store'])->name('threads.store');
    Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');
    Route::patch('/threads/{thread}', [ThreadController::class, 'update'])->name('threads.update');
    Route::patch('/threads/{thread}/pin', [ThreadController::class, 'pin'])->name('threads.pin');
    Route::delete('/threads/{thread}', [ThreadController::class, 'destroy'])->name('threads.destroy');
    Route::post('/threads/{thread}/messages', [ThreadController::class, 'storeMessage'])->name('threads.messages.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
});

require __DIR__.'/settings.php';

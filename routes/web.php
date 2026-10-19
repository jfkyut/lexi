<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('new-chat', 'Thread')->name('dashboard');

});

require __DIR__.'/settings.php';

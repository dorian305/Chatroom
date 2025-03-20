<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified',])
    ->group(function () {
        /**
         * Add views which require authorization before access.
         */
        Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
        Route::get('/chatroom', fn () => view('chat'))->name('chat');
    });
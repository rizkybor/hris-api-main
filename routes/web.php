<?php

use App\Http\Controllers\PublicProposalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public share link for a Proposal Penawaran (no login; the token is the
// only key). Throttled so the token space can't be brute-forced.
Route::get('/proposal/{token}', [PublicProposalController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->middleware('throttle:30,1')
    // Stateless: a client opening a shared PDF needs no session or cookies.
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ])
    ->name('proposals.public');

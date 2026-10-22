<?php

use App\Http\Controllers\PublicMaintenanceController;
use App\Http\Controllers\PublicProposalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public share link for a Proposal Penawaran (no login; the token is the
// only key). Throttled so the token space can't be brute-forced. Any
// token -- even a malformed or truncated one -- reaches the controller, so
// the client always gets the friendly "link unavailable" page.
Route::get('/proposal/{token}', [PublicProposalController::class, 'show'])
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

// Public quota page for a maintenance client, same rules as the proposal
// link above (token-only, throttled, stateless, friendly 404).
Route::get('/maintenance/{token}', [PublicMaintenanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    ])
    ->name('maintenance.public');

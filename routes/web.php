<?php

use App\Http\Controllers\LicenseCertificateController;
use App\Http\Controllers\PublicMaintenanceController;
use App\Http\Controllers\PublicProposalController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->name('proposals.public');

// Public quota page for a maintenance client, same rules as the proposal
// link above (token-only, throttled, stateless, friendly 404).
Route::get('/maintenance/{token}', [PublicMaintenanceController::class, 'show'])
    ->middleware('throttle:30,1')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->name('maintenance.public');

// Public License Certificate from a WhatsApp share: a 7-day signed URL
// (checked in the controller so a bad link gets a friendly page).
Route::get('/license-certificate/{license}', [LicenseCertificateController::class, 'publicShow'])
    ->whereNumber('license')
    ->middleware('throttle:30,1')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->name('licenses.certificate.public');

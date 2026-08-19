<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Nightly automatic backup, retaining the last 14 days -- keeps a
        // rolling safety net without needing anyone to remember to click
        // "Create Backup" manually.
        $schedule->command('backup:run')->dailyAt('01:00')->onOneServer();
        $schedule->command('backup:prune')->dailyAt('01:30')->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'project.member' => \App\Http\Middleware\EnsureProjectMembership::class,
        ]);

        // This app is API-only (the SPA lives in a separate frontend project,
        // and routes/web.php has no login page), so an unauthenticated request
        // should always get a 401 JSON response rather than the default
        // Authenticate middleware trying to redirect to a nonexistent 'login'
        // route -- which throws RouteNotFoundException and surfaces as a 500.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->append(\App\Http\Middleware\AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This app is API-only (no server-rendered login page exists in
        // routes/web.php), so an unauthenticated request must always get a
        // 401 JSON response. Laravel's default exception handler falls back
        // to redirect()->guest(route('login')) for requests that don't send
        // an Accept: application/json header (e.g. window.open() downloads,
        // direct navigation) -- since no 'login' route is defined, that
        // fallback throws RouteNotFoundException and surfaces as a 500
        // instead of a clean 401.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        });

        // Match the app-wide {success, message, data} envelope instead of
        // Laravel's bare {"message": "Too Many Attempts."} default, so the
        // frontend can handle a 429 the same way it handles every other
        // error response.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please wait a moment and try again.',
                'data' => null,
            ], 429, $e->getHeaders());
        });
    })->create();

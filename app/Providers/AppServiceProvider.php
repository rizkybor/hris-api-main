<?php

namespace App\Providers;

use App\Interfaces\OptionRepositoryInterface;
use App\Repositories\OptionRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repository bindings
        $this->app->bind(OptionRepositoryInterface::class, OptionRepository::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {

            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);

            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordDefaults();
    }

    /**
     * App-wide default for every `Password::defaults()` validation rule
     * (used by login/employee account passwords -- NOT the credential
     * vault, which records arbitrary third-party passwords the user
     * doesn't get to redefine the rules for). Was just `min:8` with no
     * complexity or breach check. `uncompromised()` calls the HaveIBeenPwned
     * range API using k-anonymity (only the first 5 chars of the password's
     * SHA-1 hash ever leave the server) and fails open if that API is
     * unreachable, so it's safe to enable everywhere, not just production.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised()
        );
    }

    /**
     * Brute-force protection for auth endpoints. Each limiter stacks two
     * limits: a tight one keyed by email+IP (stops a targeted attack on one
     * account) and a looser one keyed by IP alone (stops an attacker from
     * dodging the first limit by spraying many different emails from the
     * same machine).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($emailKey),
                Limit::perMinute(20)->by('login|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(3)->by($emailKey),
                Limit::perMinute(10)->by('password-reset|'.$request->ip()),
            ];
        });
    }
}

<?php

namespace App\Providers;

use App\Actions\RecordCompanyActivity;
use App\Contracts\CompanyActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyActivityRecorder::class, RecordCompanyActivity::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('broadcasting.requested_default') === 'ably'
            && blank(config('broadcasting.connections.ably.key'))) {
            Log::warning('ABLY_KEY is empty; broadcasting is temporarily using the log driver.');
        }

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

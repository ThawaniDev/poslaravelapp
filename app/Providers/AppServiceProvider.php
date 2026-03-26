<?php

namespace App\Providers;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Policies\UserPolicy;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Services\TokenService;
use App\Domain\Billing\Models\HardwareSale;
use App\Domain\Billing\Models\ImplementationFee;
use App\Domain\Core\Services\OnboardingService;
use App\Domain\Core\Services\StoreService;
use App\Domain\ProviderSubscription\Observers\HardwareSaleObserver;
use App\Domain\ProviderSubscription\Observers\ImplementationFeeObserver;
use App\Domain\ProviderSubscription\Services\BillingService;
use App\Domain\Security\Services\PinOverrideService;
use App\Domain\StaffManagement\Services\PermissionService;
use App\Domain\StaffManagement\Services\RoleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auth services — singleton for token service (stateless)
        $this->app->singleton(TokenService::class);
        $this->app->singleton(OtpService::class);
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService($app->make(TokenService::class));
        });

        // Staff / Roles & Permissions services
        $this->app->singleton(RoleService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(PinOverrideService::class);

        // Store & Onboarding services
        $this->app->singleton(StoreService::class);
        $this->app->singleton(OnboardingService::class, function ($app) {
            return new OnboardingService($app->make(StoreService::class));
        });

        // Billing service
        $this->app->singleton(BillingService::class);
    }

    public function boot(): void
    {
        // Enforce strict mode in non-production
        Model::shouldBeStrict(! $this->app->isProduction());

        // Prevent lazy loading in development
        Model::preventLazyLoading(! $this->app->isProduction());

        // Prevent silently discarding attributes
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Register policies
        Gate::policy(User::class, UserPolicy::class);

        // Register model observers for auto-invoicing
        HardwareSale::observe(HardwareSaleObserver::class);
        ImplementationFee::observe(ImplementationFeeObserver::class);

        // Configure API rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}

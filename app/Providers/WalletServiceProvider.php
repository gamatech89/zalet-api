<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Wallet\Contracts\PaymentProviderInterface;
use App\Domains\Wallet\Services\StubRaiAcceptService;
use Illuminate\Support\ServiceProvider;

final class WalletServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProviderInterface::class, function ($app) {
            $mode = config('services.raiaccept.mode', 'stub');

            return match ($mode) {
                'stub' => new StubRaiAcceptService(config('services.raiaccept.stub_behavior', 'success')),
                // Future: 'sandbox' => new RaiAcceptSandboxService(),
                // Future: 'production' => new RaiAcceptProductionService(),
                default => new StubRaiAcceptService('success'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

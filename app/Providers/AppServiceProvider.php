<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The default payment gateway is SSLCommerz; tests rebind the FakeGateway.
        $this->app->bind(PaymentGateway::class, SslCommerzGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface lazy loading, missing attributes, and silently discarded
        // attributes everywhere except production, so N+1s and typos fail loud
        // in local/CI rather than degrading the live API.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}

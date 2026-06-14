<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\SslCommerzGateway;
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
        //
    }
}

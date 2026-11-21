<?php

namespace App\Providers;

use App\Mail\Transport\ResendApiTransport;
use App\Services\CloudSunnyPricingService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\FlashSaleService;
use App\Services\MediaStorageService;
use App\Services\PromotionEngine;
use App\Services\WelcomeOfferService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Singleton per request so in-memory memoization (active campaigns, live flash sales,
        // pricing catalog, user claim counts) persists across every app(...) resolution.
        $this->app->singleton(PromotionEngine::class);
        $this->app->singleton(FlashSaleService::class);
        $this->app->singleton(WelcomeOfferService::class);
        $this->app->singleton(CloudSunnyPricingService::class);
        $this->app->singleton(CloudSunnyProxyPricingService::class);
        $this->app->singleton(MediaStorageService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \Illuminate\Pagination\Paginator::useTailwind();

        Mail::extend('resend', function (array $config = []) {
            $apiKey = (string) ($config['key'] ?? config('mail.mailers.smtp.password') ?? '');
            return new ResendApiTransport($apiKey);
        });
    }
}

<?php

namespace App\Providers;

use App\Mail\Transport\MailjetApiTransport;
use App\Mail\Transport\ResendApiTransport;
use App\Mail\Transport\SenderApiTransport;
use App\Mail\Transport\SmartFailoverTransport;
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

        Mail::extend('mailjet', function (array $config = []) {
            $apiKey = (string) ($config['key'] ?? '');
            $secretKey = (string) ($config['secret'] ?? '');
            $fromAddress = isset($config['from_address']) ? (string) $config['from_address'] : null;
            $fromName = isset($config['from_name']) ? (string) $config['from_name'] : null;
            return new MailjetApiTransport($apiKey, $secretKey, $fromAddress, $fromName);
        });

        Mail::extend('sender', function (array $config = []) {
            $token = (string) ($config['token'] ?? '');
            $fromAddress = isset($config['from_address']) ? (string) $config['from_address'] : null;
            $fromName = isset($config['from_name']) ? (string) $config['from_name'] : null;
            return new SenderApiTransport($token, $fromAddress, $fromName);
        });

        Mail::extend('smart_failover', function (array $config = []) {
            $mailers = $config['mailers'] ?? ['resend', 'brevo'];
            if (is_string($mailers)) {
                $mailers = array_values(array_filter(array_map('trim', explode(',', $mailers))));
            }
            return new SmartFailoverTransport(is_array($mailers) ? $mailers : ['resend', 'brevo']);
        });
    }
}

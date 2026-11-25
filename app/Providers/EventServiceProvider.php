<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Laravel 12 đã tự động đăng ký Registered -> SendEmailVerificationNotification ở core;
        // không khai báo lặp tại đây để tránh gửi 2 email xác thực mỗi lần đăng ký.
    ];

    /**
     * Tránh đăng ký lặp SendEmailVerificationNotification khi Laravel 12 core EventServiceProvider đã tự đăng ký.
     */
    protected function configureEmailVerification()
    {
        // Handled once by Illuminate\Foundation\Support\Providers\EventServiceProvider.
    }

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

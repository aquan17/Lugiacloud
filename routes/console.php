<?php

use App\Models\VpsInstance;
use App\Services\CloudSunnyPricingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pricing:sync-upstream', function (CloudSunnyPricingService $pricing) {
    $res = $pricing->syncFromUpstream();
    $this->info("Đã đồng bộ bảng giá gốc: {$res['vps_synced']} gói VPS, {$res['proxy_synced']} gói Proxy, {$res['server_synced']} gói Máy chủ vật lý.");
})->purpose('Đồng bộ giá vốn gốc từ API đối tác về bảng pricing_catalogs');

Schedule::command('cloudsunny:sync-accounts')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('pricing:sync-upstream')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('cloudsunny:check-expiring')->dailyAt('09:00');
Schedule::command('vps:delete-expired')->everyMinute()->withoutOverlapping();
Schedule::command('cloudsunny:sync-status')->everyMinute()->withoutOverlapping();
Schedule::command('cloudsunny:sync-proxies')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('proxy:auto-renew')->hourly()->withoutOverlapping();
Schedule::command('vps:auto-renew')->hourly()->withoutOverlapping();
Schedule::command('promotions:send-launch-emails')->everyFiveMinutes()->withoutOverlapping();

Schedule::call(function () {
    VpsInstance::syncOverdueStatus();
})->name('vps-bulk-overdue-status')->everyMinute()->withoutOverlapping();

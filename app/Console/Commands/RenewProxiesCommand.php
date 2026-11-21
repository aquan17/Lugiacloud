<?php

namespace App\Console\Commands;

use App\Models\ProxyInstance;
use App\Services\ProxyAutoRenewService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenewProxiesCommand extends Command
{
    protected $signature = 'proxy:auto-renew';

    protected $description = 'Quét và tự động gia hạn Proxy trước thời điểm hết hạn 1 ngày (24h)';

    public function handle(): int
    {
        $now = Carbon::now();
        $targetDate = $now->copy()->addDay();

        $proxies = ProxyInstance::with(['user', 'cloudSunnyAccount'])
            ->where('is_auto_renew', true)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã hủy', 'Đang khởi tạo', 'Đang cấp phát'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $targetDate)
            ->get();

        $count = 0;
        $service = app(ProxyAutoRenewService::class);

        foreach ($proxies as $proxy) {
            try {
                if ($service->processAutoRenewal($proxy)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error("Auto renew failed for proxy {$proxy->id}: " . $e->getMessage());
            }
        }

        $this->info("Đã quét {$proxies->count()} proxy và gia hạn thành công {$count} proxy.");

        return 0;
    }
}

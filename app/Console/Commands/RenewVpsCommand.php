<?php

namespace App\Console\Commands;

use App\Models\VpsInstance;
use App\Services\VpsAutoRenewService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenewVpsCommand extends Command
{
    protected $signature = 'vps:auto-renew';

    protected $description = 'Quét và tự động gia hạn Cloud VPS trước thời điểm hết hạn 1 ngày (24h)';

    public function handle(): int
    {
        $now = Carbon::now();
        $targetDate = $now->copy()->addDay();

        $instances = VpsInstance::with(['user', 'cloudSunnyAccount'])
            ->where('is_auto_renew', true)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $targetDate)
            ->get();

        $count = 0;
        $service = app(VpsAutoRenewService::class);

        foreach ($instances as $vps) {
            try {
                if ($service->processAutoRenewal($vps)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::error("Auto renew failed for VPS {$vps->id}: " . $e->getMessage());
            }
        }

        $this->info("Đã quét {$instances->count()} VPS và tự động gia hạn thành công {$count} máy chủ.");

        return 0;
    }
}

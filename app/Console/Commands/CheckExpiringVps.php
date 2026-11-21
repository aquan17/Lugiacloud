<?php

namespace App\Console\Commands;

use App\Mail\ProxyExpiring;
use App\Mail\VpsExpiring;
use App\Models\ProxyInstance;
use App\Models\VpsInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckExpiringVps extends Command
{
    protected $signature = 'cloudsunny:check-expiring';
    protected $description = 'Gửi email nhắc gia hạn VPS và Proxy trước 3 ngày và 1 ngày.';

    public function handle(): int
    {
        $milestones = [3, 1];
        $countVps = 0;
        $countProxy = 0;

        foreach ($milestones as $daysLeft) {
            $targetDate = now()->addDays($daysLeft)->toDateString();

            $instances = VpsInstance::whereIn('status', ['Sẵn sàng', 'Đang chạy', 'Đã tắt'])
                ->whereDate('expires_at', $targetDate)
                ->with('user')
                ->get();

            foreach ($instances as $vps) {
                if (!$vps->user || empty($vps->user->email)) {
                    continue;
                }

                $dedupeKey = "vps_expiring_mail_{$vps->id}_{$targetDate}_{$daysLeft}d";
                if (!Cache::add($dedupeKey, true, 86400 * 3)) {
                    continue;
                }

                try {
                    Mail::to($vps->user->email)->queue(new VpsExpiring($vps, $daysLeft));
                    $countVps++;
                } catch (\Throwable $e) {
                    Log::error('Failed to queue VPS expiration email', [
                        'vps_id' => $vps->id,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }

            $proxies = ProxyInstance::whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã hủy', 'Đang khởi tạo', 'Đang cấp phát'])
                ->whereDate('expires_at', $targetDate)
                ->with('user')
                ->get();

            foreach ($proxies as $proxy) {
                if (!$proxy->user || empty($proxy->user->email)) {
                    continue;
                }

                $dedupeKey = "proxy_expiring_mail_{$proxy->id}_{$targetDate}_{$daysLeft}d";
                if (!Cache::add($dedupeKey, true, 86400 * 3)) {
                    continue;
                }

                try {
                    Mail::to($proxy->user->email)->queue(new ProxyExpiring($proxy, $daysLeft));
                    $countProxy++;
                } catch (\Throwable $e) {
                    Log::error('Failed to queue Proxy expiration email', [
                        'proxy_id' => $proxy->id,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Completed. Queued {$countVps} VPS and {$countProxy} Proxy reminder emails.");

        return 0;
    }
}

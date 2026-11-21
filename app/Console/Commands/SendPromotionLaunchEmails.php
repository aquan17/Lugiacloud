<?php

namespace App\Console\Commands;

use App\Models\PromotionCampaign;
use App\Services\PromotionBroadcastService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPromotionLaunchEmails extends Command
{
    protected $signature = 'promotions:send-launch-emails';

    protected $description = 'Tự động gửi email quảng bá cho toàn bộ khách hàng khi sự kiện theo lịch bắt đầu mở.';

    public function handle(PromotionBroadcastService $broadcaster): int
    {
        $now = now();
        $windowStart = $now->copy()->subHours(24);

        $campaigns = PromotionCampaign::query()
            ->where('is_active', true)
            ->where('send_launch_email', true)
            ->whereNull('launch_email_sent_at')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->where('starts_at', '>=', $windowStart)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('priority')
            ->get();

        $totalEmails = 0;

        foreach ($campaigns as $campaign) {
            try {
                $sent = $broadcaster->broadcast($campaign, false);
                $totalEmails += $sent;
                $this->info("Sự kiện [{$campaign->name}]: đã xếp hàng gửi {$sent} email.");
            } catch (\Throwable $e) {
                Log::error("Broadcast failed for campaign {$campaign->id}: " . $e->getMessage());
            }
        }

        if ($campaigns->isEmpty()) {
            $this->info('Không có sự kiện mới mở lịch cần gửi email.');
        }

        return 0;
    }
}

<?php

namespace App\Services;

use App\Mail\PromotionCampaignLaunched;
use App\Models\PromotionCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PromotionBroadcastService
{
    /**
     * Đẩy hàng loạt email thông báo mở sự kiện vào hàng đợi với nhịp giãn cách an toàn (chống spam/rate-limit SMTP).
     */
    public function broadcast(PromotionCampaign $campaign, bool $force = false): int
    {
        if (!$force) {
            // Khóa nguyên tử: đảm bảo không bao giờ gửi trùng kể cả khi có 2 cron worker chạy song song
            $locked = PromotionCampaign::whereKey($campaign->id)
                ->whereNull('launch_email_sent_at')
                ->update(['launch_email_sent_at' => now()]);

            if ($locked === 0) {
                return 0;
            }
        } else {
            $campaign->update(['launch_email_sent_at' => now()]);
        }

        $couponCodes = $campaign->coupons()
            ->where('is_active', true)
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        $queuedCount = 0;
        $batchIndex = 0;

        User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($campaign, $couponCodes, &$queuedCount, &$batchIndex) {
                foreach ($users as $user) {
                    try {
                        // Giãn cách mỗi 10 email cách nhau 2 giây để an toàn cho SMTP/Mailgun/SES
                        $delaySeconds = intdiv($queuedCount, 10) * 2;
                        $mailable = new PromotionCampaignLaunched(
                            campaign: $campaign,
                            userName: $user->name ?: 'bạn',
                            couponCodes: $couponCodes
                        );

                        if ($delaySeconds > 0) {
                            Mail::to($user->email)->later(now()->addSeconds($delaySeconds), $mailable);
                        } else {
                            Mail::to($user->email)->queue($mailable);
                        }
                        $queuedCount++;
                    } catch (\Throwable $e) {
                        Log::error('Failed to queue promotion launch email', [
                            'campaign_id' => $campaign->id,
                            'user_id' => $user->id,
                            'msg' => $e->getMessage(),
                        ]);
                    }
                }
                $batchIndex++;
            });

        $campaign->update([
            'launch_email_sent_at' => now(),
            'launch_email_count' => $queuedCount,
        ]);

        return $queuedCount;
    }
}

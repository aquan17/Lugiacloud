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
        $broadcastMailer = (string) config('mail.broadcast_mailer', 'broadcast');
        $useCustomMailer = $broadcastMailer !== '' && is_array(config("mail.mailers.{$broadcastMailer}"));

        User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNotNull('email_verified_at')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($campaign, $couponCodes, $broadcastMailer, $useCustomMailer, &$queuedCount, &$batchIndex) {
                foreach ($users as $user) {
                    try {
                        // Giãn cách mỗi 5 email cách nhau 2 giây để an toàn cho API/SMTP rate-limit
                        $delaySeconds = intdiv($queuedCount, 5) * 2;
                        $mailable = new PromotionCampaignLaunched(
                            campaign: $campaign,
                            userName: $user->name ?: 'bạn',
                            couponCodes: $couponCodes
                        );

                        $pending = $useCustomMailer
                            ? Mail::mailer($broadcastMailer)->to($user->email)
                            : Mail::to($user->email);

                        if ($delaySeconds > 0) {
                            $pending->later(now()->addSeconds($delaySeconds), $mailable);
                        } else {
                            $pending->queue($mailable);
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

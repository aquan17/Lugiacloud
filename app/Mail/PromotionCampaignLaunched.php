<?php

namespace App\Mail;

use App\Models\PromotionCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PromotionCampaignLaunched extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $couponCodes
     */
    public function __construct(
        public PromotionCampaign $campaign,
        public string $userName,
        public array $couponCodes = []
    ) {
    }

    public function build()
    {
        $badge = $this->campaign->badge_text ? " ({$this->campaign->badge_text})" : '';

        return $this->subject("[LugiaCloud] {$this->campaign->name}{$badge}")
            ->view('emails.promotion_launched');
    }
}

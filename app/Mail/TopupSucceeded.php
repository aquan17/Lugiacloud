<?php

namespace App\Mail;

use App\Models\TopupRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TopupSucceeded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public TopupRequest $topup;
    public User $user;

    public function __construct(TopupRequest $topup, User $user)
    {
        $this->topup = $topup;
        $this->user = $user;
    }

    public function build()
    {
        $total = (int) ($this->topup->approved_amount ?: $this->topup->amount) + (int) ($this->topup->bonus_amount ?? 0);

        return $this->subject('[LugiaCloud] Xác nhận nạp tiền +' . number_format($total, 0, ',', '.') . 'đ (' . $this->topup->code . ')')
            ->view('emails.topup_succeeded');
    }
}

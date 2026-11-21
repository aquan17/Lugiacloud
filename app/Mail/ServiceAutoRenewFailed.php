<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceAutoRenewFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $serviceName,
        public int $requiredAmount,
        public int $currentBalance,
        public string $expiresAt
    ) {
    }

    public function build()
    {
        return $this->subject("[LugiaCloud] Ví chưa đủ số dư để tự động gia hạn {$this->serviceName}")
            ->view('emails.auto_renew_failed');
    }
}

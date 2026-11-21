<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceRenewed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $serviceType,
        public string $serviceName,
        public ?string $ipAddress,
        public string $cycleLabel,
        public int $amountPaid,
        public string $newExpiresAt,
        public string $manageUrl,
        public bool $isAuto = false
    ) {
    }

    public function build()
    {
        $prefix = $this->isAuto ? 'Tự động gia hạn thành công' : 'Xác nhận gia hạn';

        return $this->subject("[LugiaCloud] {$prefix}: {$this->serviceName}")
            ->view('emails.service_renewed');
    }
}

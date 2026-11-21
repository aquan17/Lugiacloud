<?php

namespace App\Mail;

use App\Models\VpsInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VpsExpiring extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $vps;
    public $daysLeft;

    public function __construct(VpsInstance $vps, int $daysLeft)
    {
        $this->vps = $vps;
        $this->daysLeft = $daysLeft;
    }

    public function build()
    {
        return $this->subject('[LugiaCloud] Nhắc gia hạn: VPS ' . $this->vps->label . ' còn ' . $this->daysLeft . ' ngày')
                    ->view('emails.vps_expiring');
    }
}

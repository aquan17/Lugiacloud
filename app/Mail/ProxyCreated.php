<?php

namespace App\Mail;

use App\Models\ProxyInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProxyCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $proxy;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(ProxyInstance $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('[LugiaCloud] Proxy IPv4 #' . $this->proxy->id . ' đã sẵn sàng kết nối')
                    ->view('emails.proxy_created');
    }
}

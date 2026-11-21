<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('[LugiaCloud] Xác thực địa chỉ email tài khoản')
            ->view('emails.verify_email', [
                'userName' => $notifiable->name ?? 'bạn',
                'verifyUrl' => $verificationUrl,
            ]);
    }
}

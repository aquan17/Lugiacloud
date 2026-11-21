<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedResetPassword extends ResetPassword
{
    public function toMail($notifiable)
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expireMinutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('[LugiaCloud] Yêu cầu đặt lại mật khẩu tài khoản')
            ->view('emails.reset_password', [
                'userName' => $notifiable->name ?? 'bạn',
                'resetUrl' => $resetUrl,
                'expireMinutes' => $expireMinutes,
            ]);
    }
}

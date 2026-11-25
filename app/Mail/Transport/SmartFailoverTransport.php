<?php

namespace App\Mail\Transport;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Throwable;

class SmartFailoverTransport implements TransportInterface
{
    /**
     * @param  array<int, string>  $mailers
     */
    public function __construct(private array $mailers)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $configured = array_values(array_filter(
            $this->mailers,
            fn (string $name) => $this->isMailerConfigured($name)
        ));

        if (empty($configured)) {
            throw new RuntimeException('No email providers are configured for smart failover.');
        }

        $ready = [];
        $cooling = [];
        foreach ($configured as $name) {
            if (Cache::has($this->cooldownKey($name))) {
                $cooling[] = $name;
            } else {
                $ready[] = $name;
            }
        }

        // Ưu tiên các kênh chưa bị chạm hạn mức; nếu tất cả đều đang cooldown thì vẫn thử lại làm phương án cuối
        $attemptOrder = !empty($ready) ? array_merge($ready, $cooling) : $cooling;
        $errors = [];

        foreach ($attemptOrder as $mailerName) {
            try {
                $transport = Mail::mailer($mailerName)->getSymfonyTransport();
                return $transport->send($message, $envelope);
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                $errors[$mailerName] = $msg;

                $ttlSeconds = $this->resolveCooldownSeconds($msg);
                if ($ttlSeconds > 0) {
                    Cache::put($this->cooldownKey($mailerName), $msg, $ttlSeconds);
                }

                Log::warning("Mail failover: [{$mailerName}] failed, trying next provider.", [
                    'mailer' => $mailerName,
                    'cooldown_seconds' => $ttlSeconds,
                    'error' => mb_substr($msg, 0, 400),
                ]);
            }
        }

        $summary = collect($errors)
            ->map(fn ($err, $name) => "[{$name}] {$err}")
            ->implode(' | ');

        throw new RuntimeException('All failover mailers failed: ' . $summary);
    }

    private function isMailerConfigured(string $name): bool
    {
        $cfg = config("mail.mailers.{$name}");
        if (!is_array($cfg)) {
            return false;
        }

        $transport = (string) ($cfg['transport'] ?? '');
        if ($transport === 'resend') {
            return trim((string) ($cfg['key'] ?? '')) !== '';
        }
        if ($transport === 'brevo') {
            return trim((string) ($cfg['key'] ?? '')) !== '';
        }
        if ($transport === 'mailjet') {
            return trim((string) ($cfg['key'] ?? '')) !== ''
                && trim((string) ($cfg['secret'] ?? '')) !== '';
        }
        if ($transport === 'sender') {
            return trim((string) ($cfg['token'] ?? '')) !== '';
        }
        if ($transport === 'smtp') {
            return trim((string) ($cfg['host'] ?? '')) !== ''
                && trim((string) ($cfg['username'] ?? '')) !== ''
                && trim((string) ($cfg['password'] ?? '')) !== '';
        }
        if ($transport === 'smart_failover') {
            return false; // Tránh đệ quy vòng lặp
        }

        return true;
    }

    private function resolveCooldownSeconds(string $errorMessage): int
    {
        $lower = strtolower($errorMessage);

        // Chạm giới hạn ngày (Resend 429 daily_quota_exceeded, Brevo credit/limit, Gmail daily limit)
        if (
            str_contains($lower, 'daily_quota_exceeded') ||
            str_contains($lower, 'daily limit') ||
            str_contains($lower, 'daily sending limit') ||
            str_contains($lower, 'not_enough_credits') ||
            str_contains($lower, 'out of credits') ||
            str_contains($lower, 'quota')
        ) {
            // Nghỉ tới 00:05 UTC hôm sau (thời điểm Resend/Brevo reset hạn mức ngày), tối thiểu 15 phút
            $nextUtcMidnight = Carbon::now('UTC')->addDay()->startOfDay()->addMinutes(5);
            return max(900, (int) Carbon::now('UTC')->diffInSeconds($nextUtcMidnight));
        }

        // Lỗi 429 rate limit ngắn hạn hoặc 5xx tạm thời -> nghỉ 10 phút rồi thử lại
        if (
            str_contains($lower, '(429)') ||
            str_contains($lower, 'too many requests') ||
            str_contains($lower, 'rate limit') ||
            str_contains($lower, '(500)') ||
            str_contains($lower, '(502)') ||
            str_contains($lower, '(503)') ||
            str_contains($lower, '(504)')
        ) {
            return 600;
        }

        // Lỗi xác thực API key / sender chưa verify -> nghỉ 30 phút để tránh spam log
        if (str_contains($lower, '(401)') || str_contains($lower, '(403)')) {
            return 1800;
        }

        return 0;
    }

    private function cooldownKey(string $mailerName): string
    {
        return "lugiacloud.mail.provider_cooldown.{$mailerName}";
    }

    public function __toString(): string
    {
        return 'smart_failover(' . implode(',', $this->mailers) . ')';
    }
}

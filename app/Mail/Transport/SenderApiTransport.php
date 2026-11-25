<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class SenderApiTransport extends AbstractTransport
{
    public function __construct(
        private string $apiToken,
        private ?string $overrideFromAddress = null,
        private ?string $overrideFromName = null
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiToken) === '') {
            throw new RuntimeException('Sender.net API token is not configured.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $fromList = $email->getFrom();
        $fromAddress = !empty($fromList)
            ? $fromList[0]->getAddress()
            : (string) config('mail.from.address', 'hotro@lugiacloud.com');
        $fromName = !empty($fromList) && trim($fromList[0]->getName()) !== ''
            ? trim($fromList[0]->getName())
            : (string) config('mail.from.name', 'LugiaCloud');

        if ($this->overrideFromAddress && trim($this->overrideFromAddress) !== '') {
            $fromAddress = trim($this->overrideFromAddress);
        }
        if ($this->overrideFromName && trim($this->overrideFromName) !== '') {
            $fromName = trim($this->overrideFromName);
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        if (!$html && $text) {
            $html = nl2br(e((string) $text));
        }

        $recipients = $email->getTo();
        if (empty($recipients)) {
            throw new RuntimeException('Sender.net requires at least one recipient.');
        }

        foreach ($recipients as $recipient) {
            $toPayload = ['email' => $recipient->getAddress()];
            $recipientName = trim($recipient->getName());
            if ($recipientName !== '') {
                $toPayload['name'] = $recipientName;
            }

            $payload = [
                'from' => [
                    'email' => $fromAddress,
                    'name' => $fromName !== '' ? $fromName : $fromAddress,
                ],
                'to' => $toPayload,
                'subject' => (string) $email->getSubject(),
                'html' => (string) ($html ?: ''),
            ];

            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.sender.net/v2/message/send', $payload);

            if ($response->failed()) {
                throw new RuntimeException(sprintf(
                    'Sender.net API error (%d): %s',
                    $response->status(),
                    $response->body()
                ));
            }

            if ($response->json('success') === false) {
                throw new RuntimeException('Sender.net API rejected message: ' . $response->body());
            }
        }
    }

    public function __toString(): string
    {
        return 'sender';
    }
}

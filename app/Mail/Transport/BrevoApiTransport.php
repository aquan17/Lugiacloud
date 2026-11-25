<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class BrevoApiTransport extends AbstractTransport
{
    public function __construct(
        private string $apiKey,
        private ?string $overrideFromAddress = null,
        private ?string $overrideFromName = null
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Brevo API key is not configured.');
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

        $sender = ['email' => $fromAddress];
        if ($fromName !== '') {
            $sender['name'] = $fromName;
        }

        $payload = [
            'sender' => $sender,
            'to' => array_map(fn (Address $a) => $this->formatRecipient($a), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = (string) $html;
        }
        if ($text = $email->getTextBody()) {
            $payload['textContent'] = (string) $text;
        }
        if (!empty($cc = $email->getCc())) {
            $payload['cc'] = array_map(fn (Address $a) => $this->formatRecipient($a), $cc);
        }
        if (!empty($bcc = $email->getBcc())) {
            $payload['bcc'] = array_map(fn (Address $a) => $this->formatRecipient($a), $bcc);
        }
        if (!empty($replyTo = $email->getReplyTo())) {
            $payload['replyTo'] = $this->formatRecipient($replyTo[0]);
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])
            ->timeout(20)
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Brevo API error (%d): %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    private function formatRecipient(Address $address): array
    {
        $item = ['email' => $address->getAddress()];
        $name = trim($address->getName());
        if ($name !== '') {
            $item['name'] = $name;
        }
        return $item;
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}

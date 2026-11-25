<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class MailjetApiTransport extends AbstractTransport
{
    public function __construct(
        private string $apiKey,
        private string $secretKey,
        private ?string $overrideFromAddress = null,
        private ?string $overrideFromName = null
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiKey) === '' || trim($this->secretKey) === '') {
            throw new RuntimeException('Mailjet API credentials are not configured.');
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

        $msgPayload = [
            'From' => [
                'Email' => $fromAddress,
                'Name' => $fromName !== '' ? $fromName : $fromAddress,
            ],
            'To' => array_map(fn (Address $a) => $this->formatRecipient($a), $email->getTo()),
            'Subject' => (string) $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $msgPayload['HTMLPart'] = (string) $html;
        }
        if ($text = $email->getTextBody()) {
            $msgPayload['TextPart'] = (string) $text;
        }
        if (!empty($cc = $email->getCc())) {
            $msgPayload['Cc'] = array_map(fn (Address $a) => $this->formatRecipient($a), $cc);
        }
        if (!empty($bcc = $email->getBcc())) {
            $msgPayload['Bcc'] = array_map(fn (Address $a) => $this->formatRecipient($a), $bcc);
        }
        if (!empty($replyTo = $email->getReplyTo())) {
            $msgPayload['ReplyTo'] = $this->formatRecipient($replyTo[0]);
        }

        $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.mailjet.com/v3.1/send', [
                'Messages' => [$msgPayload],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Mailjet API error (%d): %s',
                $response->status(),
                $response->body()
            ));
        }

        $status = $response->json('Messages.0.Status');
        if ($status && strtolower((string) $status) !== 'success') {
            throw new RuntimeException('Mailjet API rejected message: ' . $response->body());
        }
    }

    private function formatRecipient(Address $address): array
    {
        $item = ['Email' => $address->getAddress()];
        $name = trim($address->getName());
        if ($name !== '') {
            $item['Name'] = $name;
        }
        return $item;
    }

    public function __toString(): string
    {
        return 'mailjet';
    }
}

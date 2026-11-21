<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

class ResendApiTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $fromList = $email->getFrom();
        $from = !empty($fromList)
            ? $this->formatAddress($fromList[0])
            : $this->formatAddress(new Address(
                (string) config('mail.from.address', 'hotro@lugiacloud.com'),
                (string) config('mail.from.name', 'LugiaCloud')
            ));

        $payload = [
            'from' => $from,
            'to' => array_map(fn (Address $a) => $a->getAddress(), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($html = $email->getHtmlBody()) {
            $payload['html'] = (string) $html;
        }
        if ($text = $email->getTextBody()) {
            $payload['text'] = (string) $text;
        }
        if (!empty($cc = $email->getCc())) {
            $payload['cc'] = array_map(fn (Address $a) => $a->getAddress(), $cc);
        }
        if (!empty($bcc = $email->getBcc())) {
            $payload['bcc'] = array_map(fn (Address $a) => $a->getAddress(), $bcc);
        }
        if (!empty($replyTo = $email->getReplyTo())) {
            $payload['reply_to'] = $replyTo[0]->getAddress();
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Resend API error (%d): %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    private function formatAddress(Address $address): string
    {
        $name = trim($address->getName());
        return $name !== '' ? sprintf('%s <%s>', $name, $address->getAddress()) : $address->getAddress();
    }

    public function __toString(): string
    {
        return 'resend';
    }
}

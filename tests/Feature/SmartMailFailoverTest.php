<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmartMailFailoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'mail.default' => 'smart',
            'mail.mailers.resend.key' => 're_test_key',
            'mail.mailers.sender.token' => 'sender_test_token',
            'mail.mailers.mailjet.key' => 'mj_pub_key',
            'mail.mailers.mailjet.secret' => 'mj_sec_key',
            'mail.mailers.brevo.key' => 'xkeysib_test_key',
            'mail.mailers.smart.mailers' => ['resend', 'sender', 'mailjet', 'brevo'],
            'mail.mailers.broadcast.mailers' => ['sender', 'mailjet', 'brevo', 'resend'],
            'mail.from.address' => 'hotro@lugiacloud.com',
            'mail.from.name' => 'LugiaCloud',
        ]);
    }

    public function test_smart_mailer_fails_over_from_resend_to_sender_and_cools_down_resend(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::response(['name' => 'daily_quota_exceeded', 'message' => 'You have reached your daily email sending quota.'], 429),
            'api.sender.net/*' => Http::response(['success' => true, 'message' => 'Message sent'], 200),
        ]);

        Mail::mailer('smart')->html('<p>Hello 1</p>', function ($m) {
            $m->to('user1@example.com')->subject('Test Failover 1');
        });

        Mail::mailer('smart')->html('<p>Hello 2</p>', function ($m) {
            $m->to('user2@example.com')->subject('Test Failover 2');
        });

        // Mail 1 hit Resend (429) -> switched to Sender.net. Mail 2 skipped Resend (in cooldown) -> directly to Sender.net!
        Http::assertSentCount(3);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.resend.com/emails'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.sender.net/v2/message/send')
            && $req->hasHeader('Authorization', 'Bearer sender_test_token')
            && ($req->data()['from']['email'] ?? null) === 'hotro@lugiacloud.com'
            && ($req->data()['to']['email'] ?? null) === 'user2@example.com');
    }

    public function test_broadcast_mailer_prefers_sender_then_mailjet_then_brevo_then_resend(): void
    {
        Http::fake([
            'api.sender.net/*' => Http::response(['message' => 'Rate limit exceeded'], 429),
            'api.mailjet.com/*' => Http::response(['ErrorMessage' => 'Daily limit reached'], 429),
            'api.brevo.com/*' => Http::response(['code' => 'not_enough_credits', 'message' => 'Daily limit reached'], 429),
            'api.resend.com/*' => Http::response(['id' => 're_ok_1'], 200),
        ]);

        Mail::mailer('broadcast')->html('<p>Black Friday</p>', function ($m) {
            $m->to('vip@example.com')->subject('Black Friday Sale');
        });

        Http::assertSentCount(4);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.sender.net/v2/message/send'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.mailjet.com/v3.1/send'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.brevo.com/v3/smtp/email'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.resend.com/emails')
            && ($req->data()['to'][0] ?? null) === 'vip@example.com');
    }
}

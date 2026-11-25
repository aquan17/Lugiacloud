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
            'mail.mailers.mailjet.key' => 'mj_pub_key',
            'mail.mailers.mailjet.secret' => 'mj_sec_key',
            'mail.mailers.brevo.key' => 'xkeysib_test_key',
            'mail.mailers.smart.mailers' => ['resend', 'mailjet', 'brevo'],
            'mail.mailers.broadcast.mailers' => ['mailjet', 'brevo', 'resend'],
            'mail.from.address' => 'hotro@lugiacloud.com',
            'mail.from.name' => 'LugiaCloud',
        ]);
    }

    public function test_smart_mailer_fails_over_from_resend_to_mailjet_and_cools_down_resend(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::response(['name' => 'daily_quota_exceeded', 'message' => 'You have reached your daily email sending quota.'], 429),
            'api.mailjet.com/*' => Http::response(['Messages' => [['Status' => 'success']]], 200),
        ]);

        Mail::mailer('smart')->html('<p>Hello 1</p>', function ($m) {
            $m->to('user1@example.com')->subject('Test Failover 1');
        });

        Mail::mailer('smart')->html('<p>Hello 2</p>', function ($m) {
            $m->to('user2@example.com')->subject('Test Failover 2');
        });

        // Mail 1 hit Resend (429) -> switched to Mailjet. Mail 2 skipped Resend (in cooldown) -> directly to Mailjet!
        Http::assertSentCount(3);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.resend.com/emails'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.mailjet.com/v3.1/send')
            && ($req->data()['Messages'][0]['From']['Email'] ?? null) === 'hotro@lugiacloud.com'
            && ($req->data()['Messages'][0]['To'][0]['Email'] ?? null) === 'user2@example.com');
    }

    public function test_broadcast_mailer_prefers_mailjet_then_brevo_then_resend(): void
    {
        Http::fake([
            'api.mailjet.com/*' => Http::response(['ErrorMessage' => 'Daily limit reached'], 429),
            'api.brevo.com/*' => Http::response(['code' => 'not_enough_credits', 'message' => 'Daily limit reached'], 429),
            'api.resend.com/*' => Http::response(['id' => 're_ok_1'], 200),
        ]);

        Mail::mailer('broadcast')->html('<p>Black Friday</p>', function ($m) {
            $m->to('vip@example.com')->subject('Black Friday Sale');
        });

        Http::assertSentCount(3);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.mailjet.com/v3.1/send'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.brevo.com/v3/smtp/email'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.resend.com/emails')
            && ($req->data()['to'][0] ?? null) === 'vip@example.com');
    }
}

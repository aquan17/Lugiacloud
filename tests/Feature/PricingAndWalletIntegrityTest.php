<?php

namespace Tests\Feature;

use App\Constants\UserRole;
use App\Models\Coupon;
use App\Models\PricingCatalog;
use App\Models\PromotionCampaign;
use App\Models\ProxyInstance;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PricingAndWalletIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_floor_protects_reseller_margin_with_event_coupon_and_affiliate(): void
    {
        $referrer = User::create([
            'name' => 'Partner',
            'email' => 'partner@gmail.com',
            'password' => Hash::make('password123'),
            'ref_code' => 'PARTNER1',
        ]);

        $buyer = User::create([
            'name' => 'Buyer',
            'email' => 'buyer@gmail.com',
            'password' => Hash::make('password123'),
            'referred_by' => $referrer->id,
            'balance' => 1000000,
        ]);

        // Gói vốn gốc 150.000đ, bán lẻ 220.000đ, bảo vệ biên lãi tối thiểu 10%
        PricingCatalog::create([
            'service_type' => 'vps',
            'slug' => 'lg-8',
            'provider_code' => 'sn-4-8-50',
            'provider_product_id' => 4,
            'name' => 'Cloud LG-8',
            'specs' => ['cores' => 4, 'ram' => 8, 'disk' => 50],
            'provider_cost_monthly' => 150000,
            'markup_mode' => 'fixed_price',
            'retail_price_monthly' => 220000,
            'protect_cost_floor' => true,
            'min_margin_percent' => 10.0,
            'is_active' => true,
        ]);

        // Giả sử tạo sự kiện giảm cực sâu 50% + mã giảm thêm 50.000đ
        $campaign = PromotionCampaign::create([
            'name' => 'Mega Sale',
            'slug' => 'mega-sale',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'target_scope' => 'all',
            'is_active' => true,
            'protect_cost_floor' => true,
        ]);

        Coupon::create([
            'campaign_id' => $campaign->id,
            'code' => 'MEGA50K',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'applies_to' => 'all',
            'protect_cost_floor' => true,
            'is_active' => true,
        ]);

        app(PromotionEngine::class)->flushCache();
        $pricing = app(CloudSunnyPricingService::class);
        $pricing->flushCache();
        $plan = $pricing->getPlan('lg-8', $buyer);
        $this->assertEquals('Cloud LG-8', $pricing->getPlan('nova-pro', $buyer)['name']);

        $quote = $pricing->quoteCycleForPlan($plan, 'monthly', $buyer, 0, 0, 0, false, 'MEGA50K');

        // Giá gốc 150k + 10% min margin = 165k net; cộng dự phòng 5% affiliate => tối thiểu ~173.685đ
        $netAfterAffiliate = $quote['final_price'] * 0.95;
        $this->assertTrue($quote['floor_applied']);
        $this->assertGreaterThanOrEqual(165000, $netAfterAffiliate);
    }

    public function test_wallet_debit_idempotency_and_single_refund(): void
    {
        $user = User::create([
            'name' => 'Wallet User',
            'email' => 'wallet@gmail.com',
            'password' => Hash::make('password123'),
            'balance' => 500000,
        ]);

        $wallet = app(WalletService::class);

        $t1 = $wallet->debit($user, 120000, [
            'idempotency_key' => 'order_unique_01',
            'type' => 'buy',
            'provider_cost' => 85000,
            'description' => 'Test buy VPS',
        ]);

        // Gọi lặp lại cùng idempotency_key không bị trừ tiền lần 2
        $t2 = $wallet->debit($user, 120000, [
            'idempotency_key' => 'order_unique_01',
            'type' => 'buy',
            'provider_cost' => 85000,
            'description' => 'Test buy VPS',
        ]);

        $this->assertEquals($t1->id, $t2->id);
        $this->assertEquals(380000, $user->fresh()->balance);

        // Hoàn tiền 2 lần liên tiếp cũng chỉ hoàn đúng 1 lần
        $wallet->refundTransaction($t1, 'Node timeout');
        $wallet->refundTransaction($t1, 'Node timeout');

        $this->assertEquals(500000, $user->fresh()->balance);
        $this->assertEquals('refunded', $t1->fresh()->status);
    }

    public function test_upstream_error_sanitizer_masks_provider_identity(): void
    {
        $raw = 'Error connecting to https://api.cloudsunny.net/api/agency: CloudSunny insufficient credit';
        $masked = CloudSunnyApiService::sanitizeErrorMessage($raw);
        $this->assertStringNotContainsStringIgnoringCase('cloudsunny', $masked);
        $this->assertStringNotContainsStringIgnoringCase('agency', $masked);

        $curlRaw = 'cURL error 28: Operation timed out for https://api.cloudsunny.vn/agency/order-vps (see https://curl.haxx.se)';
        $curlSafe = CloudSunnyApiService::sanitizeErrorMessage($curlRaw);
        $this->assertStringNotContainsStringIgnoringCase('cloudsunny', $curlSafe);
        $this->assertStringNotContainsStringIgnoringCase('curl', $curlSafe);
        $this->assertStringNotContainsStringIgnoringCase('http', $curlSafe);

        $vnRaw = 'Lỗi từ hệ thống cloudsunny.vn: không tìm thấy sản phẩm';
        $vnSafe = CloudSunnyApiService::sanitizeErrorMessage($vnRaw);
        $this->assertStringNotContainsStringIgnoringCase('cloudsunny', $vnSafe);
    }

    public function test_admin_can_reassign_vps_and_proxy_to_another_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $buyer = User::factory()->create();

        $vps = \App\Models\VpsInstance::create([
            'user_id' => $admin->id,
            'label' => 'test-demo-01',
            'root_password' => 'TestPass123456',
            'region' => 'vn',
            'plan_id' => 'lg-4',
            'status' => 'Sẵn sàng',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 30,
            'cost_monthly_usd' => 0,
            'is_auto_renew' => true,
        ]);

        $proxy = \App\Models\ProxyInstance::create([
            'user_id' => $admin->id,
            'product_id' => 15,
            'type_proxy' => 'HTTP',
            'ip' => '103.1.2.3',
            'port' => '8080',
            'status' => 'Hoạt động',
            'is_auto_renew' => true,
        ]);

        $this->actingAs($admin)
            ->post("/admin/instances/{$vps->id}/reassign", ['user_id' => $buyer->id, 'disable_auto_renew' => true])
            ->assertSessionHas('success');

        $this->assertSame($buyer->id, $vps->fresh()->user_id);
        $this->assertFalse((bool) $vps->fresh()->is_auto_renew);

        $this->actingAs($admin)
            ->post("/admin/proxies/{$proxy->id}/reassign", ['user_id' => $buyer->id, 'disable_auto_renew' => true])
            ->assertSessionHas('success');

        $this->assertSame($buyer->id, $proxy->fresh()->user_id);
        $this->assertFalse((bool) $proxy->fresh()->is_auto_renew);
    }

    public function test_public_and_admin_pages_render_cleanly(): void
    {
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        $this->get('/')->assertStatus(200);
        $this->get('/login')->assertStatus(200);
        $this->get('/support')->assertStatus(200);

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-test@gmail.com',
            'password' => Hash::make('password123'),
            'is_admin' => UserRole::ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertStatus(200);
        $this->actingAs($admin)->get('/vps')->assertStatus(200);
        $this->actingAs($admin)->get('/overview')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/admin/pricing')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/promotions')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/revenue')->assertStatus(200);
    }

    public function test_stale_expected_total_prevents_accidental_charge(): void
    {
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        $buyer = User::create([
            'name' => 'Fast Slider Buyer',
            'email' => 'fastbuyer@gmail.com',
            'password' => Hash::make('password123'),
            'balance' => 2000000,
            'email_verified_at' => now(),
        ]);

        // Kịch bản: khách kéo vCPU lên +8 Core nhưng gửi lên expected_total cũ (50.000đ)
        $response = $this->actingAs($buyer)->from('/store/lg-1')->post('/store', [
            'plan' => 'lg-1',
            'region' => 'vn',
            'duration' => 'monthly',
            'label' => 'test-fast-slider',
            'image' => 7,
            'addon_cpu' => 8,
            'addon_ram' => 4,
            'addon_disk' => 50,
            'expected_total' => 50000,
        ]);

        $response->assertRedirect('/store/lg-1');
        $response->assertSessionHas('error');
        $this->assertEquals(2000000, (int) $buyer->fresh()->balance);
    }

    public function test_multi_quantity_vps_quote_and_admin_event_update(): void
    {
        $pricing = app(CloudSunnyPricingService::class);
        $pricing->ensureCatalogBootstrapped();

        $campaign = PromotionCampaign::create([
            'name' => 'Sale Mùa Hè',
            'slug' => 'sale-mua-he',
            'event_tag' => 'summer',
            'badge_text' => '-10%',
            'accent_color' => '#1D6AE5',
            'is_active' => true,
            'priority' => 10,
            'discount_type' => 'percent',
            'discount_value' => 10,
            'target_scope' => 'all',
            'min_months' => 1,
            'apply_on_renew' => false,
            'stack_with_user_discount' => false,
            'protect_cost_floor' => true,
            'topup_bonus_percent' => 5,
        ]);
        app(PromotionEngine::class)->flushCache();

        $plan = $pricing->getPlan('lg-1');
        $q1 = $pricing->quoteCycleForPlan($plan, 'monthly', null, 1, 0, 10, false, null, 1);
        $q5 = $pricing->quoteCycleForPlan($plan, 'monthly', null, 1, 0, 10, false, null, 5);

        // Đơn giá 1 VPS = 55k + 25k CPU + 15k SSD = 95k -> giảm 10% = 85.5k -> x5 VPS = 427.5k
        $this->assertEquals(95000, $q1['list_price']);
        $this->assertEquals(85500, $q1['final_price']);
        $this->assertEquals(475000, $q5['list_price']);
        $this->assertEquals(427500, $q5['final_price']);

        // Kiểm tra Admin sửa sự kiện (PUT /admin/promotions/campaigns/{id})
        $admin = User::create([
            'name' => 'Admin Event',
            'email' => 'admin-event@gmail.com',
            'password' => Hash::make('password123'),
            'is_admin' => UserRole::ADMIN,
            'email_verified_at' => now(),
        ]);

        $res = $this->actingAs($admin)->put("/admin/promotions/campaigns/{$campaign->id}", [
            'name' => 'Sale Mùa Hè Nâng Cấp',
            'event_tag' => 'summer',
            'badge_text' => 'SALE -15%',
            'accent_color' => '#1D6AE5',
            'description' => 'Cập nhật mức giảm',
            'is_active' => true,
            'priority' => 25,
            'discount_type' => 'percent',
            'discount_value' => 15,
            'target_scope' => 'all',
            'min_months' => 1,
            'apply_on_renew' => true,
            'stack_with_user_discount' => false,
            'protect_cost_floor' => true,
            'topup_bonus_percent' => 12,
        ]);

        $res->assertStatus(302);
        $this->assertEquals('Sale Mùa Hè Nâng Cấp', $campaign->fresh()->name);
        $this->assertEquals(15, (int) $campaign->fresh()->discount_value);
        $this->assertEquals(12.0, (float) $campaign->fresh()->topup_bonus_percent);
    }

    public function test_reseller_api_portal_permissions_and_endpoints(): void
    {
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        $customer = User::create([
            'name' => 'Standard User',
            'email' => 'customer-api@gmail.com',
            'password' => Hash::make('password123'),
            'is_admin' => UserRole::CUSTOMER,
            'api_key' => 'sk_standard_user_should_be_blocked',
            'email_verified_at' => now(),
        ]);

        // Khách lẻ vẫn vào đọc tài liệu tại /reseller-api được, nhưng không được cấp Key hay gọi API
        $this->actingAs($customer)->get('/reseller-api')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('ResellerApi/Index')
                ->where('canUseApi', false)
                ->where('apiKey', null)
            );
        $this->actingAs($customer)->post('/reseller-api/regenerate')->assertStatus(403);
        $this->actingAs($customer)->post('/reseller-api/webhook', ['webhook_url' => 'https://example.com/hook'])->assertStatus(403);

        $this->getJson('/api/v1/profile')->assertStatus(401);
        $this->withToken('sk_standard_user_should_be_blocked')->getJson('/api/v1/profile')->assertStatus(403);

        // Đại lý (role = 2) được cấp Key, lưu Webhook và gọi API v1 hưởng chiết khấu sỉ
        $reseller = User::create([
            'name' => 'Reseller Partner',
            'email' => 'reseller-partner@gmail.com',
            'password' => Hash::make('password123'),
            'is_admin' => UserRole::RESELLER,
            'discount_percent' => 10,
            'balance' => 30000,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($reseller)->get('/reseller-api')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('ResellerApi/Index')
                ->where('canUseApi', true)
            );

        $apiKey = $reseller->fresh()->api_key;
        $this->assertNotEmpty($apiKey);
        $this->assertStringStartsWith('sk_', $apiKey);

        $this->actingAs($reseller)
            ->post('/reseller-api/webhook', ['webhook_url' => 'https://partner.vn/webhook'])
            ->assertStatus(302);
        $this->assertEquals('https://partner.vn/webhook', $reseller->fresh()->webhook_url);

        $this->withToken($apiKey)->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.discount_percent', 10);

        $this->withToken($apiKey)->getJson('/api/v1/plans')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Kiểm tra mã lỗi 402 khi số dư ví Đại lý không đủ
        $this->withToken($apiKey)->postJson('/api/v1/vps/create', [
            'plan_id' => 'lg-8',
            'os_id' => 7,
            'duration' => 1,
        ])->assertStatus(402);
    }

    public function test_customer_vps_and_proxy_deletion_moves_to_deleted_tab_without_hard_delete(): void
    {
        $user = User::create([
            'name' => 'Delete Flow User',
            'email' => 'delete-flow@gmail.com',
            'password' => Hash::make('password123'),
            'balance' => 500000,
            'email_verified_at' => now(),
        ]);

        $vps = VpsInstance::create([
            'user_id' => $user->id,
            'label' => 'vps-soft-del-01',
            'plan_id' => 'lg-2',
            'region' => 'VN',
            'cpu' => 2,
            'ram' => 2,
            'disk' => 30,
            'public_ip' => '103.100.10.20',
            'root_password' => 'SecretPass123!',
            'cost_monthly_usd' => 4.0,
            'status' => 'Sẵn sàng',
            'is_auto_renew' => true,
            'expires_at' => now()->addDays(15),
        ]);

        $proxy = ProxyInstance::create([
            'user_id' => $user->id,
            'product_id' => 1,
            'type_proxy' => 'HTTP',
            'ip' => '103.100.10.99',
            'port' => '8080',
            'username' => 'pxuser',
            'password' => 'pxpass',
            'status' => 'Đang chạy',
            'is_auto_renew' => true,
            'paid_amount' => 39000,
            'expires_at' => now()->addDays(15),
        ]);

        // 1. Xóa VPS: giữ nguyên record trong DB, chuyển status = 'Đã xóa', tắt auto-renew, chuyển sang tab deleted
        $this->actingAs($user)
            ->delete("/vps/{$vps->id}")
            ->assertRedirect('/vps?status=deleted');

        $vps->refresh();
        $this->assertEquals('Đã xóa', $vps->status);
        $this->assertEquals('deleted', $vps->normalizedState());
        $this->assertFalse((bool) $vps->is_auto_renew);

        // Không cho bật lại tự động gia hạn trên VPS đã xóa
        $this->actingAs($user)
            ->post("/vps/{$vps->id}/auto-renew", ['is_auto_renew' => true])
            ->assertSessionHas('error');
        $this->assertFalse((bool) $vps->fresh()->is_auto_renew);

        // 2. Xóa Proxy: giữ nguyên record trong DB, chuyển status = 'Đã xóa', tắt auto-renew, chuyển sang tab deleted
        $this->actingAs($user)
            ->delete("/proxy/{$proxy->id}")
            ->assertRedirect('/proxy?status=deleted');

        $proxy->refresh();
        $this->assertEquals('Đã xóa', $proxy->status);
        $this->assertEquals('deleted', $proxy->normalizedState());
        $this->assertFalse((bool) $proxy->is_auto_renew);

        // Không cho bật lại tự động gia hạn hoặc gia hạn trên Proxy đã xóa
        $this->actingAs($user)
            ->post("/proxy/{$proxy->id}/auto-renew", ['is_auto_renew' => true])
            ->assertSessionHas('error');
        $this->assertFalse((bool) $proxy->fresh()->is_auto_renew);

        $this->actingAs($user)
            ->post("/proxy/{$proxy->id}/renew", ['billing_cycle' => 'monthly'])
            ->assertSessionHas('error');

        // Kiểm tra bộ đếm trên /proxy tách đúng tab Đã xóa (deleted = 1, active = 0)
        $this->actingAs($user)
            ->get('/proxy?status=deleted')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Proxy/Index')
                ->where('counts.deleted', 1)
                ->where('counts.active', 0)
                ->where('counts.expired', 0)
                ->has('instances.data', 1)
            );
    }

    public function test_security_headers_seo_and_fail_closed_topup_webhook(): void
    {
        // 1. Security Headers + SEO Canonical & OG Cover
        $home = $this->get('/?ref=TEST123');
        $home->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertSee('<link rel="canonical" href="' . url('/') . '">', false)
            ->assertSee('og-cover.jpg', false);

        // 2. Sitemap trả về XML hợp lệ kèm <lastmod>
        $this->get('/sitemap.xml')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<lastmod>', false);

        // 3. Fail-closed Webhook: không có chữ ký & không có webhook_secret vẫn bị từ chối 401
        config(['deposit.webhook_secret' => null]);
        $this->postJson('/webhooks/topups', [
            'content' => 'LUGIA999',
            'amount' => 100000,
        ])->assertStatus(401);

        // 4. Có webhook_secret nhưng sai secret -> 401
        config(['deposit.webhook_secret' => 'super-secret-key']);
        $this->postJson('/webhooks/topups', [
            'content' => 'LUGIA999',
            'amount' => 100000,
        ], ['X-Webhook-Secret' => 'wrong-secret'])->assertStatus(401);
    }
}

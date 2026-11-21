<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\PricingCatalog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CloudSunnyPricingService;
use App\Services\FlashSaleService;
use App\Services\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FlashSaleAndVoucherHuntTest extends TestCase
{
    use RefreshDatabase;

    private function seedLg4Catalog(): PricingCatalog
    {
        return PricingCatalog::create([
            'service_type' => 'vps',
            'slug' => 'lg-4',
            'provider_code' => 'sn-2-4-30',
            'provider_product_id' => 3,
            'name' => 'VPS Pro 4GB',
            'description' => 'Test plan',
            'specs' => ['cores' => 2, 'ram' => 4, 'disk' => 30, 'network_out_mbps' => 1000],
            'provider_cost_monthly' => 81000,
            'markup_mode' => 'fixed_price',
            'markup_percent' => 48.1,
            'retail_price_monthly' => 120000,
            'protect_cost_floor' => true,
            'min_margin_percent' => 5.0,
            'is_active' => true,
            'sort_order' => 30,
        ]);
    }

    public function test_flash_sale_applies_exclusively_respects_floor_and_cycle_limit(): void
    {
        $this->seedLg4Catalog();
        $user = User::factory()->create(['discount_percent' => 10]);

        $coupon = Coupon::create([
            'code' => 'EXTRA10',
            'description' => 'Giảm 10%',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_order_amount' => 0,
            'applies_to' => 'all',
            'apply_on_renew' => false,
            'protect_cost_floor' => true,
            'max_uses' => 100,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ]);

        // Flash sale giảm 25% trên lg-4 (120k * 0.75 = 90k > sàn 85,050đ)
        FlashSale::create([
            'name' => 'Flash Sale VPS 4GB',
            'badge_text' => 'FLASH SALE -25%',
            'service_type' => 'vps',
            'plan_slug' => 'lg-4',
            'discount_type' => 'percent',
            'discount_value' => 25,
            'total_slots' => 5,
            'sold_slots' => 0,
            'max_per_user' => 1,
            'max_months' => 1,
            'protect_cost_floor' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(6),
            'is_active' => true,
        ]);

        $pricing = app(CloudSunnyPricingService::class);
        $pricing->flushCache();
        app(FlashSaleService::class)->flushCache();

        $plan = $pricing->getPlan('lg-4', $user);
        $this->assertNotNull($plan);

        // 1. Mua 1 tháng có kèm coupon -> Flash Sale thắng, coupon bị từ chối rõ ràng, không cộng dồn VIP 10%
        $quote1m = $pricing->quoteCycleForPlan($plan, 'monthly', $user, 0, 0, 0, false, $coupon->code, 1);
        $this->assertNotNull($quote1m['flash_sale']);
        $this->assertSame(90000, $quote1m['final_price']);
        $this->assertSame(0, $quote1m['user_discount_amount']);
        $this->assertFalse($quote1m['coupon']['valid']);

        // 2. Mua 3 tháng (vượt max_months = 1) -> Flash Sale không áp dụng, chuyển sang VIP/Coupon bình thường
        $quote3m = $pricing->quoteCycleForPlan($plan, 'quarterly', $user, 0, 0, 0, false, null, 1);
        $this->assertNull($quote3m['flash_sale']);

        // 3. Gia hạn -> Flash Sale không áp dụng
        $quoteRenew = $pricing->quoteCycleForPlan($plan, 'monthly', $user, 0, 0, 0, true, null, 1);
        $this->assertNull($quoteRenew['flash_sale']);
    }

    public function test_flash_sale_atomic_slot_reservation_and_release_on_refund(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $deal = FlashSale::create([
            'name' => 'Flash Sale 2 Suất',
            'service_type' => 'vps',
            'plan_slug' => 'lg-4',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'total_slots' => 2,
            'sold_slots' => 0,
            'max_per_user' => 1,
            'max_months' => 1,
            'protect_cost_floor' => true,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $service = app(FlashSaleService::class);
        $txn1 = Transaction::create(['user_id' => $user1->id, 'type' => 'buy', 'amount' => -96000, 'status' => 'completed', 'description' => 'Test 1']);
        $txn2 = Transaction::create(['user_id' => $user2->id, 'type' => 'buy', 'amount' => -96000, 'status' => 'completed', 'description' => 'Test 2']);

        // User 1 săn suất 1
        $service->reserveAndConsumeSlot($deal->id, $user1, 1, $txn1->id, 24000);
        $this->assertSame(1, $deal->fresh()->sold_slots);

        // User 1 cố săn thêm -> bị chặn max_per_user
        $this->expectException(RuntimeException::class);
        try {
            $service->reserveAndConsumeSlot($deal->id, $user1, 1, null, 24000);
        } finally {
            // User 2 săn suất cuối (2/2)
            $service->reserveAndConsumeSlot($deal->id, $user2, 1, $txn2->id, 24000);
            $this->assertSame(2, $deal->fresh()->sold_slots);

            // User 3 cố săn suất thứ 3 -> bị chặn hết suất
            $blocked = false;
            try {
                $service->reserveAndConsumeSlot($deal->id, $user3, 1, null, 24000);
            } catch (RuntimeException $e) {
                $blocked = true;
            }
            $this->assertTrue($blocked);

            // Hoàn trả suất khi txn2 bị lỗi hoàn tiền -> sold_slots quay về 1
            $service->releaseSlotByTransaction($txn2->id);
            $this->assertSame(1, $deal->fresh()->sold_slots);
        }
    }

    public function test_voucher_hunt_claim_limit_uniqueness_and_auto_clear_after_use(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $coupon = Coupon::create([
            'code' => 'SANMA20K',
            'description' => 'Giảm 20.000đ đơn từ 100k',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'min_order_amount' => 100000,
            'applies_to' => 'all',
            'apply_on_renew' => false,
            'protect_cost_floor' => true,
            'max_uses' => 50,
            'max_uses_per_user' => 1,
            'is_huntable' => true,
            'claim_limit' => 1,
            'claimed_count' => 0,
            'is_active' => true,
        ]);

        // User 1 lưu mã thành công
        $resp1 = $this->actingAs($user1)->post("/vouchers/{$coupon->id}/claim");
        $resp1->assertSessionHas('success');
        $this->assertSame(1, $coupon->fresh()->claimed_count);

        // User 1 có mã trong ví
        $flashService = app(FlashSaleService::class);
        $walletVouchers = $flashService->getUserClaimedVouchers($user1, 'vps');
        $this->assertCount(1, $walletVouchers);
        $this->assertSame('SANMA20K', $walletVouchers[0]['code']);

        // User 1 bấm lưu lần 2 -> bị chặn trùng
        $respDup = $this->actingAs($user1)->post("/vouchers/{$coupon->id}/claim");
        $respDup->assertSessionHas('error');

        // User 2 bấm lưu -> bị chặn vì claim_limit = 1 đã hết
        $resp2 = $this->actingAs($user2)->post("/vouchers/{$coupon->id}/claim");
        $resp2->assertSessionHas('error');

        // User 1 sử dụng mã khi thanh toán -> mã tự biến mất khỏi ví chưa dùng
        $txn = Transaction::create(['user_id' => $user1->id, 'type' => 'buy', 'amount' => -100000, 'status' => 'completed', 'description' => 'Use voucher']);
        app(PromotionEngine::class)->recordCouponUsage('SANMA20K', $user1, $txn, 20000);
        $this->assertCount(0, $flashService->getUserClaimedVouchers($user1, 'vps'));
    }

    public function test_welcome_offer_grants_on_register_enforces_7_days_1_month_and_first_order_only(): void
    {
        $this->seedLg4Catalog();
        $welcomeService = app(\App\Services\WelcomeOfferService::class);
        $welcomeService->syncWelcomeCoupon();

        // Guest sees guest mode
        $guestOffer = $welcomeService->getActiveOfferForUser(null);
        $this->assertSame('guest', $guestOffer['mode']);
        $this->assertSame(10, $guestOffer['percent']);

        // New user registration auto-grants WELCOME10 (7 days)
        $resp = $this->post('/register', [
            'name' => 'Khach Moi Test',
            'email' => 'khachmoi@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $resp->assertRedirect('/dashboard');

        $newUser = User::where('email', 'khachmoi@example.com')->firstOrFail();
        $eligibleOffer = $welcomeService->getActiveOfferForUser($newUser);
        $this->assertSame('eligible', $eligibleOffer['mode']);
        $this->assertSame('WELCOME10', $eligibleOffer['code']);

        $pricing = app(CloudSunnyPricingService::class);
        $pricing->flushCache();
        $plan = $pricing->getPlan('lg-4', $newUser);

        // 1 tháng -> giảm 10% (120k -> 108k)
        $q1m = $pricing->quoteCycleForPlan($plan, 'monthly', $newUser, 0, 0, 0, false, 'WELCOME10', 1);
        $this->assertTrue($q1m['coupon']['valid']);
        $this->assertSame(12000, $q1m['coupon']['discount_amount']);
        $this->assertSame(108000, $q1m['final_price']);

        // 3 tháng -> bị từ chối do vượt max_months = 1
        $q3m = $pricing->quoteCycleForPlan($plan, 'quarterly', $newUser, 0, 0, 0, false, 'WELCOME10', 1);
        $this->assertFalse($q3m['coupon']['valid']);

        // Sau khi hoàn tất đơn mua đầu tiên -> WELCOME10 tự ẩn & không thể dùng lại
        Transaction::create([
            'user_id' => $newUser->id,
            'type' => 'buy',
            'amount' => -108000,
            'status' => 'completed',
            'description' => 'First VPS order',
        ]);
        $this->assertNull($welcomeService->getActiveOfferForUser($newUser));
        $qSecond = $pricing->quoteCycleForPlan($plan, 'monthly', $newUser, 0, 0, 0, false, 'WELCOME10', 1);
        $this->assertFalse($qSecond['coupon']['valid']);
    }
}

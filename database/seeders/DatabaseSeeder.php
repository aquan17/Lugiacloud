<?php

namespace Database\Seeders;

use App\Constants\UserRole;
use App\Models\Coupon;
use App\Models\PromotionBanner;
use App\Models\PromotionCampaign;
use App\Models\User;
use App\Services\CloudSunnyPricingService;
use App\Services\PromotionEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        if (!User::where('email', 'admin@lugiacloud.vn')->exists()) {
            User::create([
                'name' => 'Quản trị LugiaCloud',
                'email' => 'admin@lugiacloud.vn',
                'phone' => '0988888888',
                'password' => Hash::make('Admin@123456'),
                'balance' => 15000000,
                'is_admin' => UserRole::ADMIN,
                'ref_code' => 'LUGIAADM',
                'email_verified_at' => now(),
            ]);
        }

        if (PromotionCampaign::count() === 0) {
            $campaign = PromotionCampaign::create([
                'name' => 'Lễ Hội Cloud Cuối Năm — Tri Ân Khách Hàng',
                'slug' => 'le-hoi-cloud-cuoi-nam',
                'event_tag' => 'seasonal',
                'badge_text' => 'ƯU ĐÃI SỰ KIỆN -15%',
                'accent_color' => '#1D6AE5',
                'description' => 'Giảm trực tiếp 15% toàn bộ gói Cloud VPS SSD & Proxy IPv4 Việt Nam, tặng thêm 10% giá trị nạp ví tự động.',
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(14),
                'is_active' => true,
                'priority' => 20,
                'discount_type' => 'percent',
                'discount_value' => 15,
                'target_scope' => 'all',
                'min_months' => 1,
                'apply_on_renew' => false,
                'stack_with_user_discount' => false,
                'protect_cost_floor' => true,
                'topup_bonus_percent' => 10,
            ]);

            PromotionBanner::create([
                'campaign_id' => $campaign->id,
                'placement' => 'top_bar',
                'eyebrow' => 'SỰ KIỆN ĐẶC BIỆT',
                'title' => 'Giảm tự động 15% Cloud VPS & Proxy IPv4 + Tặng thêm 10% giá trị nạp tiền',
                'cta_text' => 'Nhận ưu đãi',
                'cta_url' => '#pricing',
                'coupon_code_display' => 'LUGIA2026',
                'theme_preset' => 'navy_ocean',
                'bg_from' => '#0B1437',
                'bg_to' => '#1D4ED8',
                'ends_at' => $campaign->ends_at,
                'is_active' => true,
                'sort_order' => 1,
            ]);

            PromotionBanner::create([
                'campaign_id' => $campaign->id,
                'placement' => 'hero_showcase',
                'eyebrow' => 'ĐẠI TIỆC HẠ TẦNG CUỐI NĂM',
                'title' => 'Giảm trực tiếp 15% toàn bộ Cloud VPS SSD & Proxy IPv4 Việt Nam',
                'subtitle' => 'Giá ưu đãi đã được trừ tự động trên bảng giá bên dưới. Nhập thêm mã LUGIA2026 ở bước thanh toán cho đơn từ 100.000đ để giảm sâu hơn.',
                'cta_text' => 'Xem gói đang giảm giá',
                'cta_url' => '#pricing',
                'coupon_code_display' => 'LUGIA2026',
                'theme_preset' => 'navy_ocean',
                'bg_from' => '#0F1A42',
                'bg_to' => '#1D6AE5',
                'ends_at' => $campaign->ends_at,
                'is_active' => true,
                'sort_order' => 1,
            ]);

            PromotionBanner::create([
                'campaign_id' => $campaign->id,
                'placement' => 'dashboard_banner',
                'eyebrow' => 'KHUYẾN MÃI NẠP VÍ',
                'title' => 'Tặng tự động +10% số dư khi nạp tiền qua VietQR / PayOS trong thời gian sự kiện',
                'subtitle' => 'Tiền thưởng được cộng ngay lập tức cùng giao dịch gốc hoàn toàn tự động 24/7.',
                'cta_text' => 'Nạp tiền nhận thưởng',
                'cta_url' => '/topup',
                'coupon_code_display' => 'LUGIA2026',
                'theme_preset' => 'navy_ocean',
                'bg_from' => '#0F1A42',
                'bg_to' => '#1D4ED8',
                'ends_at' => $campaign->ends_at,
                'is_active' => true,
                'sort_order' => 1,
            ]);

            Coupon::create([
                'campaign_id' => $campaign->id,
                'code' => 'LUGIA2026',
                'description' => 'Giảm thêm 10% (tối đa 50.000đ) cho đơn đăng ký mới từ 100.000đ',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_discount_amount' => 50000,
                'min_order_amount' => 100000,
                'applies_to' => 'all',
                'apply_on_renew' => false,
                'protect_cost_floor' => true,
                'max_uses' => 500,
                'max_uses_per_user' => 3,
                'starts_at' => $campaign->starts_at,
                'ends_at' => $campaign->ends_at,
                'is_active' => true,
            ]);

            app(PromotionEngine::class)->flushCache();
            app(CloudSunnyPricingService::class)->flushCache();
        }

        $this->call(DummyDataSeeder::class);
    }
}

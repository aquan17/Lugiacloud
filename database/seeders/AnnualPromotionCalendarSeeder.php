<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\PricingCatalog;
use App\Models\PromotionBanner;
use App\Models\PromotionCampaign;
use App\Services\CloudSunnyPricingService;
use App\Services\FlashSaleService;
use App\Services\PromotionEngine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AnnualPromotionCalendarSeeder extends Seeder
{
    /**
     * Khởi tạo bộ Lịch Sự Kiện Lễ Việt Nam + Ngày Đôi (Shopee-style) trọn năm & Gói Khai Trương mở bán VPS.
     * Toàn bộ mức giảm đã tính sát biên lợi nhuận thực tế (30–40%) và bật protect_cost_floor = true (không bao giờ bán lỗ).
     * Idempotent: chạy lại nhiều lần không tạo trùng bản ghi.
     */
    public function run(): void
    {
        $now = Carbon::now();

        /**
         * Tính khung ngày sắp tới cho sự kiện cố định theo ngày/tháng dương lịch.
         * Nếu khung sự kiện trong năm hiện tại đã kết thúc, tự động xếp lịch cho năm kế tiếp để tái sử dụng quanh năm.
         */
        $nextAnnualWindow = function (int $startMonth, int $startDay, int $durationDays) use ($now): array {
            $year = (int) $now->year;
            $start = Carbon::create($year, $startMonth, $startDay, 0, 0, 0);
            $end = $start->copy()->addDays(max(1, $durationDays) - 1)->setTime(23, 59, 59);

            if ($end->lt($now)) {
                $start->addYear();
                $end->addYear();
            }

            return [$start, $end];
        };

        // Tết Nguyên Đán Đinh Mùi 2027 (Mùng 1 = 06/02/2027) -> Mở từ 28 Tết (04/02) đến hết Mùng 8 (13/02) = 10 ngày
        $tetStart = Carbon::create(2027, 2, 4, 0, 0, 0);
        $tetEnd = Carbon::create(2027, 2, 13, 23, 59, 59);
        if ($tetEnd->lt($now)) {
            $tetStart->addYear();
            $tetEnd->addYear();
        }

        // Khai trương mở bán (ĐANG CHẠY NGAY 14 NGÀY)
        $launchStart = $now->copy()->startOfDay();
        $launchEnd = $now->copy()->addDays(13)->setTime(23, 59, 59);

        $events = [
            // 0. SỰ KIỆN ĐANG MỞ NGAY KHI DEPLOY VPS
            [
                'slug' => 'khai-truong-ha-tang-lugiacloud',
                'campaign' => [
                    'name' => 'Khai Trương Hạ Tầng LugiaCloud Thế Hệ Mới',
                    'event_tag' => 'launch',
                    'badge_text' => 'KHAI TRƯƠNG -8% · NẠP +3%',
                    'accent_color' => '#1D6AE5',
                    'description' => 'Mừng ra mắt nền tảng LugiaCloud mới: Giảm trực tiếp 8% toàn bộ Cloud VPS & Proxy IPv4, tặng thêm 3% giá trị mọi đơn nạp ví.',
                    'starts_at' => $launchStart,
                    'ends_at' => $launchEnd,
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 100,
                    'discount_type' => 'percent',
                    'discount_value' => 8,
                    'max_discount_amount' => 150000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 3,
                ],
                'coupons' => [
                    [
                        'code' => 'LUGIAVPS10',
                        'description' => 'Săn mã Khai Trương: Giảm 10% đơn Cloud VPS (tối đa 60.000đ, đơn từ 70.000đ)',
                        'discount_type' => 'percent',
                        'discount_value' => 10,
                        'max_discount_amount' => 60000,
                        'min_order_amount' => 70000,
                        'applies_to' => 'vps',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 200,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 200,
                        'is_active' => true,
                    ],
                    [
                        'code' => 'PROXY15K',
                        'description' => 'Săn mã Combo Proxy: Giảm thẳng 15.000đ cho đơn Proxy IPv4 từ 75.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 15000,
                        'max_discount_amount' => 15000,
                        'min_order_amount' => 75000,
                        'applies_to' => 'proxy',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 200,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 200,
                        'is_active' => true,
                    ],
                ],
                'banners' => [
                    [
                        'placement' => 'top_bar',
                        'eyebrow' => 'KHAI TRƯƠNG HẠ TẦNG MỚI',
                        'title' => 'Giảm ngay 8% toàn bộ Cloud VPS & Proxy IPv4 · Tặng thêm +3% nạp ví',
                        'subtitle' => 'Săn thêm voucher LUGIAVPS10 (-10%) & Flash Sale VPS chỉ từ 102.000đ/tháng.',
                        'cta_text' => 'Săn ưu đãi ngay',
                        'cta_url' => '#pricing',
                        'coupon_code_display' => 'LUGIAVPS10',
                        'theme_preset' => 'navy_ocean',
                        'bg_from' => '#0B1437',
                        'bg_to' => '#1D4ED8',
                        'sort_order' => 1,
                    ],
                    [
                        'placement' => 'hero_showcase',
                        'eyebrow' => 'TUẦN LỄ KHAI TRƯƠNG · 14 NGÀY',
                        'title' => 'Cloud VPS FPT & Proxy Sạch — Ưu đãi Khai trương giảm tới 15%',
                        'subtitle' => 'Giảm trực tiếp 8% mọi gói, thưởng +3% mọi giao dịch nạp VietQR và kho Voucher săn miễn phí mỗi ngày.',
                        'cta_text' => 'Khởi tạo VPS ngay',
                        'cta_url' => '/store',
                        'coupon_code_display' => 'LUGIAVPS10',
                        'theme_preset' => 'navy_ocean',
                        'bg_from' => '#0B1437',
                        'bg_to' => '#1D6AE5',
                        'sort_order' => 1,
                    ],
                ],
            ],

            // 1. BLACK FRIDAY & CYBER MONDAY (5 ngày: 26/11 -> 30/11)
            [
                'slug' => 'black-friday-cyber-monday',
                'window' => $nextAnnualWindow(11, 26, 5),
                'campaign' => [
                    'name' => 'Black Friday & Cyber Monday — Đại Tiệc Cloud',
                    'event_tag' => 'black_friday',
                    'badge_text' => 'BLACK FRIDAY -15%',
                    'accent_color' => '#0F172A',
                    'description' => 'Mùa săn Cloud lớn nhất năm: Giảm 15% toàn bộ VPS & Proxy (tối đa 300.000đ, áp dụng gia hạn từ 3 tháng) + Thưởng nạp 5%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 95,
                    'discount_type' => 'percent',
                    'discount_value' => 15,
                    'max_discount_amount' => 300000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'BLACKFRI15',
                        'description' => 'Black Friday: Giảm 15% (tối đa 150.000đ) cho đơn từ 250.000đ (gồm gia hạn)',
                        'discount_type' => 'percent',
                        'discount_value' => 15,
                        'max_discount_amount' => 150000,
                        'min_order_amount' => 250000,
                        'applies_to' => 'all',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],

            // 2. SIÊU SALE NGÀY ĐÔI 12/12 (3 ngày: 11/12 -> 13/12)
            [
                'slug' => 'sieu-sale-ngay-doi-12-12',
                'window' => $nextAnnualWindow(12, 11, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 12/12 — Xả Kho Cuối Năm',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 12/12 -12%',
                    'accent_color' => '#DC2626',
                    'description' => 'Bùng nổ ngày đôi 12/12: Giảm 12% toàn bộ dịch vụ VPS & Proxy + Thưởng nạp 5% trong 72 giờ vàng.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 90,
                    'discount_type' => 'percent',
                    'discount_value' => 12,
                    'max_discount_amount' => 200000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'SALE1212',
                        'description' => 'Ngày đôi 12/12: Giảm 15% (tối đa 100.000đ) cho đơn từ 180.000đ',
                        'discount_type' => 'percent',
                        'discount_value' => 15,
                        'max_discount_amount' => 100000,
                        'min_order_amount' => 180000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],

            // 3. GIÁNG SINH NOEL (4 ngày: 23/12 -> 26/12)
            [
                'slug' => 'giang-sinh-noel-am-ap',
                'window' => $nextAnnualWindow(12, 23, 4),
                'campaign' => [
                    'name' => 'Giáng Sinh An Lành — Quà Tặng Noel Cloud',
                    'event_tag' => 'christmas',
                    'badge_text' => 'NOEL -10% · NẠP +5%',
                    'accent_color' => '#059669',
                    'description' => 'Đón Giáng Sinh rộn ràng: Giảm 10% mọi gói VPS & Proxy, tặng thêm +5% khi nạp số dư ví.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 85,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'max_discount_amount' => 150000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'XMAS25K',
                        'description' => 'Quà Noel: Giảm thẳng 25.000đ cho đơn VPS/Proxy từ 150.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 25000,
                        'max_discount_amount' => 25000,
                        'min_order_amount' => 150000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],

            // 4. TẾT DƯƠNG LỊCH 01/01 (4 ngày: 30/12 -> 02/01)
            [
                'slug' => 'tet-duong-lich-chao-nam-moi',
                'window' => $nextAnnualWindow(12, 30, 4),
                'campaign' => [
                    'name' => 'Chào Năm Mới — Tết Dương Lịch 01/01',
                    'event_tag' => 'new_year',
                    'badge_text' => 'NEW YEAR -10% · NẠP +5%',
                    'accent_color' => '#2563EB',
                    'description' => 'Khởi đầu năm mới bứt phá: Giảm 10% toàn bộ VPS & Proxy (tối đa 150.000đ) + Thưởng nạp ví +5%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 88,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'max_discount_amount' => 150000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'NEWYEAR12',
                        'description' => 'Tết Dương Lịch: Giảm 12% (tối đa 80.000đ) đơn từ 120.000đ',
                        'discount_type' => 'percent',
                        'discount_value' => 12,
                        'max_discount_amount' => 80000,
                        'min_order_amount' => 120000,
                        'applies_to' => 'all',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 5. TẾT NGUYÊN ĐÁN (10 ngày dịp Tết Âm lịch — Lễ lớn nhất Việt Nam)
            [
                'slug' => 'tet-nguyen-dan-li-xi-dau-xuan',
                'window' => [$tetStart, $tetEnd],
                'campaign' => [
                    'name' => 'Tết Nguyên Đán — Lì Xì Khai Xuân LugiaCloud',
                    'event_tag' => 'tet',
                    'badge_text' => 'TẾT -12% · LÌ XÌ NẠP +6%',
                    'accent_color' => '#B91C1C',
                    'description' => 'Khai xuân như ý: Giảm 12% mua mới & gia hạn (tối đa 200.000đ) + Lì xì ngay +6% giá trị mọi đơn nạp VietQR.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 98,
                    'discount_type' => 'percent',
                    'discount_value' => 12,
                    'max_discount_amount' => 200000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 6,
                ],
                'coupons' => [
                    [
                        'code' => 'LIXI50K',
                        'description' => 'Lì Xì Tết: Giảm thẳng 50.000đ cho đơn VPS/Proxy hoặc Gia hạn từ 300.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 50000,
                        'max_discount_amount' => 50000,
                        'min_order_amount' => 300000,
                        'applies_to' => 'all',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 200,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 200,
                        'is_active' => true,
                    ],
                ],
            ],

            // 6. NGÀY ĐÔI 3/3 (3 ngày: 02/03 -> 04/03)
            [
                'slug' => 'sieu-sale-ngay-doi-3-3',
                'window' => $nextAnnualWindow(3, 2, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 3/3 — Bứt Tốc Cloud VPS',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 3/3 · VPS -8%',
                    'accent_color' => '#EA580C',
                    'description' => 'Ngày đôi 3/3 ưu đãi riêng cho hạ tầng Cloud VPS SSD: Giảm 8% tự động + Săn thêm mã giảm 10%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 75,
                    'discount_type' => 'percent',
                    'discount_value' => 8,
                    'max_discount_amount' => 120000,
                    'target_scope' => 'vps',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 0,
                ],
                'coupons' => [
                    [
                        'code' => 'SALE0303',
                        'description' => 'Ngày đôi 3/3: Giảm 10% Cloud VPS (tối đa 50.000đ, đơn từ 100.000đ)',
                        'discount_type' => 'percent',
                        'discount_value' => 10,
                        'max_discount_amount' => 50000,
                        'min_order_amount' => 100000,
                        'applies_to' => 'vps',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 100,
                        'max_uses_per_user' => 1,
                        'is_huntable' => true,
                        'claim_limit' => 100,
                        'is_active' => true,
                    ],
                ],
            ],

            // 7. QUỐC TẾ PHỤ NỮ 8/3 (3 ngày: 07/03 -> 09/03)
            [
                'slug' => 'quoc-te-phu-nu-8-3',
                'window' => $nextAnnualWindow(3, 7, 3),
                'campaign' => [
                    'name' => 'Quốc Tế Phụ Nữ 8/3 — Thưởng Nạp +5%',
                    'event_tag' => 'womens_day',
                    'badge_text' => '8/3 · NẠP +5%',
                    'accent_color' => '#DB2777',
                    'description' => 'Chúc mừng 8/3: Tặng thêm +5% số dư mọi giao dịch nạp ví + Săn voucher giảm thẳng 20.000đ.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 75,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                    'max_discount_amount' => null,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'QUATANG83',
                        'description' => 'Mừng 8/3: Giảm thẳng 20.000đ cho đơn VPS/Proxy từ 120.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 20000,
                        'max_discount_amount' => 20000,
                        'min_order_amount' => 120000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 1,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 8. NGÀY ĐÔI 4/4 — NGÀY HỘI PROXY (3 ngày: 03/04 -> 05/04)
            [
                'slug' => 'sieu-sale-ngay-doi-4-4-proxy',
                'window' => $nextAnnualWindow(4, 3, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 4/4 — Ngày Hội Proxy IPv4',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 4/4 · PROXY -10%',
                    'accent_color' => '#0284C7',
                    'description' => 'Ngày hội Proxy sạch Việt Nam 4/4: Giảm 10% toàn bộ Proxy Dân cư & Datacenter.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 75,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'max_discount_amount' => 100000,
                    'target_scope' => 'proxy',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 0,
                ],
                'coupons' => [
                    [
                        'code' => 'PROXY0404',
                        'description' => 'Ngày đôi 4/4: Giảm 12% Proxy IPv4 (tối đa 40.000đ, đơn từ 60.000đ)',
                        'discount_type' => 'percent',
                        'discount_value' => 12,
                        'max_discount_amount' => 40000,
                        'min_order_amount' => 60000,
                        'applies_to' => 'proxy',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 9. ĐẠI LỄ 30/4 – 01/05 (6 ngày: 28/04 -> 03/05)
            [
                'slug' => 'dai-le-30-4-va-1-5',
                'window' => $nextAnnualWindow(4, 28, 6),
                'campaign' => [
                    'name' => 'Đại Lễ 30/4 – 01/05 — Siêu Ưu Đãi Toàn Sàn',
                    'event_tag' => 'holiday_30_4',
                    'badge_text' => 'ĐẠI LỄ 30/4 -12% · NẠP +5%',
                    'accent_color' => '#DC2626',
                    'description' => 'Mừng Đại Lễ Giải phóng Miền Nam & Quốc tế Lao động: Giảm 12% toàn sàn (kể cả gia hạn) + Thưởng nạp +5%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 92,
                    'discount_type' => 'percent',
                    'discount_value' => 12,
                    'max_discount_amount' => 200000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'DAILE304',
                        'description' => 'Đại Lễ 30/4: Giảm 15% (tối đa 100.000đ) cho đơn từ 200.000đ',
                        'discount_type' => 'percent',
                        'discount_value' => 15,
                        'max_discount_amount' => 100000,
                        'min_order_amount' => 200000,
                        'applies_to' => 'all',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],

            // 10. NGÀY ĐÔI 6/6 — GIỮA NĂM NÂNG CẤP CẤU HÌNH (3 ngày: 05/06 -> 07/06)
            [
                'slug' => 'sieu-sale-ngay-doi-6-6',
                'window' => $nextAnnualWindow(6, 5, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Giữa Năm 6/6 — Nâng Cấp Cấu Hình',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 6/6 · UPGRADE -12%',
                    'accent_color' => '#7C3AED',
                    'description' => 'Giữa năm nâng cấp tài nguyên: Giảm 12% nâng cấp CPU/RAM/SSD + Săn mã giảm 10% mua mới.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 78,
                    'discount_type' => 'percent',
                    'discount_value' => 12,
                    'max_discount_amount' => 120000,
                    'target_scope' => 'upgrade',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 3,
                ],
                'coupons' => [
                    [
                        'code' => 'SALE0606',
                        'description' => 'Sale 6/6: Giảm 10% (tối đa 60.000đ) cho đơn từ 100.000đ',
                        'discount_type' => 'percent',
                        'discount_value' => 10,
                        'max_discount_amount' => 60000,
                        'min_order_amount' => 100000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 1,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 11. NGÀY ĐÔI 8/8 — PHÁT TÀI PHÁT LỘC (3 ngày: 07/08 -> 09/08)
            [
                'slug' => 'sieu-sale-ngay-doi-8-8',
                'window' => $nextAnnualWindow(8, 7, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 8/8 — Phát Lộc Toàn Sàn',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 8/8 -8% · NẠP +4%',
                    'accent_color' => '#D97706',
                    'description' => 'Ngày đẹp 8/8: Giảm 8% toàn bộ VPS & Proxy + Thưởng nạp +4% + Voucher giảm thẳng 30.000đ.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 78,
                    'discount_type' => 'percent',
                    'discount_value' => 8,
                    'max_discount_amount' => 150000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 4,
                ],
                'coupons' => [
                    [
                        'code' => 'LUGIA88',
                        'description' => 'Ngày đôi 8/8: Giảm thẳng 30.000đ cho đơn VPS/Proxy từ 200.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 30000,
                        'max_discount_amount' => 30000,
                        'min_order_amount' => 200000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 12. QUỐC KHÁNH 02/09 (6 ngày: 30/08 -> 04/09)
            [
                'slug' => 'quoc-khanh-viet-nam-2-9',
                'window' => $nextAnnualWindow(8, 30, 6),
                'campaign' => [
                    'name' => 'Mừng Quốc Khánh 02/09 — Tự Hào Hạ Tầng Việt',
                    'event_tag' => 'national_day',
                    'badge_text' => 'QUỐC KHÁNH -12% · NẠP +6%',
                    'accent_color' => '#B91C1C',
                    'description' => 'Kỷ niệm Quốc Khánh 02/09: Giảm 12% toàn bộ VPS & Proxy (áp dụng cả gia hạn) + Thưởng nạp +6%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 92,
                    'discount_type' => 'percent',
                    'discount_value' => 12,
                    'max_discount_amount' => 200000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => true,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 6,
                ],
                'coupons' => [
                    [
                        'code' => 'QUOCKHANH29',
                        'description' => 'Quốc Khánh 2/9: Giảm 15% (tối đa 100.000đ) đơn từ 200.000đ (gồm gia hạn)',
                        'discount_type' => 'percent',
                        'discount_value' => 15,
                        'max_discount_amount' => 100000,
                        'min_order_amount' => 200000,
                        'applies_to' => 'all',
                        'apply_on_renew' => true,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],

            // 13. NGÀY ĐÔI 10/10 (3 ngày: 09/10 -> 11/10)
            [
                'slug' => 'sieu-sale-ngay-doi-10-10',
                'window' => $nextAnnualWindow(10, 9, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 10/10 — Tròn Đầy Hiệu Năng',
                    'event_tag' => 'double_day',
                    'badge_text' => 'SALE 10/10 · VPS -10%',
                    'accent_color' => '#1D6AE5',
                    'description' => 'Ngày đôi 10/10: Giảm ngay 10% Cloud VPS (tối đa 120.000đ) + Săn mã giảm 12% cho đơn từ 120.000đ.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 80,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'max_discount_amount' => 120000,
                    'target_scope' => 'vps',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => false,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 3,
                ],
                'coupons' => [
                    [
                        'code' => 'SALE1010',
                        'description' => 'Ngày đôi 10/10: Giảm 12% (tối đa 70.000đ) đơn từ 120.000đ',
                        'discount_type' => 'percent',
                        'discount_value' => 12,
                        'max_discount_amount' => 70000,
                        'min_order_amount' => 120000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 120,
                        'max_uses_per_user' => 1,
                        'is_huntable' => true,
                        'claim_limit' => 120,
                        'is_active' => true,
                    ],
                ],
            ],

            // 14. SIÊU SALE ĐỘC THÂN 11/11 (3 ngày: 10/11 -> 12/11 — Ngày Đôi lớn nhất năm!)
            [
                'slug' => 'sieu-sale-ngay-doi-11-11',
                'window' => $nextAnnualWindow(11, 10, 3),
                'campaign' => [
                    'name' => 'Siêu Sale Ngày Đôi 11/11 — Đỉnh Cao Mua Sắm',
                    'event_tag' => 'double_day',
                    'badge_text' => 'MEGA SALE 11/11 -15%',
                    'accent_color' => '#E11D48',
                    'description' => 'Ngày đôi lớn nhất năm 11/11: Giảm 15% toàn bộ VPS, Proxy & Nâng cấp (tối đa 250.000đ) + Thưởng nạp +5%.',
                    'is_active' => true,
                    'send_launch_email' => false,
                    'priority' => 94,
                    'discount_type' => 'percent',
                    'discount_value' => 15,
                    'max_discount_amount' => 250000,
                    'target_scope' => 'all',
                    'min_months' => 1,
                    'apply_on_renew' => false,
                    'apply_on_upgrade' => true,
                    'stack_with_user_discount' => false,
                    'protect_cost_floor' => true,
                    'topup_bonus_percent' => 5,
                ],
                'coupons' => [
                    [
                        'code' => 'MEGA1111',
                        'description' => 'Siêu Sale 11/11: Giảm thẳng 50.000đ cho đơn VPS/Proxy từ 250.000đ',
                        'discount_type' => 'fixed',
                        'discount_value' => 50000,
                        'max_discount_amount' => 50000,
                        'min_order_amount' => 250000,
                        'applies_to' => 'all',
                        'apply_on_renew' => false,
                        'stack_with_campaign' => false,
                        'protect_cost_floor' => true,
                        'max_uses' => 150,
                        'max_uses_per_user' => 2,
                        'is_huntable' => true,
                        'claim_limit' => 150,
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        foreach ($events as $item) {
            $campData = $item['campaign'];
            $campData['is_active'] = false; // Mặc định tắt — Admin chủ động bật khi cần chạy
            if (isset($item['window'])) {
                [$s, $e] = $item['window'];
                $campData['starts_at'] = $s;
                $campData['ends_at'] = $e;
            }

            $campaign = PromotionCampaign::updateOrCreate(
                ['slug' => $item['slug']],
                $campData
            );

            foreach ($item['coupons'] ?? [] as $cpData) {
                $cpData['campaign_id'] = $campaign->id;
                $cpData['starts_at'] = $campaign->starts_at;
                $cpData['ends_at'] = $campaign->ends_at;
                $cpData['is_active'] = false; // Mặc định tắt

                Coupon::updateOrCreate(
                    ['code' => $cpData['code']],
                    $cpData
                );
            }

            foreach ($item['banners'] ?? [] as $bData) {
                $bData['campaign_id'] = $campaign->id;
                $bData['starts_at'] = $campaign->starts_at;
                $bData['ends_at'] = $campaign->ends_at;
                $bData['is_active'] = false; // Mặc định tắt

                PromotionBanner::updateOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'placement' => $bData['placement'],
                    ],
                    $bData
                );
            }
        }

        // 2 Suất Flash Sale Khai Trương (mặc định tắt — Admin chủ động bật khi chạy)
        $flashDeals = [
            [
                'plan_slug' => 'lg-4',
                'name' => 'Flash Sale Khai Trương · Cloud VPS 2C/4GB',
                'badge_text' => 'FLASH SALE -15%',
                'service_type' => 'vps',
                'discount_type' => 'percent',
                'discount_value' => 15, // 120k -> 102k (cost 81k -> lãi 21k/suất)
                'total_slots' => 20,
                'max_per_user' => 1,
                'max_months' => 1,
                'protect_cost_floor' => true,
                'starts_at' => $launchStart,
                'ends_at' => $launchEnd,
                'is_active' => false,
            ],
            [
                'plan_slug' => 'proxy-14',
                'name' => 'Flash Sale Khai Trương · Proxy Private DC',
                'badge_text' => 'FLASH SALE -15%',
                'service_type' => 'proxy',
                'discount_type' => 'percent',
                'discount_value' => 15, // 20k -> 17k (cost 14k -> lãi 3k/suất)
                'total_slots' => 50,
                'max_per_user' => 5,
                'max_months' => 1,
                'protect_cost_floor' => true,
                'starts_at' => $launchStart,
                'ends_at' => $launchEnd,
                'is_active' => false,
            ],
        ];

        foreach ($flashDeals as $fd) {
            if (PricingCatalog::where('slug', $fd['plan_slug'])->exists()) {
                FlashSale::updateOrCreate(
                    ['name' => $fd['name']],
                    $fd
                );
            }
        }

        app(PromotionEngine::class)->flushCache();
        app(FlashSaleService::class)->flushCache();
        app(CloudSunnyPricingService::class)->flushCache();
    }
}

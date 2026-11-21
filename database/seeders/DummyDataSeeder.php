<?php

namespace Database\Seeders;

use App\Constants\UserRole;
use App\Models\AffiliateLog;
use App\Models\CloudSunnyAccount;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\KnowledgeBase;
use App\Models\PricingCatalog;
use App\Models\PromotionBanner;
use App\Models\PromotionCampaign;
use App\Models\ProxyInstance;
use App\Models\Setting;
use App\Models\TopupRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\CloudSunnyPricingService;
use App\Services\PromotionEngine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Đảm bảo Pricing Catalog chuẩn LugiaCloud (SSD, Intel Xeon Gold/Platinum, FPT HCM)
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        // 2. Tạo / Cập nhật Users
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@lugiacloud.vn'],
            [
                'name' => 'Quản trị LugiaCloud',
                'phone' => '0988888888',
                'password' => Hash::make('Admin@123456'),
                'balance' => 15000000,
                'is_admin' => UserRole::ADMIN,
                'ref_code' => 'LUGIAADM',
                'discount_percent' => 0,
                'proxy_discount_percent' => 0,
                'email_verified_at' => now(),
            ]
        );

        // Tài khoản test khớp với ảnh chụp UI của User (quanpc2001@gmail.com)
        $quanUser = User::updateOrCreate(
            ['email' => 'quanpc2001@gmail.com'],
            [
                'name' => 'Admin Quân PC',
                'phone' => '0901234567',
                'password' => Hash::make('Admin@123456'),
                'balance' => 22672611,
                'is_admin' => UserRole::ADMIN,
                'ref_code' => 'QUANPC',
                'discount_percent' => 0,
                'proxy_discount_percent' => 0,
                'email_verified_at' => now(),
            ]
        );

        $resellerUser = User::updateOrCreate(
            ['email' => 'reseller@lugiacloud.vn'],
            [
                'name' => 'Đối Tác Đại Lý FastCloud',
                'phone' => '0912345678',
                'password' => Hash::make('Admin@123456'),
                'balance' => 8500000,
                'is_admin' => UserRole::RESELLER,
                'ref_code' => 'RESELLER01',
                'api_key' => 'lg_live_4b8f9e2d3c1a5b6e7f8a9b0c1d2e3f4a',
                'webhook_url' => 'https://reseller-partner.example.com/api/lugia-webhook',
                'discount_percent' => 10,
                'proxy_discount_percent' => 10,
                'email_verified_at' => now(),
            ]
        );

        $namDev = User::updateOrCreate(
            ['email' => 'hoangnam.dev@gmail.com'],
            [
                'name' => 'Hoàng Nam Developer',
                'phone' => '0933112233',
                'password' => Hash::make('Admin@123456'),
                'balance' => 850000,
                'is_admin' => UserRole::CUSTOMER,
                'ref_code' => 'NAMDEV99',
                'referred_by' => $quanUser->id,
                'email_verified_at' => now(),
            ]
        );

        $tuanMmo = User::updateOrCreate(
            ['email' => 'minhtuan.mmo@gmail.com'],
            [
                'name' => 'Minh Tuấn MMO',
                'phone' => '0977223344',
                'password' => Hash::make('Admin@123456'),
                'balance' => 420000,
                'is_admin' => UserRole::CUSTOMER,
                'ref_code' => 'TUANMMO',
                'referred_by' => $resellerUser->id,
                'email_verified_at' => now(),
            ]
        );

        $huongAgency = User::updateOrCreate(
            ['email' => 'thanhhuong.agency@gmail.com'],
            [
                'name' => 'Thanh Hương Media Agency',
                'phone' => '0944556677',
                'password' => Hash::make('Admin@123456'),
                'balance' => 1680000,
                'is_admin' => UserRole::CUSTOMER,
                'ref_code' => 'HUONGAGY',
                'referred_by' => $quanUser->id,
                'email_verified_at' => now(),
            ]
        );

        $ducAnhVpn = User::updateOrCreate(
            ['email' => 'ducanh.vpn@gmail.com'],
            [
                'name' => 'Đức Anh Network',
                'phone' => '0966778899',
                'password' => Hash::make('Admin@123456'),
                'balance' => 75000,
                'is_admin' => UserRole::CUSTOMER,
                'ref_code' => 'DUCANHVPN',
                'referred_by' => $adminUser->id,
                'email_verified_at' => now(),
            ]
        );

        // 3. Upstream Accounts (cloud_sunny_accounts)
        $accNode1 = CloudSunnyAccount::firstOrCreate(
            ['api_username' => 'node-hcm01@lugiacloud.vn'],
            [
                'label' => 'CloudSunny Node HCM-01 (FPT Tân Thuận)',
                'api_app' => 'CLOUDSUNNY_APP_01',
                'api_secret' => 'sec_node1_secret_998877665544332211',
                'credit_vnd' => 28500000,
                'total_vnd' => 50000000,
                'is_active' => true,
                'is_full' => false,
                'priority' => 10,
                'last_synced_at' => now(),
            ]
        );

        $accNode2 = CloudSunnyAccount::firstOrCreate(
            ['api_username' => 'node-hcm02@lugiacloud.vn'],
            [
                'label' => 'CloudSunny Node HCM-02 (FPT Q9 Dự Phòng)',
                'api_app' => 'CLOUDSUNNY_APP_02',
                'api_secret' => 'sec_node2_secret_112233445566778899',
                'credit_vnd' => 14200000,
                'total_vnd' => 30000000,
                'is_active' => true,
                'is_full' => false,
                'priority' => 20,
                'last_synced_at' => now(),
            ]
        );

        $accNode3 = CloudSunnyAccount::firstOrCreate(
            ['api_username' => 'proxy-pool@lugiacloud.vn'],
            [
                'label' => 'CloudSunny Node HCM-03 (Kho Proxy Datacenter)',
                'api_app' => 'CLOUDSUNNY_APP_03',
                'api_secret' => 'sec_proxy_pool_secret_55667788990011',
                'credit_vnd' => 8900000,
                'total_vnd' => 20000000,
                'is_active' => true,
                'is_full' => false,
                'priority' => 30,
                'last_synced_at' => now(),
            ]
        );

        // 4. Khuyến mãi & Chiến dịch
        $campaign = PromotionCampaign::firstOrCreate(
            ['slug' => 'le-hoi-cloud-cuoi-nam'],
            [
                'name' => 'Lễ Hội Cloud Cuối Năm — Tri Ân Khách Hàng',
                'event_tag' => 'seasonal',
                'badge_text' => 'ƯU ĐÃI SỰ KIỆN -15%',
                'accent_color' => '#1D6AE5',
                'description' => 'Giảm trực tiếp 15% toàn bộ gói Cloud VPS SSD & Proxy IPv4 Việt Nam, tặng thêm 10% giá trị nạp ví tự động.',
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addDays(20),
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
            ]
        );

        $couponLugia = Coupon::firstOrCreate(
            ['code' => 'LUGIA2026'],
            [
                'campaign_id' => $campaign->id,
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
            ]
        );

        $couponDev = Coupon::firstOrCreate(
            ['code' => 'DEVPRO15'],
            [
                'campaign_id' => $campaign->id,
                'description' => 'Giảm 15% (tối đa 40.000đ) dành riêng cho anh em Dev',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_discount_amount' => 40000,
                'min_order_amount' => 65000,
                'applies_to' => 'vps',
                'apply_on_renew' => true,
                'protect_cost_floor' => true,
                'max_uses' => 200,
                'max_uses_per_user' => 2,
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(25),
                'is_active' => true,
            ]
        );

        $couponWelcome = Coupon::firstOrCreate(
            ['code' => 'WELCOME20K'],
            [
                'campaign_id' => $campaign->id,
                'description' => 'Tặng 20.000đ cho đơn hàng đầu tiên từ 100.000đ',
                'discount_type' => 'fixed',
                'discount_value' => 20000,
                'max_discount_amount' => 20000,
                'min_order_amount' => 100000,
                'applies_to' => 'all',
                'apply_on_renew' => false,
                'protect_cost_floor' => true,
                'max_uses' => 1000,
                'max_uses_per_user' => 1,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(60),
                'is_active' => true,
            ]
        );

        // 5. VPS Instances
        // Xóa sạch VPS cũ nếu cần làm mới hoặc giữ lại và tạo đủ tập
        if (VpsInstance::count() === 0) {
            $vpsSpecs = [
                // Cho user quanpc2001@gmail.com
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'TNguyen-9c8b',
                    'plan_id' => 'lg-32',
                    'cpu' => 24,
                    'ram' => 32,
                    'disk' => 300,
                    'cost_monthly_usd' => 24.5000,
                    'paid_amount' => 580000,
                    'provider_cost' => 410000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '180.93.126.192',
                    'login_username' => 'Administrator',
                    'root_password' => 'P@ssw0rd_Admin2026!',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(25),
                    'provider_vps_id' => 88124,
                    'provider_product_id' => 1,
                    'provider_os_id' => 10, // Windows Server 2022
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'Web-Production-01',
                    'plan_id' => 'lg-4',
                    'cpu' => 4,
                    'ram' => 8,
                    'disk' => 80,
                    'cost_monthly_usd' => 5.2000,
                    'paid_amount' => 120000,
                    'provider_cost' => 86000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '103.22.33.45',
                    'login_username' => 'root',
                    'root_password' => 'Lugia#Sec026_Ub',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(18),
                    'provider_vps_id' => 88125,
                    'provider_product_id' => 1,
                    'provider_os_id' => 1, // Ubuntu 22.04
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode2->id,
                    'label' => 'MMO-Crawler-Win10',
                    'plan_id' => 'lg-8',
                    'cpu' => 8,
                    'ram' => 16,
                    'disk' => 120,
                    'cost_monthly_usd' => 9.5000,
                    'paid_amount' => 627000,
                    'provider_cost' => 450000,
                    'status' => 'Đã tắt',
                    'public_ip' => '180.93.127.88',
                    'login_username' => 'Administrator',
                    'root_password' => 'Win10_MMO_888!',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'quarterly',
                    'expires_at' => now()->addDays(42),
                    'provider_vps_id' => 88126,
                    'provider_product_id' => 1,
                    'provider_os_id' => 12, // Windows 10
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'Database-Master',
                    'plan_id' => 'lg-16',
                    'cpu' => 16,
                    'ram' => 32,
                    'disk' => 200,
                    'cost_monthly_usd' => 16.8000,
                    'paid_amount' => 390000,
                    'provider_cost' => 281000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '103.22.34.110',
                    'login_username' => 'root',
                    'root_password' => 'Deb_Master@2026',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(3), // Cảnh báo sắp hết hạn
                    'provider_vps_id' => 88127,
                    'provider_product_id' => 1,
                    'provider_os_id' => 3, // Debian 12
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode2->id,
                    'label' => 'Api-Staging-Worker',
                    'plan_id' => 'lg-2',
                    'cpu' => 2,
                    'ram' => 4,
                    'disk' => 40,
                    'cost_monthly_usd' => 2.8000,
                    'paid_amount' => 65000,
                    'provider_cost' => 47000,
                    'status' => 'Đang khởi tạo...',
                    'public_ip' => null,
                    'login_username' => 'root',
                    'root_password' => 'Alma_Init#998',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(30),
                    'provider_vps_id' => 88128,
                    'provider_product_id' => 1,
                    'provider_os_id' => 5, // AlmaLinux 9
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'Legacy-VPN-Server',
                    'plan_id' => 'lg-1',
                    'cpu' => 1,
                    'ram' => 2,
                    'disk' => 30,
                    'cost_monthly_usd' => 2.1000,
                    'paid_amount' => 50000,
                    'provider_cost' => 36000,
                    'status' => 'Hết hạn',
                    'public_ip' => '103.22.35.12',
                    'login_username' => 'root',
                    'root_password' => 'Vpn_Exp#001',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->subDays(2), // Hết hạn 2 ngày trước
                    'provider_vps_id' => 88129,
                    'provider_product_id' => 1,
                    'provider_os_id' => 2, // Ubuntu 20.04
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode2->id,
                    'label' => 'Old-Test-Node-Terminated',
                    'plan_id' => 'lg-2',
                    'cpu' => 2,
                    'ram' => 4,
                    'disk' => 40,
                    'cost_monthly_usd' => 2.8000,
                    'paid_amount' => 65000,
                    'provider_cost' => 47000,
                    'status' => 'Đã xóa',
                    'public_ip' => '180.93.126.99',
                    'login_username' => 'Administrator',
                    'root_password' => 'Old_Terminated#00',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->subDays(7), // Quá hạn 7 ngày -> Đã xóa
                    'provider_vps_id' => 88130,
                    'provider_product_id' => 1,
                    'provider_os_id' => 11, // Windows Server 2019
                ],

                // Cho user admin@lugiacloud.vn
                [
                    'user_id' => $adminUser->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'Admin-Core-Hub',
                    'plan_id' => 'lg-8',
                    'cpu' => 8,
                    'ram' => 16,
                    'disk' => 150,
                    'cost_monthly_usd' => 9.5000,
                    'paid_amount' => 220000,
                    'provider_cost' => 158000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '180.93.126.10',
                    'login_username' => 'root',
                    'root_password' => 'Admin_Hub_Sec#26',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(28),
                    'provider_vps_id' => 88131,
                    'provider_product_id' => 1,
                    'provider_os_id' => 1,
                ],
                [
                    'user_id' => $adminUser->id,
                    'cloudsunny_account_id' => $accNode2->id,
                    'label' => 'Admin-Test-Sandbox',
                    'plan_id' => 'lg-2',
                    'cpu' => 2,
                    'ram' => 4,
                    'disk' => 40,
                    'cost_monthly_usd' => 2.8000,
                    'paid_amount' => 65000,
                    'provider_cost' => 47000,
                    'status' => 'Đang dừng',
                    'public_ip' => '180.93.126.11',
                    'login_username' => 'Administrator',
                    'root_password' => 'Adm_Win_Sandbox#1',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(14),
                    'provider_vps_id' => 88132,
                    'provider_product_id' => 1,
                    'provider_os_id' => 10,
                ],

                // Cho khách hàng khác để Admin table có data phân trang & search
                [
                    'user_id' => $namDev->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'NamDev-Laravel-Prod',
                    'plan_id' => 'lg-4',
                    'cpu' => 4,
                    'ram' => 8,
                    'disk' => 80,
                    'cost_monthly_usd' => 5.2000,
                    'paid_amount' => 120000,
                    'provider_cost' => 86000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '103.22.33.60',
                    'login_username' => 'root',
                    'root_password' => 'NamDev_Prod#889',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(20),
                    'provider_vps_id' => 88133,
                    'provider_product_id' => 1,
                    'provider_os_id' => 1,
                ],
                [
                    'user_id' => $tuanMmo->id,
                    'cloudsunny_account_id' => $accNode2->id,
                    'label' => 'MMO-AutoReg-Node',
                    'plan_id' => 'lg-8',
                    'cpu' => 8,
                    'ram' => 16,
                    'disk' => 120,
                    'cost_monthly_usd' => 9.5000,
                    'paid_amount' => 220000,
                    'provider_cost' => 158000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '180.93.127.15',
                    'login_username' => 'Administrator',
                    'root_password' => 'TuanMMO_Win10!22',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(11),
                    'provider_vps_id' => 88134,
                    'provider_product_id' => 1,
                    'provider_os_id' => 12,
                ],
                [
                    'user_id' => $huongAgency->id,
                    'cloudsunny_account_id' => $accNode1->id,
                    'label' => 'Agency-Video-Render',
                    'plan_id' => 'lg-32',
                    'cpu' => 32,
                    'ram' => 64,
                    'disk' => 500,
                    'cost_monthly_usd' => 32.5000,
                    'paid_amount' => 750000,
                    'provider_cost' => 540000,
                    'status' => 'Sẵn sàng',
                    'public_ip' => '180.93.128.88',
                    'login_username' => 'Administrator',
                    'root_password' => 'HuongAgency_Render@99',
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                    'expires_at' => now()->addDays(22),
                    'provider_vps_id' => 88135,
                    'provider_product_id' => 1,
                    'provider_os_id' => 10,
                ],
            ];

            foreach ($vpsSpecs as $spec) {
                VpsInstance::create($spec);
            }
        }

        // 6. Proxy Instances
        if (ProxyInstance::count() === 0) {
            $proxySpecs = [
                // Cho quanpc2001@gmail.com
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 8, // Proxy Datacenter Riêng 1:1
                    'type_proxy' => 'HTTP',
                    'ip' => '180.93.200.15',
                    'port' => '8080',
                    'username' => 'lugia_usr01',
                    'password' => 'px_sec_pass_888',
                    'sock5_port' => '1080',
                    'sock5_username' => 'lugia_s5_01',
                    'sock5_password' => 's5_sec_pass_888',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 1.5000,
                    'paid_amount' => 35000,
                    'expires_at' => now()->addDays(25),
                    'is_auto_renew' => true,
                    'provider_proxy_id' => 77101,
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 10, // Proxy Dân Cư VN Riêng 1:1
                    'type_proxy' => 'SOCKS5',
                    'ip' => '103.145.4.82',
                    'port' => '8443',
                    'username' => 'lugia_usr02',
                    'password' => 'px_res_pass_999',
                    'sock5_port' => '1085',
                    'sock5_username' => 'lugia_s5_02',
                    'sock5_password' => 's5_res_pass_999',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 2.8000,
                    'paid_amount' => 65000,
                    'expires_at' => now()->addDays(16),
                    'is_auto_renew' => false,
                    'provider_proxy_id' => 77102,
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 7, // Proxy Datacenter Shared
                    'type_proxy' => 'HTTP',
                    'ip' => '180.93.201.33',
                    'port' => '3128',
                    'username' => 'lugia_usr03',
                    'password' => 'px_sh_pass_333',
                    'sock5_port' => '1088',
                    'sock5_username' => 'lugia_s5_03',
                    'sock5_password' => 's5_sh_pass_333',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 0.6500,
                    'paid_amount' => 15000,
                    'expires_at' => now()->addDays(2), // Sắp hết hạn
                    'is_auto_renew' => true,
                    'provider_proxy_id' => 77103,
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 9, // Proxy Dân Cư Shared
                    'type_proxy' => 'HTTP',
                    'ip' => '103.145.5.91',
                    'port' => '8080',
                    'username' => 'lugia_usr04',
                    'password' => 'px_sh_pass_444',
                    'sock5_port' => '1090',
                    'sock5_username' => 'lugia_s5_04',
                    'sock5_password' => 's5_sh_pass_444',
                    'status' => 'Hết hạn',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 0.9000,
                    'paid_amount' => 20000,
                    'expires_at' => now()->subDay(),
                    'is_auto_renew' => false,
                    'provider_proxy_id' => 77104,
                ],
                [
                    'user_id' => $quanUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 8,
                    'type_proxy' => 'HTTP',
                    'ip' => '180.93.202.10',
                    'port' => '8080',
                    'username' => 'lugia_usr05',
                    'password' => 'px_del_pass_555',
                    'sock5_port' => '1092',
                    'sock5_username' => 'lugia_s5_05',
                    'sock5_password' => 's5_del_pass_555',
                    'status' => 'Đã xoá',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 1.5000,
                    'paid_amount' => 35000,
                    'expires_at' => now()->subDays(10),
                    'is_auto_renew' => false,
                    'provider_proxy_id' => 77105,
                ],

                // Cho khách hàng khác & Reseller
                [
                    'user_id' => $adminUser->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 8,
                    'type_proxy' => 'HTTP',
                    'ip' => '180.93.200.88',
                    'port' => '8080',
                    'username' => 'admin_px01',
                    'password' => 'adm_pass_888',
                    'sock5_port' => '1095',
                    'sock5_username' => 'adm_s5_01',
                    'sock5_password' => 'adm_s5_888',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 1.5000,
                    'paid_amount' => 35000,
                    'expires_at' => now()->addDays(20),
                    'is_auto_renew' => true,
                    'provider_proxy_id' => 77106,
                ],
                [
                    'user_id' => $tuanMmo->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 7,
                    'type_proxy' => 'HTTP',
                    'ip' => '180.93.201.77',
                    'port' => '3128',
                    'username' => 'tuan_px_01',
                    'password' => 'tuan_pass_777',
                    'sock5_port' => '1098',
                    'sock5_username' => 'tuan_s5_01',
                    'sock5_password' => 'tuan_s5_777',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 0.6500,
                    'paid_amount' => 15000,
                    'expires_at' => now()->addDays(12),
                    'is_auto_renew' => true,
                    'provider_proxy_id' => 77107,
                ],
                [
                    'user_id' => $huongAgency->id,
                    'cloudsunny_account_id' => $accNode3->id,
                    'product_id' => 10,
                    'type_proxy' => 'SOCKS5',
                    'ip' => '103.145.4.99',
                    'port' => '8443',
                    'username' => 'huong_px_01',
                    'password' => 'huong_pass_999',
                    'sock5_port' => '1100',
                    'sock5_username' => 'huong_s5_01',
                    'sock5_password' => 'huong_s5_999',
                    'status' => 'Hoạt động',
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => 2.8000,
                    'paid_amount' => 65000,
                    'expires_at' => now()->addDays(28),
                    'is_auto_renew' => true,
                    'provider_proxy_id' => 77108,
                ],
            ];

            foreach ($proxySpecs as $spec) {
                ProxyInstance::create($spec);
            }
        }

        // 7. Topup Requests
        if (TopupRequest::count() <= 1) {
            $topups = [
                [
                    'user_id' => $quanUser->id,
                    'code' => 'LUG-TOP-8801',
                    'amount' => 5000000,
                    'approved_amount' => 5000000,
                    'bonus_amount' => 500000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'payos',
                    'provider_order_code' => 8801001,
                    'transaction_ref' => 'PAYOS_TX_8801',
                    'status' => 'paid',
                    'note' => 'Nạp ví tự động qua VietQR PayOS (+10% thưởng)',
                    'paid_at' => now()->subDays(6),
                    'processed_at' => now()->subDays(6),
                ],
                [
                    'user_id' => $quanUser->id,
                    'code' => 'LUG-TOP-8802',
                    'amount' => 2000000,
                    'approved_amount' => 2000000,
                    'bonus_amount' => 200000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'payos',
                    'provider_order_code' => 8801002,
                    'transaction_ref' => 'PAYOS_TX_8802',
                    'status' => 'paid',
                    'note' => 'Nạp số dư duy trì dịch vụ Cloud (+10% thưởng)',
                    'paid_at' => now()->subDays(2),
                    'processed_at' => now()->subDays(2),
                ],
                [
                    'user_id' => $quanUser->id,
                    'code' => 'LUG-TOP-8803',
                    'amount' => 500000,
                    'approved_amount' => null,
                    'bonus_amount' => 50000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'vietqr',
                    'provider_order_code' => 8801003,
                    'transaction_ref' => null,
                    'status' => 'pending',
                    'note' => 'Chờ chuyển khoản VietQR Techcombank',
                    'paid_at' => null,
                    'processed_at' => null,
                ],
                [
                    'user_id' => $resellerUser->id,
                    'code' => 'LUG-TOP-8804',
                    'amount' => 10000000,
                    'approved_amount' => 10000000,
                    'bonus_amount' => 1000000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'payos',
                    'provider_order_code' => 8801004,
                    'transaction_ref' => 'PAYOS_TX_8804',
                    'status' => 'approved',
                    'approved_by' => $adminUser->id,
                    'note' => 'Đại lý nạp hạn mức API tháng 11',
                    'paid_at' => now()->subDays(12),
                    'processed_at' => now()->subDays(12),
                ],
                [
                    'user_id' => $namDev->id,
                    'code' => 'LUG-TOP-8805',
                    'amount' => 1000000,
                    'approved_amount' => 1000000,
                    'bonus_amount' => 100000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'vietqr',
                    'provider_order_code' => 8801005,
                    'transaction_ref' => 'FT261115888',
                    'status' => 'approved',
                    'approved_by' => $adminUser->id,
                    'note' => 'Nạp ví qua VietQR Techcombank',
                    'paid_at' => now()->subDays(4),
                    'processed_at' => now()->subDays(4),
                ],
                [
                    'user_id' => $tuanMmo->id,
                    'code' => 'LUG-TOP-8806',
                    'amount' => 500000,
                    'approved_amount' => 500000,
                    'bonus_amount' => 50000,
                    'campaign_id' => $campaign->id,
                    'provider' => 'payos',
                    'provider_order_code' => 8801006,
                    'transaction_ref' => 'PAYOS_TX_8806',
                    'status' => 'approved',
                    'approved_by' => $adminUser->id,
                    'note' => 'Nạp tiền mua Proxy tự động',
                    'paid_at' => now()->subDay(),
                    'processed_at' => now()->subDay(),
                ],
                [
                    'user_id' => $ducAnhVpn->id,
                    'code' => 'LUG-TOP-8807',
                    'amount' => 100000,
                    'approved_amount' => null,
                    'bonus_amount' => 0,
                    'campaign_id' => null,
                    'provider' => 'vietqr',
                    'provider_order_code' => 8801007,
                    'status' => 'rejected',
                    'admin_note' => 'Sai nội dung chuyển khoản, đã liên hệ hoàn tiền về TK Techcombank người gửi.',
                    'paid_at' => null,
                    'processed_at' => now()->subDays(3),
                ],
            ];

            foreach ($topups as $tp) {
                TopupRequest::create($tp);
            }
        }

        // 8. Transactions (Sổ cái giao dịch & Báo cáo doanh thu)
        if (Transaction::count() === 0) {
            $firstVps = VpsInstance::where('user_id', $quanUser->id)->first();
            $secondVps = VpsInstance::where('user_id', $quanUser->id)->skip(1)->first();
            $firstProxy = ProxyInstance::where('user_id', $quanUser->id)->first();

            $txns = [
                // Topup nạp tiền
                [
                    'code' => 'LUG-TXN-TOP01',
                    'idempotency_key' => 'topup_credit_8801',
                    'user_id' => $quanUser->id,
                    'type' => 'topup',
                    'status' => 'completed',
                    'amount' => 5000000,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 15552611,
                    'balance_after' => 20552611,
                    'description' => 'Nạp tiền tự động qua VietQR PayOS (Mã: LUG-TOP-8801)',
                    'created_at' => now()->subDays(6),
                ],
                // Bonus nạp tiền sự kiện
                [
                    'code' => 'LUG-BNS-TOP01',
                    'idempotency_key' => 'topup_bonus_8801',
                    'user_id' => $quanUser->id,
                    'campaign_id' => $campaign->id,
                    'type' => 'topup_bonus',
                    'status' => 'completed',
                    'amount' => 500000,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 20552611,
                    'balance_after' => 21052611,
                    'description' => 'Thưởng sự kiện +10% giá trị nạp ví (Lễ Hội Cloud Cuối Năm)',
                    'created_at' => now()->subDays(6),
                ],
                // Đăng ký VPS TNguyen-9c8b
                [
                    'code' => 'LUG-VPS-9C8B01',
                    'idempotency_key' => 'buy_vps_9c8b',
                    'user_id' => $quanUser->id,
                    'vps_instance_id' => $firstVps?->id,
                    'campaign_id' => $campaign->id,
                    'coupon_code' => 'LUGIA2026',
                    'type' => 'buy_vps',
                    'status' => 'completed',
                    'amount' => 580000,
                    'provider_cost' => 410000,
                    'discount_amount' => 50000,
                    'balance_before' => 21052611,
                    'balance_after' => 20472611,
                    'description' => 'Đăng ký Cloud VPS TNguyen-9c8b (24 Core · 32 GB RAM · 300 GB SSD)',
                    'created_at' => now()->subDays(5),
                ],
                // Đăng ký VPS Web-Production-01
                [
                    'code' => 'LUG-VPS-WEB01',
                    'idempotency_key' => 'buy_vps_web01',
                    'user_id' => $quanUser->id,
                    'vps_instance_id' => $secondVps?->id,
                    'type' => 'buy_vps',
                    'status' => 'completed',
                    'amount' => 120000,
                    'provider_cost' => 86000,
                    'discount_amount' => 0,
                    'balance_before' => 20472611,
                    'balance_after' => 20352611,
                    'description' => 'Đăng ký Cloud LG-4 Web-Production-01 (4 Core · 8 GB RAM · 80 GB SSD)',
                    'created_at' => now()->subDays(4),
                ],
                // Mua Proxy Riêng 1:1
                [
                    'code' => 'LUG-PRX-PX01',
                    'idempotency_key' => 'buy_prx_01',
                    'user_id' => $quanUser->id,
                    'proxy_instance_id' => $firstProxy?->id,
                    'type' => 'buy_proxy',
                    'status' => 'completed',
                    'amount' => 35000,
                    'provider_cost' => 27000,
                    'discount_amount' => 0,
                    'balance_before' => 20352611,
                    'balance_after' => 20317611,
                    'description' => 'Đăng ký Proxy Datacenter Riêng 1:1 (IP: 180.93.200.15)',
                    'created_at' => now()->subDays(3),
                ],
                // Nạp tiền đợt 2
                [
                    'code' => 'LUG-TXN-TOP02',
                    'idempotency_key' => 'topup_credit_8802',
                    'user_id' => $quanUser->id,
                    'type' => 'topup',
                    'status' => 'completed',
                    'amount' => 2000000,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 20317611,
                    'balance_after' => 22317611,
                    'description' => 'Nạp tiền qua VietQR PayOS (Mã: LUG-TOP-8802)',
                    'created_at' => now()->subDays(2),
                ],
                [
                    'code' => 'LUG-BNS-TOP02',
                    'idempotency_key' => 'topup_bonus_8802',
                    'user_id' => $quanUser->id,
                    'campaign_id' => $campaign->id,
                    'type' => 'topup_bonus',
                    'status' => 'completed',
                    'amount' => 200000,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 22317611,
                    'balance_after' => 22517611,
                    'description' => 'Thưởng sự kiện +10% giá trị nạp ví (Lễ Hội Cloud Cuối Năm)',
                    'created_at' => now()->subDays(2),
                ],
                // Hoa hồng Affiliate nhận được từ F1
                [
                    'code' => 'LUG-AFF-COMM01',
                    'idempotency_key' => 'aff_comm_f1_01',
                    'user_id' => $quanUser->id,
                    'type' => 'affiliate_bonus',
                    'status' => 'completed',
                    'amount' => 37500,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 22517611,
                    'balance_after' => 22555111,
                    'description' => 'Hoa hồng giới thiệu 5% từ đơn hàng của F1 (Thanh Hương Media Agency)',
                    'created_at' => now()->subDays(1),
                ],
                [
                    'code' => 'LUG-AFF-COMM02',
                    'idempotency_key' => 'aff_comm_f1_02',
                    'user_id' => $quanUser->id,
                    'type' => 'affiliate_bonus',
                    'status' => 'completed',
                    'amount' => 117500,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 22555111,
                    'balance_after' => 22672611, // Khớp chuẩn 22.672.611đ trên UI
                    'description' => 'Hoa hồng giới thiệu 5% từ các giao dịch gia hạn máy chủ của khách hàng cấp dưới',
                    'created_at' => now()->subHours(10),
                ],

                // Giao dịch tháng trước để biểu đồ Doanh thu Admin có cột phân bổ đa tháng
                [
                    'code' => 'LUG-VPS-PREV01',
                    'idempotency_key' => 'vps_prev_m1',
                    'user_id' => $huongAgency->id,
                    'type' => 'buy_vps',
                    'status' => 'completed',
                    'amount' => 750000,
                    'provider_cost' => 540000,
                    'discount_amount' => 0,
                    'balance_before' => 3000000,
                    'balance_after' => 2250000,
                    'description' => 'Đăng ký Cloud LG-32 Agency-Video-Render (32 Core · 64 GB · 500 GB SSD)',
                    'created_at' => now()->subMonths(1)->startOfMonth()->addDays(5),
                ],
                [
                    'code' => 'LUG-VPS-PREV02',
                    'idempotency_key' => 'vps_prev_m2',
                    'user_id' => $tuanMmo->id,
                    'type' => 'buy_vps',
                    'status' => 'completed',
                    'amount' => 220000,
                    'provider_cost' => 158000,
                    'discount_amount' => 0,
                    'balance_before' => 1000000,
                    'balance_after' => 780000,
                    'description' => 'Đăng ký Cloud LG-8 MMO-AutoReg-Node (8 Core · 16 GB · 120 GB SSD)',
                    'created_at' => now()->subMonths(2)->startOfMonth()->addDays(12),
                ],
                // 1 giao dịch bị hoàn tiền (để test bộ lọc và bảo toàn lợi nhuận Admin)
                [
                    'code' => 'LUG-VPS-ERR01',
                    'idempotency_key' => 'vps_err_sample',
                    'user_id' => $namDev->id,
                    'type' => 'buy_vps',
                    'status' => 'refunded',
                    'amount' => 120000,
                    'provider_cost' => 86000,
                    'discount_amount' => 0,
                    'balance_before' => 970000,
                    'balance_after' => 850000,
                    'description' => 'Đăng ký Cloud LG-4 [Đã hoàn tiền]',
                    'created_at' => now()->subDays(8),
                ],
                [
                    'code' => 'LUG-REF-ERR01',
                    'idempotency_key' => 'refund_txn_vps_err_sample',
                    'user_id' => $namDev->id,
                    'type' => 'refund',
                    'status' => 'completed',
                    'amount' => 120000,
                    'provider_cost' => 0,
                    'discount_amount' => 0,
                    'balance_before' => 850000,
                    'balance_after' => 970000,
                    'description' => 'Hoàn tiền ví do lỗi cấp phát máy chủ upstream',
                    'created_at' => now()->subDays(8)->addMinutes(5),
                ],
            ];

            foreach ($txns as $tx) {
                Transaction::create($tx);
            }
        }

        // 9. Coupon Usage
        if (CouponUsage::count() === 0) {
            $buyTxn = Transaction::where('coupon_code', 'LUGIA2026')->first();
            if ($buyTxn && $couponLugia) {
                CouponUsage::create([
                    'coupon_id' => $couponLugia->id,
                    'user_id' => $quanUser->id,
                    'transaction_id' => $buyTxn->id,
                    'discount_amount' => 50000,
                ]);
            }
        }

        // 10. Affiliate Logs
        if (AffiliateLog::count() === 0) {
            $affLogs = [
                [
                    'user_id' => $quanUser->id,
                    'buyer_id' => $huongAgency->id,
                    'transaction_type' => 'buy_vps',
                    'amount' => 750000,
                    'commission' => 37500,
                    'created_at' => now()->subDays(1),
                ],
                [
                    'user_id' => $quanUser->id,
                    'buyer_id' => $namDev->id,
                    'transaction_type' => 'buy_vps',
                    'amount' => 120000,
                    'commission' => 6000,
                    'created_at' => now()->subDays(4),
                ],
                [
                    'user_id' => $resellerUser->id,
                    'buyer_id' => $tuanMmo->id,
                    'transaction_type' => 'buy_vps',
                    'amount' => 220000,
                    'commission' => 11000,
                    'created_at' => now()->subDays(7),
                ],
                [
                    'user_id' => $resellerUser->id,
                    'buyer_id' => $tuanMmo->id,
                    'transaction_type' => 'buy_proxy',
                    'amount' => 65000,
                    'commission' => 3250,
                    'created_at' => now()->subDays(10),
                ],
                [
                    'user_id' => $adminUser->id,
                    'buyer_id' => $ducAnhVpn->id,
                    'transaction_type' => 'buy_vps',
                    'amount' => 50000,
                    'commission' => 2500,
                    'created_at' => now()->subDays(15),
                ],
            ];

            foreach ($affLogs as $log) {
                AffiliateLog::create($log);
            }
        }

        // 11. Knowledge Bases (Cơ sở tri thức AI Bot & Hướng dẫn kỹ thuật LugiaCloud)
        if (KnowledgeBase::count() === 0) {
            $kbs = [
                [
                    'title' => 'Quy trình kích hoạt và bàn giao Cloud VPS tự động',
                    'content' => "Sau khi khách hàng chọn cấu hình và bấm thanh toán từ số dư ví LugiaCloud, hệ thống sẽ tự động gửi lệnh khởi tạo đến hạ tầng cụm máy chủ FPT Datacenter HCM. Thời gian hoàn tất thông thường từ 60 đến 120 giây. Khi hoàn tất, IP, tài khoản Administrator (Windows) hoặc root (Linux), và mật khẩu khởi tạo sẽ hiển thị ngay trên trang Quản lý máy chủ và được gửi về email đăng ký.",
                    'is_active' => true,
                ],
                [
                    'title' => 'Chính sách gia hạn, sao lưu dữ liệu và thu hồi máy chủ',
                    'content' => "LugiaCloud gửi thông báo gia hạn tự động qua Email và Telegram trước ngày hết hạn 7 ngày, 3 ngày và 24 giờ. Nếu máy chủ quá hạn thanh toán, hệ thống sẽ tự động tạm dừng dịch vụ (Suspend). Sau 5 ngày quá hạn kể từ ngày hết hạn, toàn bộ dữ liệu trên ổ cứng SSD chuyên dụng sẽ được thu hồi tự động và không thể phục hồi.",
                    'is_active' => true,
                ],
                [
                    'title' => 'Hướng dẫn kết nối Remote Desktop (Windows) và SSH (Linux)',
                    'content' => "Đối với Windows Server / Windows 10: Khách hàng sử dụng ứng dụng Remote Desktop Connection (mstsc.exe) trên máy tính hoặc tải trực tiếp file .RDP từ LugiaCloud. Nhập IP máy chủ, Username: Administrator, và mật khẩu khởi tạo. Đối với Linux (Ubuntu, Debian, CentOS, AlmaLinux): Sử dụng ứng dụng Terminal hoặc PuTTY kết nối qua SSH cổng 22 với lệnh: ssh root@<Địa_chỉ_IP>.",
                    'is_active' => true,
                ],
                [
                    'title' => 'Chính sách đối tác Đại lý API (Reseller) và Webhook',
                    'content' => "Đối tác Đại lý tại LugiaCloud được hưởng chính sách chiết khấu trực tiếp 10% đến 25% trên toàn bộ danh mục Cloud VPS SSD và Proxy IPv4 Việt Nam. Đại lý có thể tích hợp đầy đủ API RESTful để tra cứu cấu hình, khởi tạo máy chủ tự động, theo dõi trạng thái, và nhận Webhook thông báo thời gian thực khi máy chủ sẵn sàng hoặc có thay đổi số dư.",
                    'is_active' => true,
                ],
            ];

            foreach ($kbs as $kb) {
                KnowledgeBase::create($kb);
            }
        }

        // 12. Settings
        Setting::updateOrCreate(
            ['key' => 'vps_out_of_stock'],
            ['value' => '0', 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['key' => 'site_maintenance'],
            ['value' => '0', 'type' => 'boolean']
        );
        Setting::updateOrCreate(
            ['key' => 'affiliate_commission_percent'],
            ['value' => '5', 'type' => 'integer']
        );
        Setting::updateOrCreate(
            ['key' => 'support_hotline'],
            ['value' => '0988.888.888', 'type' => 'string']
        );

        // Xóa sạch cache để giao diện nhận dữ liệu mới ngay lập tức
        app(PromotionEngine::class)->flushCache();
        app(CloudSunnyPricingService::class)->flushCache();
    }
}

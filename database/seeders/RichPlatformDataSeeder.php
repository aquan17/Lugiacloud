<?php

namespace Database\Seeders;

use App\Constants\UserRole;
use App\Models\AffiliateLog;
use App\Models\CloudSunnyAccount;
use App\Models\Coupon;
use App\Models\KnowledgeBase;
use App\Models\PricingCatalog;
use App\Models\ProxyInstance;
use App\Models\TopupRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VpsInstance;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RichPlatformDataSeeder extends Seeder
{
    public function run(): void
    {
        $quanUser = User::where('email', 'quanpc2001@gmail.com')->first();
        if (!$quanUser) {
            $quanUser = User::first();
        }

        $resellerUser = User::where('email', 'reseller@lugiacloud.vn')->first();
        $adminUser = User::where('email', 'admin@lugiacloud.vn')->first();

        $accountNode1 = CloudSunnyAccount::first();

        // 1. Thêm danh sách khách hàng F1 cho Quân PC (User #2)
        $f1Customers = [
            [
                'email' => 'quocbao.fintech@gmail.com',
                'name' => 'Quốc Bảo FinTech',
                'phone' => '0918112233',
                'balance' => 4500000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'lananh.ecom@gmail.com',
                'name' => 'Lan Anh E-commerce Shop',
                'phone' => '0922334455',
                'balance' => 1250000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'tienlong.studio@gmail.com',
                'name' => 'Tiến Long Game Studio',
                'phone' => '0933445566',
                'balance' => 8900000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'quanghuy.botnet@gmail.com',
                'name' => 'Quang Huy Automation Tool',
                'phone' => '0944556688',
                'balance' => 620000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'trungdung.hosting@gmail.com',
                'name' => 'Trung Dũng Reseller Cloud',
                'phone' => '0955667788',
                'balance' => 3100000,
                'role' => UserRole::RESELLER,
            ],
            [
                'email' => 'ngocanh.marketing@gmail.com',
                'name' => 'Ngọc Ánh Digital Agency',
                'phone' => '0966778811',
                'balance' => 2150000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'khanhtoan.crypto@gmail.com',
                'name' => 'Khánh Toàn Web3 Validator',
                'phone' => '0977889922',
                'balance' => 5400000,
                'role' => UserRole::CUSTOMER,
            ],
            [
                'email' => 'vietthang.security@gmail.com',
                'name' => 'Việt Thắng Cyber Security',
                'phone' => '0988991133',
                'balance' => 1800000,
                'role' => UserRole::CUSTOMER,
            ],
        ];

        $createdF1 = [];
        foreach ($f1Customers as $c) {
            $u = User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => Hash::make('Admin@123456'),
                    'balance' => $c['balance'],
                    'is_admin' => $c['role'],
                    'ref_code' => strtoupper(Str::random(8)),
                    'referred_by' => $quanUser->id,
                    'email_verified_at' => now()->subDays(rand(10, 90)),
                ]
            );
            $createdF1[] = $u;
        }

        // 2. Thêm Affiliate Logs phong phú cho Quân PC (để bảng hoa hồng đầy ắp)
        $services = ['buy_vps', 'renew_vps', 'buy_proxy', 'renew_proxy', 'upgrade_vps'];
        $amounts = [
            'buy_vps' => [120000, 220000, 390000, 750000, 1400000],
            'renew_vps' => [120000, 220000, 390000],
            'buy_proxy' => [35000, 65000, 105000, 195000],
            'renew_proxy' => [35000, 65000],
            'upgrade_vps' => [60000, 120000, 180000],
        ];

        foreach ($createdF1 as $f1) {
            $numLogs = rand(2, 4);
            for ($k = 0; $k < $numLogs; $k++) {
                $type = $services[array_rand($services)];
                $amtList = $amounts[$type];
                $amt = $amtList[array_rand($amtList)];
                $comm = (int) round($amt * 0.05); // 5% hoa hồng
                $daysAgo = rand(1, 55);

                AffiliateLog::firstOrCreate(
                    [
                        'user_id' => $quanUser->id,
                        'buyer_id' => $f1->id,
                        'transaction_type' => $type,
                        'amount' => $amt,
                        'created_at' => Carbon::now()->subDays($daysAgo)->subHours(rand(1, 12)),
                    ],
                    [
                        'commission' => $comm,
                    ]
                );
            }
        }

        // 3. Thêm VPS Instances phong phú
        $extraVps = [
            // User #2 VPS
            [
                'user_id' => $quanUser->id,
                'label' => 'LUGIA-API-PROD-01',
                'plan_id' => 'lg-4',
                'provider_os_id' => 14, // Ubuntu 24.04
                'cpu' => 2,
                'ram' => 4,
                'disk' => 30,
                'public_ip' => '103.145.4.155',
                'login_username' => 'root',
                'root_password' => 'LugiaApi@Prod2026',
                'status' => 'Sẵn sàng',
                'paid_amount' => 220000,
                'cost_monthly_usd' => '9.5000',
                'provider_cost' => 150000,
                'expires_at' => Carbon::now()->addDays(28),
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'user_id' => $quanUser->id,
                'label' => 'LUGIA-GAME-MU-VN',
                'plan_id' => 'lg-16',
                'provider_os_id' => 10, // Windows Server 2022
                'cpu' => 8,
                'ram' => 16,
                'disk' => 70,
                'public_ip' => '180.93.200.222',
                'login_username' => 'Administrator',
                'root_password' => 'MuOnline@Admin888',
                'status' => 'Sẵn sàng',
                'paid_amount' => 750000,
                'cost_monthly_usd' => '32.0000',
                'provider_cost' => 520000,
                'expires_at' => Carbon::now()->addDays(14),
                'created_at' => Carbon::now()->subDays(16),
            ],
            [
                'user_id' => $quanUser->id,
                'label' => 'LUGIA-DEV-STAGING',
                'plan_id' => 'lg-2',
                'provider_os_id' => 2, // Debian 12
                'cpu' => 1,
                'ram' => 2,
                'disk' => 25,
                'public_ip' => '180.93.201.88',
                'login_username' => 'root',
                'root_password' => 'Staging@Dev777',
                'status' => 'Đã tắt',
                'paid_amount' => 120000,
                'cost_monthly_usd' => '5.2000',
                'provider_cost' => 80000,
                'expires_at' => Carbon::now()->addDays(22),
                'created_at' => Carbon::now()->subDays(8),
            ],
            [
                'user_id' => $quanUser->id,
                'label' => 'LUGIA-PROXY-RELAY',
                'plan_id' => 'lg-1',
                'provider_os_id' => 3, // CentOS / AlmaLinux
                'cpu' => 1,
                'ram' => 1,
                'disk' => 20,
                'public_ip' => '103.145.5.40',
                'login_username' => 'root',
                'root_password' => 'Relay@Proxy999',
                'status' => 'Sẵn sàng',
                'paid_amount' => 70000,
                'cost_monthly_usd' => '3.0000',
                'provider_cost' => 45000,
                'expires_at' => Carbon::now()->addDays(3), // Expiring soon
                'created_at' => Carbon::now()->subDays(27),
            ],
            // Other Users VPS
            [
                'user_id' => $createdF1[0]->id,
                'label' => 'FinTech-Trading-Bot',
                'plan_id' => 'lg-8',
                'provider_os_id' => 14,
                'cpu' => 4,
                'ram' => 8,
                'disk' => 50,
                'public_ip' => '103.145.4.110',
                'login_username' => 'root',
                'root_password' => 'Trading@Bot444',
                'status' => 'Sẵn sàng',
                'paid_amount' => 390000,
                'cost_monthly_usd' => '16.8000',
                'provider_cost' => 270000,
                'expires_at' => Carbon::now()->addDays(25),
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'user_id' => $createdF1[1]->id,
                'label' => 'WooCommerce-Shop-01',
                'plan_id' => 'lg-4',
                'provider_os_id' => 1,
                'cpu' => 2,
                'ram' => 4,
                'disk' => 30,
                'public_ip' => '180.93.200.170',
                'login_username' => 'root',
                'root_password' => 'WooShop@2026',
                'status' => 'Sẵn sàng',
                'paid_amount' => 220000,
                'cost_monthly_usd' => '9.5000',
                'provider_cost' => 150000,
                'expires_at' => Carbon::now()->addDays(19),
                'created_at' => Carbon::now()->subDays(11),
            ],
            [
                'user_id' => $createdF1[2]->id,
                'label' => 'Unity-Build-Server',
                'plan_id' => 'lg-32',
                'provider_os_id' => 10,
                'cpu' => 16,
                'ram' => 32,
                'disk' => 100,
                'public_ip' => '180.93.202.99',
                'login_username' => 'Administrator',
                'root_password' => 'UnityCI@Build777',
                'status' => 'Sẵn sàng',
                'paid_amount' => 1400000,
                'cost_monthly_usd' => '60.0000',
                'provider_cost' => 950000,
                'expires_at' => Carbon::now()->addDays(15),
                'created_at' => Carbon::now()->subDays(15),
            ],
            [
                'user_id' => $createdF1[3]->id,
                'label' => 'Telegram-Scraper-VN',
                'plan_id' => 'lg-1',
                'provider_os_id' => 1,
                'cpu' => 1,
                'ram' => 1,
                'disk' => 20,
                'public_ip' => null,
                'login_username' => 'root',
                'root_password' => 'Scraper@Tel888',
                'status' => 'Đang khởi tạo...',
                'paid_amount' => 70000,
                'cost_monthly_usd' => '3.0000',
                'provider_cost' => 45000,
                'expires_at' => Carbon::now()->addDays(30),
                'created_at' => Carbon::now()->subMinutes(3),
            ],
            [
                'user_id' => $createdF1[4]->id,
                'label' => 'Demo-Customer-Expired',
                'plan_id' => 'lg-2',
                'provider_os_id' => 3,
                'cpu' => 1,
                'ram' => 2,
                'disk' => 25,
                'public_ip' => '180.93.201.250',
                'login_username' => 'root',
                'root_password' => 'OldPass@123',
                'status' => 'Hết hạn',
                'paid_amount' => 120000,
                'cost_monthly_usd' => '5.2000',
                'provider_cost' => 80000,
                'expires_at' => Carbon::now()->subDays(4),
                'created_at' => Carbon::now()->subDays(34),
            ],
        ];

        foreach ($extraVps as $v) {
            VpsInstance::updateOrCreate(
                ['label' => $v['label'], 'user_id' => $v['user_id']],
                array_merge($v, [
                    'cloudsunny_account_id' => $accountNode1?->id,
                    'provider_vps_id' => rand(18000, 25000),
                    'provider_product_id' => 1,
                    'region' => 'FPT HCM',
                    'billing_cycle' => 'monthly',
                ])
            );
        }

        // 4. Thêm Proxy Instances đa dạng
        $extraProxies = [
            // User #2 Proxies
            [
                'user_id' => $quanUser->id,
                'product_id' => 8, // Datacenter 1:1
                'type_proxy' => 'HTTP',
                'ip' => '103.145.4.210',
                'port' => '8080',
                'username' => 'lg_px_vip01',
                'password' => 'px_pass_vip111',
                'sock5_port' => '1080',
                'sock5_username' => 'lg_s5_vip01',
                'sock5_password' => 's5_pass_vip111',
                'status' => 'Hoạt động',
                'is_auto_renew' => true,
                'paid_amount' => 35000,
                'expires_at' => Carbon::now()->addDays(29),
                'created_at' => Carbon::now()->subDays(1),
            ],
            [
                'user_id' => $quanUser->id,
                'product_id' => 10, // Residential 1:1
                'type_proxy' => 'SOCKS5',
                'ip' => '103.145.5.80',
                'port' => '8443',
                'username' => 'lg_res_vip02',
                'password' => 'res_pass_vip222',
                'sock5_port' => '1085',
                'sock5_username' => 'lg_res_s5_02',
                'sock5_password' => 'res_s5_pass222',
                'status' => 'Hoạt động',
                'is_auto_renew' => true,
                'paid_amount' => 65000,
                'expires_at' => Carbon::now()->addDays(24),
                'created_at' => Carbon::now()->subDays(6),
            ],
            [
                'user_id' => $quanUser->id,
                'product_id' => 7, // Datacenter Shared
                'type_proxy' => 'HTTP',
                'ip' => '180.93.201.120',
                'port' => '3128',
                'username' => 'lg_sh_exp03',
                'password' => 'sh_pass_333',
                'sock5_port' => '1090',
                'sock5_username' => 'lg_sh_s5_03',
                'sock5_password' => 'sh_s5_pass333',
                'status' => 'Hoạt động',
                'is_auto_renew' => false,
                'paid_amount' => 15000,
                'expires_at' => Carbon::now()->addDays(2), // Sắp hết hạn
                'created_at' => Carbon::now()->subDays(28),
            ],
            // Other Users Proxies
            [
                'user_id' => $createdF1[1]->id,
                'product_id' => 8,
                'type_proxy' => 'HTTP',
                'ip' => '180.93.200.95',
                'port' => '8080',
                'username' => 'ecom_px_01',
                'password' => 'ecom_pass_999',
                'sock5_port' => '1080',
                'sock5_username' => 'ecom_s5_01',
                'sock5_password' => 'ecom_s5_pass',
                'status' => 'Hoạt động',
                'is_auto_renew' => true,
                'paid_amount' => 35000,
                'expires_at' => Carbon::now()->addDays(27),
                'created_at' => Carbon::now()->subDays(3),
            ],
            [
                'user_id' => $createdF1[3]->id,
                'product_id' => 10,
                'type_proxy' => 'SOCKS5',
                'ip' => '103.145.4.199',
                'port' => '8443',
                'username' => 'bot_res_01',
                'password' => 'bot_pass_123',
                'sock5_port' => '1082',
                'sock5_username' => 'bot_s5_01',
                'sock5_password' => 'bot_s5_pass123',
                'status' => 'Hoạt động',
                'is_auto_renew' => true,
                'paid_amount' => 65000,
                'expires_at' => Carbon::now()->addDays(18),
                'created_at' => Carbon::now()->subDays(12),
            ],
            [
                'user_id' => $createdF1[5]->id,
                'product_id' => 7,
                'type_proxy' => 'HTTP',
                'ip' => '180.93.201.215',
                'port' => '3128',
                'username' => 'mkt_px_01',
                'password' => 'mkt_pass_444',
                'sock5_port' => '1095',
                'sock5_username' => 'mkt_s5_01',
                'sock5_password' => 'mkt_s5_pass444',
                'status' => 'Hết hạn',
                'is_auto_renew' => false,
                'paid_amount' => 15000,
                'expires_at' => Carbon::now()->subDays(3),
                'created_at' => Carbon::now()->subDays(33),
            ],
        ];

        foreach ($extraProxies as $px) {
            ProxyInstance::updateOrCreate(
                ['ip' => $px['ip'], 'port' => $px['port']],
                array_merge($px, [
                    'cloudsunny_account_id' => $accountNode1?->id,
                    'provider_proxy_id' => rand(88000, 99000),
                    'billing_cycle' => 'monthly',
                    'cost_monthly_usd' => '1.5000',
                ])
            );
        }

        // 5. Thêm Topup Requests phong phú (VietQR & PayOS)
        $extraTopups = [
            [
                'user_id' => $quanUser->id,
                'code' => 'LUGIA-TOP-8802',
                'amount' => 1000000,
                'provider' => 'vietqr',
                'status' => 'paid',
                'provider_order_code' => 8819201,
                'transaction_ref' => 'ref_topup_8819201',
                'created_at' => Carbon::now()->subDays(3)->subHours(2),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'LUGIA-TOP-8803',
                'amount' => 500000,
                'provider' => 'payos',
                'status' => 'paid',
                'provider_order_code' => 8819202,
                'transaction_ref' => 'ref_topup_8819202',
                'created_at' => Carbon::now()->subDays(12)->subHours(5),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'LUGIA-TOP-8804',
                'amount' => 2000000,
                'provider' => 'vietqr',
                'status' => 'paid',
                'provider_order_code' => 8819203,
                'transaction_ref' => 'ref_topup_8819203',
                'created_at' => Carbon::now()->subDays(25),
            ],
            [
                'user_id' => $createdF1[0]->id,
                'code' => 'LUGIA-TOP-8805',
                'amount' => 5000000,
                'provider' => 'vietqr',
                'status' => 'paid',
                'provider_order_code' => 8819204,
                'transaction_ref' => 'ref_topup_8819204',
                'created_at' => Carbon::now()->subDays(6),
            ],
            [
                'user_id' => $createdF1[2]->id,
                'code' => 'LUGIA-TOP-8806',
                'amount' => 10000000,
                'provider' => 'payos',
                'status' => 'paid',
                'provider_order_code' => 8819205,
                'transaction_ref' => 'ref_topup_8819205',
                'created_at' => Carbon::now()->subDays(16),
            ],
        ];

        foreach ($extraTopups as $tu) {
            TopupRequest::updateOrCreate(
                ['code' => $tu['code']],
                $tu
            );
        }

        // 6. Thêm Sổ cái Transactions phong phú (cho cả hóa đơn và doanh thu tháng)
        $extraTxns = [
            [
                'user_id' => $quanUser->id,
                'code' => 'TXN-TOP-8802',
                'type' => 'topup',
                'amount' => 1000000,
                'status' => 'completed',
                'balance_before' => 21672611,
                'balance_after' => 22672611,
                'description' => 'Nạp tiền tự động qua VietQR Pro #8819201',
                'created_at' => Carbon::now()->subDays(3)->subHours(2),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'TXN-VPS-API01',
                'type' => 'buy_vps',
                'amount' => -220000,
                'status' => 'completed',
                'balance_before' => 21892611,
                'balance_after' => 21672611,
                'description' => 'Thanh toán khởi tạo Cloud VPS LUGIA-API-PROD-01 (Ubuntu 24.04)',
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'TXN-PX-VIP01',
                'type' => 'buy_proxy',
                'amount' => -35000,
                'status' => 'completed',
                'balance_before' => 21927611,
                'balance_after' => 21892611,
                'description' => 'Thanh toán Proxy Datacenter Riêng 1:1 (IP 103.145.4.210)',
                'created_at' => Carbon::now()->subDays(1),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'TXN-AFF-HUONG01',
                'type' => 'affiliate_commission',
                'amount' => 37500,
                'status' => 'completed',
                'balance_before' => 21890111,
                'balance_after' => 21927611,
                'description' => 'Cộng hoa hồng giới thiệu 5% từ đơn hàng F1 Thanh Hương Media',
                'created_at' => Carbon::now()->subDays(1)->subHours(4),
            ],
            [
                'user_id' => $quanUser->id,
                'code' => 'TXN-AFF-LONG01',
                'type' => 'affiliate_commission',
                'amount' => 70000,
                'status' => 'completed',
                'balance_before' => 21820111,
                'balance_after' => 21890111,
                'description' => 'Cộng hoa hồng giới thiệu 5% từ đơn hàng F1 Tiến Long Studio',
                'created_at' => Carbon::now()->subDays(15),
            ],
            // Transactions các tháng trước để biểu đồ doanh thu Admin nhảy số đều
            [
                'user_id' => $createdF1[0]->id,
                'code' => 'TXN-F1-BAO01',
                'type' => 'buy_vps',
                'amount' => -390000,
                'status' => 'completed',
                'balance_before' => 4890000,
                'balance_after' => 4500000,
                'description' => 'Khách hàng thanh toán Cloud LG-8 (FinTech Trading Bot)',
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'user_id' => $createdF1[2]->id,
                'code' => 'TXN-F1-LONG01',
                'type' => 'buy_vps',
                'amount' => -1400000,
                'status' => 'completed',
                'balance_before' => 10300000,
                'balance_after' => 8900000,
                'description' => 'Khách hàng thanh toán Cloud LG-32 (Unity Build Server)',
                'created_at' => Carbon::now()->subDays(15),
            ],
            [
                'user_id' => $createdF1[1]->id,
                'code' => 'TXN-F1-ANH01',
                'type' => 'buy_vps',
                'amount' => -220000,
                'status' => 'completed',
                'balance_before' => 1470000,
                'balance_after' => 1250000,
                'description' => 'Khách hàng thanh toán Cloud LG-4 (WooCommerce Store)',
                'created_at' => Carbon::now()->subMonths(1)->subDays(10),
            ],
            [
                'user_id' => $createdF1[4]->id,
                'code' => 'TXN-F1-DUNG01',
                'type' => 'buy_vps',
                'amount' => -750000,
                'status' => 'completed',
                'balance_before' => 3850000,
                'balance_after' => 3100000,
                'description' => 'Khách hàng thanh toán Cloud LG-16 (Game Server Cluster)',
                'created_at' => Carbon::now()->subMonths(2)->subDays(5),
            ],
        ];

        foreach ($extraTxns as $t) {
            Transaction::updateOrCreate(
                ['code' => $t['code']],
                $t
            );
        }

        // 7. Thêm bài viết Tri Thức Kỹ Thuật (Knowledge Base)
        $extraKbs = [
            [
                'title' => 'Cách cấu hình Proxy IPv4 trên trình duyệt Antidetect (AdsPower, GoLogin)',
                'content' => "Để cấu hình Proxy LugiaCloud trên AdsPower hoặc GoLogin:\n1. Chọn giao thức HTTP hoặc SOCKS5 tùy nhu cầu.\n2. Sao chép trực tiếp chuỗi kết nối định dạng `IP:Port:User:Pass` từ bảng điều khiển LugiaCloud.\n3. Dán vào ô 'Proxy Quick Import' trên AdsPower hoặc trường Proxy trong GoLogin.\n4. Bấm 'Check Proxy' để kiểm tra kết nối: Hệ thống sẽ hiển thị IP Việt Nam, độ trễ Ping < 15ms và múi giờ Asia/Ho_Chi_Minh chuẩn xác 100%.",
                'is_active' => true,
            ],
            [
                'title' => 'Hướng dẫn kết nối Cloud VPS Windows bằng Remote Desktop trên điện thoại iPhone & Android',
                'content' => "Khách hàng có thể quản trị máy chủ Windows mọi lúc mọi nơi từ điện thoại:\n1. Tải ứng dụng 'RD Client' (Microsoft Remote Desktop) từ App Store hoặc Google Play.\n2. Bấm dấu '+' để thêm PC mới.\n3. Nhập IP máy chủ tại ô PC Name (ví dụ: 180.93.200.15).\n4. User Account: chọn 'Add user' -> Nhập Username là 'Administrator' và Mật khẩu khởi tạo được cấp trên LugiaCloud.\n5. Bấm Save và chạm vào biểu tượng máy chủ để bắt đầu điều khiển từ xa.",
                'is_active' => true,
            ],
            [
                'title' => 'Cách mở rộng dung lượng ổ cứng (Extend Volume) sau khi nâng cấp Disk SSD',
                'content' => "Khi quý khách nâng cấp thêm dung lượng ổ cứng SSD cho máy chủ Windows:\n1. Mở cửa sổ Run (Win + R) -> nhập `diskmgmt.msc` và nhấn Enter.\n2. Quý khách sẽ thấy phân vùng unallocated mới được cộng thêm ở cuối đĩa.\n3. Chuột phải vào ổ C: -> chọn 'Extend Volume...' -> bấm Next liên tục đến khi Finish.\nĐối với Linux: Sử dụng lệnh `growpart /dev/vda 1` và `resize2fs /dev/vda1` để mở rộng phân vùng không cần khởi động lại máy chủ.",
                'is_active' => true,
            ],
            [
                'title' => 'Tối ưu bảo mật máy chủ: Đổi cổng SSH mặc định (Port 22) trên Linux',
                'content' => "Để ngăn chặn các cuộc tấn công Brute-force rà quét cổng mặc định:\n1. Mở file cấu hình SSH bằng lệnh: `nano /etc/ssh/sshd_config`.\n2. Tìm dòng `#Port 22`, bỏ dấu `#` và đổi sang cổng mới (ví dụ: `Port 2222`).\n3. Mở cổng trên tường lửa UFW: `ufw allow 2222/tcp`.\n4. Khởi động lại dịch vụ SSH: `systemctl restart sshd`.\nLưu ý: Luôn giữ một cửa sổ SSH hiện tại mở để kiểm tra kết nối thử ở cửa sổ mới trước khi thoát.",
                'is_active' => true,
            ],
        ];

        foreach ($extraKbs as $kb) {
            KnowledgeBase::updateOrCreate(
                ['title' => $kb['title']],
                $kb
            );
        }

        // 8. Thêm Coupons Khuyến Mãi
        $extraCoupons = [
            [
                'code' => 'VIPRESELLER20',
                'description' => 'Ưu đãi đối tác đại lý nạp tiền & mua gói lớn giảm 20%',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'min_order_amount' => 500000,
                'max_discount_amount' => 200000,
                'max_uses' => 100,
                'used_count' => 14,
                'applies_to' => 'all',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(10),
                'ends_at' => Carbon::now()->addDays(60),
            ],
            [
                'code' => 'CHAOXUAN2026',
                'description' => 'Mã giảm trực tiếp 50.000đ cho đơn hàng Cloud VPS từ 3 tháng',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'min_order_amount' => 300000,
                'max_discount_amount' => 50000,
                'max_uses' => 500,
                'used_count' => 88,
                'applies_to' => 'vps',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(5),
                'ends_at' => Carbon::now()->addDays(90),
            ],
            [
                'code' => 'PROXY10FREE',
                'description' => 'Giảm 10% khi thuê từ 5 IP Proxy Datacenter / Dân cư',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'min_order_amount' => 100000,
                'max_discount_amount' => 100000,
                'max_uses' => 200,
                'used_count' => 32,
                'applies_to' => 'proxy',
                'is_active' => true,
                'starts_at' => Carbon::now()->subDays(8),
                'ends_at' => Carbon::now()->addDays(45),
            ],
        ];

        foreach ($extraCoupons as $cp) {
            Coupon::updateOrCreate(
                ['code' => $cp['code']],
                $cp
            );
        }
    }
}

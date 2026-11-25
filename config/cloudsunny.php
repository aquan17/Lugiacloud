<?php

return [
    'api_base' => env('CLOUDSUNNY_API_BASE', 'https://api.cloudsunny.net/api'),
    'api_username' => env('CLOUDSUNNY_API_USERNAME'),
    'api_app' => env('CLOUDSUNNY_API_APP_KEY'),
    'api_secret' => env('CLOUDSUNNY_API_SECRET_KEY'),
    'cache_ttl' => (int) env('CLOUDSUNNY_CACHE_TTL', 300),

    'default_os_id' => (int) env('CLOUDSUNNY_DEFAULT_OS_ID', 1),
    'default_billing_cycle' => env('CLOUDSUNNY_DEFAULT_BILLING_CYCLE', 'monthly'),

    'addon_prices' => [
        'cpu_monthly' => (int) env('CLOUDSUNNY_ADDON_CPU_MONTHLY', 25000),
        'ram_monthly' => (int) env('CLOUDSUNNY_ADDON_RAM_MONTHLY', 25000),
        'disk_10gb_monthly' => (int) env('CLOUDSUNNY_ADDON_DISK_10GB_MONTHLY', 15000),
        'change_ip' => (int) env('CLOUDSUNNY_ADDON_CHANGE_IP', 30000),
    ],

    // Chi phí gốc từ Provider (Để tính lợi nhuận). Nếu bỏ trống sẽ tự lấy bằng giá bán ở trên.
    'provider_addon_prices' => [
        'cpu_monthly' => (int) env('CLOUDSUNNY_PROVIDER_ADDON_CPU_MONTHLY', 16200),
        'ram_monthly' => (int) env('CLOUDSUNNY_PROVIDER_ADDON_RAM_MONTHLY', 16200),
        'disk_10gb_monthly' => (int) env('CLOUDSUNNY_PROVIDER_ADDON_DISK_10GB_MONTHLY', 5400),
        'change_ip' => (int) env('CLOUDSUNNY_PROVIDER_ADDON_CHANGE_IP', 20000),
    ],

    'proxy_plans' => [
        12 => [
            'name' => 'Proxy Datacenter — Shared',
            'price_per_month' => (int) env('CLOUDSUNNY_PROXY_12_MONTHLY', 15000),
            'provider_price_per_month' => (int) env('CLOUDSUNNY_PROVIDER_PROXY_12_MONTHLY', 9000),
        ],
        13 => [
            'name' => 'Proxy Datacenter — Riêng 1:1',
            'price_per_month' => (int) env('CLOUDSUNNY_PROXY_13_MONTHLY', 39000),
            'provider_price_per_month' => (int) env('CLOUDSUNNY_PROVIDER_PROXY_13_MONTHLY', 27000),
        ],
        14 => [
            'name' => 'Proxy Dân Cư VN — Shared',
            'price_per_month' => (int) env('CLOUDSUNNY_PROXY_14_MONTHLY', 20000),
            'provider_price_per_month' => (int) env('CLOUDSUNNY_PROVIDER_PROXY_14_MONTHLY', 14000),
        ],
        15 => [
            'name' => 'Proxy Dân Cư VN — Riêng 1:1',
            'price_per_month' => (int) env('CLOUDSUNNY_PROXY_15_MONTHLY', 75000),
            'provider_price_per_month' => (int) env('CLOUDSUNNY_PROVIDER_PROXY_15_MONTHLY', 54000),
        ],
    ],

    // -----------------------------------------------------------------------
    // Chu kỳ thanh toán (Tháng, Quý, Năm) & Giảm giá
    // -----------------------------------------------------------------------
    'billing_cycles' => [
        'monthly' => ['months' => 1, 'label' => '1 Tháng', 'discount_percent' => 0],
        'twomonthly' => ['months' => 2, 'label' => '2 Tháng', 'discount_percent' => 2],
        'quarterly' => ['months' => 3, 'label' => '3 Tháng', 'discount_percent' => 3],
        'semi_annually' => ['months' => 6, 'label' => '6 Tháng', 'discount_percent' => 5],
        'annually' => ['months' => 12, 'label' => '1 Năm', 'discount_percent' => 8],
        'biennially' => ['months' => 24, 'label' => '2 Năm', 'discount_percent' => 10],
        'triennially' => ['months' => 36, 'label' => '3 Năm', 'discount_percent' => 12],
    ],

    'proxy_billing_cycles' => ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually'],

    'plans' => [
        'lg-1' => [
            'provider_id' => 'sn-1-1-20',
            'name' => 'Cloud LG-1',
            'desc' => 'Phù hợp làm Proxy/VPN riêng, bot Telegram, web tĩnh nhẹ.',
            'badge' => 'Tiết kiệm',
            'price_per_month' => 55000,
            'provider_price_per_month' => 38000,
            'cores' => 1,
            'ram' => 1,
            'disk' => 20,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 100,
        ],
        'lg-2' => [
            'provider_id' => 'sn-1-2-25',
            'name' => 'Cloud LG-2',
            'desc' => 'Chạy mượt WordPress, landing page, treo tool nhẹ 24/7.',
            'badge' => null,
            'price_per_month' => 70000,
            'provider_price_per_month' => 49000,
            'cores' => 1,
            'ram' => 2,
            'disk' => 25,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 100,
        ],
        'lg-4' => [
            'provider_id' => 'sn-2-4-30',
            'name' => 'Cloud LG-4',
            'desc' => 'Chạy ổn định Windows 10/Server, web bán hàng, API vừa.',
            'badge' => 'Phổ thông',
            'price_per_month' => 120000,
            'provider_price_per_month' => 81000,
            'cores' => 2,
            'ram' => 4,
            'disk' => 30,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 100,
        ],
        'lg-8' => [
            'provider_id' => 'sn-4-8-50',
            'name' => 'Cloud LG-8',
            'desc' => 'Đa nhiệm MMO, nuôi trình duyệt, web tải khá, server game nhỏ.',
            'badge' => 'Bán chạy',
            'price_per_month' => 230000,
            'provider_price_per_month' => 162000,
            'cores' => 4,
            'ram' => 8,
            'disk' => 50,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 1000,
        ],
        'lg-16' => [
            'provider_id' => 'sn-8-16-70',
            'name' => 'Cloud LG-16',
            'desc' => 'Server game đông người, database lớn, treo nhiều tab/tài khoản.',
            'badge' => 'Hiệu năng cao',
            'price_per_month' => 450000,
            'provider_price_per_month' => 313000,
            'cores' => 8,
            'ram' => 16,
            'disk' => 70,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 1000,
        ],
        'lg-32' => [
            'provider_id' => 'sn-16-32-100',
            'name' => 'Cloud LG-32',
            'desc' => 'Cấu hình tải nặng liên tục cho hệ thống lớn và cụm game server.',
            'badge' => 'Chuyên dụng',
            'price_per_month' => 850000,
            'provider_price_per_month' => 594000,
            'cores' => 16,
            'ram' => 32,
            'disk' => 100,
            'transfer_tb' => 'Unlimited',
            'network_out_mbps' => 1000,
        ],
    ],

    'images' => [
        1 => ['label' => 'Windows Server 2012 R2', 'icon' => '🪟', 'group' => 'Windows'],
        2 => ['label' => 'Windows Server 2016', 'icon' => '🪟', 'group' => 'Windows'],
        3 => ['label' => 'Linux CentOS 7 64bit', 'icon' => '🐧', 'group' => 'Linux'],
        4 => ['label' => 'Windows Server 2019', 'icon' => '🪟', 'group' => 'Windows'],
        5 => ['label' => 'Windows 10 64bit', 'icon' => '🪟', 'group' => 'Windows'],
        6 => ['label' => 'Linux Ubuntu-20.04', 'icon' => '🐧', 'group' => 'Linux'],
        7 => ['label' => 'Linux Ubuntu-22.04', 'icon' => '🐧', 'group' => 'Linux'],
        8 => ['label' => 'Debian 11', 'icon' => '🐧', 'group' => 'Linux'],
        9 => ['label' => 'AlmaLinux 8', 'icon' => '🐧', 'group' => 'Linux'],
        15 => ['label' => 'Windows Server 2022', 'icon' => '🪟', 'group' => 'Windows'],
    ],
];

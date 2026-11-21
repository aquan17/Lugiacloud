<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $seoTitle = $seo['title'] ?? (config('app.name', 'LugiaCloud') . ' — Thuê Cloud VPS SSD & Proxy IPv4 Việt Nam Chất Lượng Cao');
        $seoDescription = $seo['description'] ?? 'LugiaCloud (Lugiacloud.com) vận hành hệ thống Cloud VPS SSD chuyên dụng đặt tại FPT Datacenter TP.HCM, CPU Intel Xeon Gold/Platinum, Anti DDoS và Proxy IPv4 Việt Nam tự động 24/7.';
        $seoType = $seo['type'] ?? 'website';
        $seoImage = $seo['image'] ?? asset('og-cover.jpg');
        $seoCanonical = $seo['canonical'] ?? url()->current();
    @endphp
    <title inertia>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ $seoCanonical }}">
    <meta property="og:type" content="{{ $seoType }}">
    <meta property="og:site_name" content="{{ config('app.name', 'LugiaCloud') }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @if(!empty($seo['published_time']))
    <meta property="article:published_time" content="{{ $seo['published_time'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    <link rel="icon" type="image/png" href="{{ asset('bird-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if($cdnUrl = config('filesystems.disks.r2.url'))
    <link rel="preconnect" href="{{ rtrim($cdnUrl, '/') }}" crossorigin>
    <link rel="dns-prefetch" href="{{ rtrim($cdnUrl, '/') }}">
    @endif
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        #lugia-boot-screen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F5F7FB;
            transition: opacity 180ms ease-out;
        }
        #lugia-boot-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            opacity: 0;
            animation: lugiaBootReveal 200ms ease-out 90ms forwards;
        }
        #lugia-boot-bar-track {
            width: 120px;
            height: 3px;
            border-radius: 999px;
            background: #E2E8F0;
            overflow: hidden;
        }
        #lugia-boot-bar-fill {
            width: 48%;
            height: 100%;
            border-radius: 999px;
            background: #1D6AE5;
            animation: lugiaBootSlide 1.1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
        @keyframes lugiaBootReveal {
            to { opacity: 1; }
        }
        @keyframes lugiaBootSlide {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(60%); }
            100% { transform: translateX(220%); }
        }
        @media print {
            #lugia-boot-screen { display: none !important; }
        }
    </style>

    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "Organization",
      "name": "LugiaCloud",
      "url": "https://lugiacloud.com",
      "logo": "{{ asset('logo.png') }}",
      "description": "Nền tảng cung cấp máy chủ ảo Cloud VPS SSD chuyên dụng (FPT TP.HCM) và Proxy IPv4 tự động tại Việt Nam.",
      "contactPoint": {
        "@@type": "ContactPoint",
        "telephone": "+84-862-579-104",
        "email": "quanpc2004@gmail.com",
        "contactType": "customer service",
        "url": "https://zalo.me/g/bh9hrrcujly5ab2pqmfj"
      }
    }
    </script>

    @if(!empty($seo['json_ld']))
    <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="bg-[#F5F7FB] text-[#0F172A] antialiased selection:bg-[#1D6AE5]/15 selection:text-[#0B1437]">
    <div id="lugia-boot-screen" aria-hidden="true">
        <div id="lugia-boot-inner">
            <img src="/bird-icon.webp" alt="" width="36" height="36" style="width:36px;height:36px;object-fit:contain;" />
            <div id="lugia-boot-bar-track">
                <div id="lugia-boot-bar-fill"></div>
            </div>
        </div>
    </div>
    @inertia
</body>
</html>

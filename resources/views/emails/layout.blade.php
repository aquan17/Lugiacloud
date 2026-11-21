<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Thông báo từ LugiaCloud')</title>
</head>
@php
    $appUrl = rtrim(config('app.url', 'https://lugiacloud.com'), '/');
    $cdnUrl = rtrim((string) config('filesystems.disks.r2.url', ''), '/');
    $logoUrl = $cdnUrl !== '' ? ($cdnUrl . '/brand/logo.png') : ($appUrl . '/logo.png');
@endphp
<body style="margin:0;padding:0;background-color:#F4F6FA;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0F172A;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F6FA;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border-radius:12px;border:1px solid #E2E8F0;overflow:hidden;">
                    <!-- Logo & Brand Header -->
                    <tr>
                        <td style="padding:24px 32px 18px 32px;border-bottom:1px solid #F1F5F9;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="left" valign="middle">
                                        <a href="{{ $appUrl }}" target="_blank" style="text-decoration:none;">
                                            <img src="{{ $logoUrl }}" alt="LugiaCloud" height="32" style="display:block;height:32px;width:auto;border:0;outline:none;text-decoration:none;">
                                        </a>
                                    </td>
                                    <td align="right" valign="middle" style="font-size:12px;color:#64748B;font-weight:600;">
                                        @yield('badge', 'Thông báo hệ thống')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Body -->
                    <tr>
                        <td style="padding:28px 32px 28px 32px;font-size:14px;line-height:1.65;color:#334155;">
                            @yield('content')
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:20px 32px;background-color:#F8FAFC;border-top:1px solid #E2E8F0;font-size:12px;line-height:1.6;color:#64748B;">
                            <p style="margin:0 0 6px 0;color:#475569;font-weight:600;">
                                LugiaCloud — Hạ tầng Cloud VPS SSD &amp; Proxy IPv4 Việt Nam
                            </p>
                            <p style="margin:0 0 6px 0;">
                                Hỗ trợ kỹ thuật 24/7:
                                <a href="tel:+84862579104" style="color:#1D6AE5;text-decoration:none;font-weight:600;">0862 579 104</a>
                                &nbsp;&middot;&nbsp;
                                <a href="https://zalo.me/g/bh9hrrcujly5ab2pqmfj" target="_blank" style="color:#1D6AE5;text-decoration:none;font-weight:600;">Nhóm Zalo Hỗ trợ</a>
                                &nbsp;&middot;&nbsp;
                                <a href="{{ $appUrl }}/dashboard" target="_blank" style="color:#1D6AE5;text-decoration:none;font-weight:600;">Trang quản trị</a>
                            </p>
                            <p style="margin:0;color:#94A3B8;font-size:11px;">
                                Email này được gửi tự động từ hệ thống quản trị dịch vụ LugiaCloud. &copy; {{ date('Y') }} LugiaCloud.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

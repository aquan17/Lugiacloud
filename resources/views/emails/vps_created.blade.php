@extends('emails.layout')

@section('title', 'Thông tin máy chủ ' . $vps->label)
@section('badge', 'Cấp phát Cloud VPS')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Máy chủ {{ $vps->label }} đã sẵn sàng hoạt động
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $vps->user->name ?? 'bạn' }}, máy chủ Cloud VPS của bạn đã hoàn tất cài đặt hệ điều hành và cấp phát địa chỉ IPv4 riêng tại FPT Datacenter (TP.HCM). Dưới đây là thông tin truy cập quản trị:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 20px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:36%;font-size:13px;font-weight:600;color:#475569;">Tên máy chủ</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">{{ $vps->label }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Địa chỉ IPv4</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#1D6AE5;">{{ $vps->public_ip ?? 'Đang cập nhật' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Tài khoản quản trị</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $vps->login_username ?: 'root' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Mật khẩu khởi tạo</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $password }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Cấu hình</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;color:#0F172A;font-weight:600;">{{ $vps->cpu }} vCPU &middot; {{ $vps->ram }} GB RAM &middot; {{ $vps->disk }} GB SSD</td>
        </tr>
        @if($vps->expires_at)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Ngày hết hạn</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;color:#0F172A;font-weight:600;">{{ $vps->expires_at->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
    </table>

    <div style="padding:14px 16px;background-color:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;font-size:13px;color:#92400E;margin:0 0 24px 0;">
        <strong>Khuyến nghị bảo mật:</strong> Bạn nên đổi mật khẩu mặc định sau lần đăng nhập đầu tiên và bật tính năng <strong>Tự động gia hạn</strong> trong trang quản trị để tránh gián đoạn khi đến kỳ thanh toán.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ route('vps.show', $vps->id) }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Mở trang quản lý VPS
                </a>
            </td>
        </tr>
    </table>
@endsection

@extends('emails.layout')

@section('title', 'Thông tin Proxy #' . $proxy->id)
@section('badge', 'Cấp phát Proxy IPv4')

@section('content')
    @php
        $typeProxy = strtoupper($proxy->type_proxy ?? 'HTTP');
        $port = $typeProxy === 'SOCKS5' ? ($proxy->sock5_port ?: $proxy->port) : $proxy->port;
        $userCred = $typeProxy === 'SOCKS5' ? ($proxy->sock5_username ?: $proxy->username) : $proxy->username;
        $passCred = $typeProxy === 'SOCKS5' ? ($proxy->sock5_password ?: $proxy->password) : $proxy->password;
        $connString = ($proxy->ip && $port) ? "{$proxy->ip}:{$port}:{$userCred}:{$passCred}" : null;
    @endphp

    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Proxy IPv4 #{{ $proxy->id }} đã sẵn sàng kết nối
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $proxy->user->name ?? 'bạn' }}, địa chỉ Proxy IPv4 Việt Nam của bạn đã được cấp phát và kích hoạt thành công. Thông tin kết nối chi tiết:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 18px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:36%;font-size:13px;font-weight:600;color:#475569;">Giao thức</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">{{ $typeProxy }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Địa chỉ IP</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#059669;">{{ $proxy->ip }}</td>
        </tr>
        @if($port)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Cổng (Port)</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $port }}</td>
        </tr>
        @endif
        @if($userCred)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Tài khoản</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $userCred }}</td>
        </tr>
        @endif
        @if($passCred)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Mật khẩu</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $passCred }}</td>
        </tr>
        @endif
        @if($proxy->expires_at)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Ngày hết hạn</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:600;color:#0F172A;">{{ \Carbon\Carbon::parse($proxy->expires_at)->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
    </table>

    @if($connString)
    <div style="padding:12px 16px;background-color:#0B1437;border-radius:8px;font-family:Consolas,Menlo,monospace;font-size:13px;color:#E2E8F0;margin:0 0 24px 0;word-break:break-all;">
        {{ $connString }}
    </div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ route('proxy.show', $proxy->id) }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Xem chi tiết &amp; Cấu hình Proxy
                </a>
            </td>
        </tr>
    </table>
@endsection

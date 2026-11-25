@extends('emails.layout')

@section('title', 'Xác thực địa chỉ email tài khoản')
@section('badge', 'Xác thực tài khoản')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Xác thực địa chỉ email của bạn
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $userName ?? 'bạn' }}, cảm ơn bạn đã đăng ký tài khoản tại <strong>LugiaCloud</strong>. Vui lòng nhấn vào nút bên dưới để xác thực địa chỉ email và kích hoạt đầy đủ tính năng quản trị Cloud VPS &amp; Proxy IPv4:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px 0;">
        <tr>
            <td style="border-radius:8px;background-color:#1D6AE5;">
                <a href="{{ $verifyUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Xác thực email ngay
                </a>
            </td>
        </tr>
    </table>

    <div style="padding:14px 16px;background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;font-size:12px;color:#475569;margin:0 0 16px 0;">
        Liên kết xác thực có hiệu lực trong <strong>60 phút</strong>. Nếu bạn không tạo tài khoản này, bạn có thể bỏ qua email này một cách an toàn.
    </div>

    <p style="margin:0;font-size:12px;color:#64748B;word-break:break-all;">
        Nếu nút bấm không hoạt động, hãy sao chép đường dẫn sau vào trình duyệt:<br>
        <span style="color:#1D6AE5;font-family:monospace;">{{ $verifyUrl }}</span>
    </p>
@endsection

@extends('emails.layout')

@section('title', 'Đặt lại mật khẩu tài khoản')
@section('badge', 'Bảo mật tài khoản')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Yêu cầu đặt lại mật khẩu
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $userName ?? 'bạn' }}, hệ thống vừa nhận được yêu cầu đặt lại mật khẩu cho tài khoản <strong>LugiaCloud</strong> gắn với email này. Nhấn nút bên dưới để tạo mật khẩu mới:
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px 0;">
        <tr>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ $resetUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Đặt lại mật khẩu mới
                </a>
            </td>
        </tr>
    </table>

    <div style="padding:14px 16px;background-color:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;font-size:13px;color:#92400E;margin:0 0 16px 0;">
        <strong>Lưu ý bảo mật:</strong> Liên kết này sẽ tự động hết hạn sau <strong>{{ $expireMinutes ?? 60 }} phút</strong>. Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email — mật khẩu hiện tại của bạn vẫn an toàn.
    </div>

    <p style="margin:0;font-size:12px;color:#64748B;word-break:break-all;">
        Nếu nút bấm không hoạt động, hãy sao chép đường dẫn sau vào trình duyệt:<br>
        <span style="color:#1D6AE5;font-family:monospace;">{{ $resetUrl }}</span>
    </p>
@endsection

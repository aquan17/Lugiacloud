@extends('emails.layout')

@section('title', 'Tự động gia hạn thất bại - ' . $serviceName)
@section('badge', 'Cảnh báo số dư')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Không thể tự động gia hạn {{ $serviceName }} do ví chưa đủ số dư
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $userName }}, hệ thống vừa thực hiện lệnh tự động gia hạn trước 24 giờ cho dịch vụ của bạn nhưng số dư ví hiện tại chưa đủ thanh toán:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 20px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:40%;font-size:13px;font-weight:600;color:#475569;">Dịch vụ</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">{{ $serviceName }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Số tiền cần gia hạn</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#DC2626;">{{ number_format($requiredAmount, 0, ',', '.') }}đ</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Số dư hiện có</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ number_format($currentBalance, 0, ',', '.') }}đ</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Thời điểm hết hạn</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#B45309;">{{ $expiresAt }}</td>
        </tr>
    </table>

    <div style="padding:14px 16px;background-color:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:13px;color:#991B1B;margin:0 0 24px 0;">
        Bạn vui lòng nạp thêm tối thiểu <strong>{{ number_format(max(0, $requiredAmount - $currentBalance), 0, ',', '.') }}đ</strong> vào ví trước thời điểm hết hạn. Hệ thống sẽ tự động quét và gia hạn lại vào giờ tiếp theo ngay khi ví đủ tiền.
    </div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#1D6AE5;">
                <a href="{{ route('topup.index') }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Nạp tiền qua VietQR ngay
                </a>
            </td>
        </tr>
    </table>
@endsection

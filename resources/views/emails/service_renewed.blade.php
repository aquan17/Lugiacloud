@extends('emails.layout')

@section('title', 'Xác nhận gia hạn ' . $serviceName)
@section('badge', $isAuto ? 'Tự động gia hạn' : 'Xác nhận gia hạn')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        {{ $isAuto ? 'Đã tự động gia hạn' : 'Gia hạn thành công' }} {{ $serviceName }}
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $userName }}, dịch vụ {{ $serviceType === 'vps' ? 'Cloud VPS' : 'Proxy IPv4' }} của bạn đã được gia hạn thành công và tiếp tục hoạt động bình thường không gián đoạn:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 22px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:40%;font-size:13px;font-weight:600;color:#475569;">Dịch vụ</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">{{ $serviceName }}</td>
        </tr>
        @if($ipAddress)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Địa chỉ IP</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $ipAddress }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Chu kỳ cộng thêm</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#0F172A;">{{ $cycleLabel }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Số tiền thanh toán</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0B1437;">{{ number_format($amountPaid, 0, ',', '.') }}đ</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Ngày hết hạn mới</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#059669;">{{ $newExpiresAt }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ $manageUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Xem chi tiết dịch vụ
                </a>
            </td>
        </tr>
    </table>
@endsection

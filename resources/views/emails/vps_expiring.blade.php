@extends('emails.layout')

@section('title', 'Nhắc gia hạn VPS ' . $vps->label)
@section('badge', 'Nhắc hạn dịch vụ')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Máy chủ {{ $vps->label }} còn {{ $daysLeft }} ngày sử dụng
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $vps->user->name ?? 'bạn' }}, máy chủ Cloud VPS dưới đây sắp đến kỳ thanh toán tiếp theo. Bạn vui lòng kiểm tra và gia hạn kịp thời để đảm bảo dịch vụ hoạt động liên tục:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 20px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:36%;font-size:13px;font-weight:600;color:#475569;">Tên máy chủ</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">{{ $vps->label }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Địa chỉ IPv4</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $vps->public_ip ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Thời điểm hết hạn</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#B45309;">{{ $vps->expires_at?->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Tự động gia hạn</td>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:{{ $vps->is_auto_renew ? '#047857' : '#64748B' }};">
                {{ $vps->is_auto_renew ? 'Đang bật (tự động trừ ví trước 24h)' : 'Đang tắt' }}
            </td>
        </tr>
    </table>

    @if($vps->is_auto_renew)
        <div style="padding:14px 16px;background-color:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;font-size:13px;color:#065F46;margin:0 0 24px 0;">
            Dịch vụ này đang bật <strong>Tự động gia hạn</strong>. Hệ thống sẽ tự trích số dư ví để gia hạn trước thời điểm hết hạn <strong>24 giờ</strong>. Bạn chỉ cần đảm bảo số dư ví đủ thanh toán.
        </div>
    @else
        <div style="padding:14px 16px;background-color:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:13px;color:#991B1B;margin:0 0 24px 0;">
            <strong>Chính sách quá hạn:</strong> Sau thời điểm hết hạn, máy chủ sẽ bị tạm ngưng kết nối. LugiaCloud lưu giữ dữ liệu tối đa <strong>05 ngày</strong> để bạn gia hạn lại; quá thời hạn này hệ thống tự thu hồi tài nguyên và không thể khôi phục dữ liệu.
        </div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#1D6AE5;">
                <a href="{{ route('vps.show', $vps->id) }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Gia hạn máy chủ ngay
                </a>
            </td>
        </tr>
    </table>
@endsection

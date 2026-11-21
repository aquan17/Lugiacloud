@extends('emails.layout')

@section('title', 'Nhắc gia hạn Proxy #' . $proxy->id)
@section('badge', 'Nhắc hạn Proxy')

@section('content')
    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Proxy #{{ $proxy->id }} còn {{ $daysRemaining }} ngày sử dụng
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $proxy->user->name ?? 'bạn' }}, địa chỉ Proxy IPv4 dưới đây sắp hết hạn sử dụng. Vui lòng kiểm tra và gia hạn sớm nếu bạn muốn giữ nguyên IP đang dùng:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 20px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:36%;font-size:13px;font-weight:600;color:#475569;">Mã dịch vụ</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:700;color:#0B1437;">Proxy #{{ $proxy->id }} ({{ strtoupper($proxy->type_proxy ?: 'HTTP') }})</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Địa chỉ IP:Port</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ $proxy->ip }}:{{ $proxy->port }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Thời điểm hết hạn</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#B45309;">{{ \Carbon\Carbon::parse($proxy->expires_at)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Tự động gia hạn</td>
            <td style="padding:11px 16px;font-size:13px;font-weight:700;color:{{ $proxy->is_auto_renew ? '#047857' : '#64748B' }};">
                {{ $proxy->is_auto_renew ? 'Đang bật (tự động trừ ví trước 24h)' : 'Đang tắt' }}
            </td>
        </tr>
    </table>

    @if($proxy->is_auto_renew)
        <div style="padding:14px 16px;background-color:#ECFDF5;border:1px solid #A7F3D0;border-radius:8px;font-size:13px;color:#065F46;margin:0 0 24px 0;">
            Proxy này đang bật <strong>Tự động gia hạn</strong>. Hệ thống sẽ tự động gia hạn trước thời điểm hết hạn <strong>24 giờ</strong> nếu ví của bạn đủ số dư.
        </div>
    @else
        <div style="padding:14px 16px;background-color:#FEF2F2;border:1px solid #FECACA;border-radius:8px;font-size:13px;color:#991B1B;margin:0 0 24px 0;">
            <strong>Lưu ý:</strong> Sau khi hết hạn, IP Proxy sẽ bị thu hồi về kho và không thể cấp lại đúng địa chỉ IP cũ. Bạn nên gia hạn trước thời điểm trên hoặc bật Tự động gia hạn.
        </div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#1D6AE5;">
                <a href="{{ route('proxy.show', $proxy->id) }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Gia hạn Proxy ngay
                </a>
            </td>
        </tr>
    </table>
@endsection

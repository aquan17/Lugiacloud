@extends('emails.layout')

@section('title', 'Xác nhận nạp tiền ' . $topup->code)
@section('badge', 'Biên nhận nạp tiền')

@section('content')
    @php
        $baseAmount = (int) ($topup->approved_amount ?: $topup->amount);
        $bonusAmount = (int) ($topup->bonus_amount ?? 0);
        $totalCredited = $baseAmount + $bonusAmount;
    @endphp

    <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0B1437;font-weight:700;">
        Nạp tiền thành công +{{ number_format($totalCredited, 0, ',', '.') }}đ
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $user->name ?? 'bạn' }}, hệ thống đã ghi nhận khoản chuyển khoản của bạn và cộng trực tiếp vào số dư ví LugiaCloud. Chi tiết biên nhận:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 22px 0;">
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:40%;font-size:13px;font-weight:600;color:#475569;">Mã giao dịch</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0B1437;">{{ $topup->code }}</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Số tiền nạp</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#0F172A;">{{ number_format($baseAmount, 0, ',', '.') }}đ</td>
        </tr>
        @if($bonusAmount > 0)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Thưởng sự kiện</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#059669;">+{{ number_format($bonusAmount, 0, ',', '.') }}đ</td>
        </tr>
        @endif
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#475569;">Số dư ví hiện tại</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:14px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#1D6AE5;">{{ number_format((int) $user->balance, 0, ',', '.') }}đ</td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Thời gian ghi nhận</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;color:#0F172A;">{{ ($topup->paid_at ?? now())->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ route('billing.index') }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Xem sổ cái &amp; Hóa đơn
                </a>
            </td>
        </tr>
    </table>
@endsection

@extends('emails.layout')

@section('title', $campaign->name)
@section('badge', 'Chương trình ưu đãi')

@section('content')
    @php
        $scopeMap = [
            'all' => 'Cloud VPS SSD & Proxy IPv4',
            'vps' => 'Cloud VPS SSD',
            'proxy' => 'Proxy IPv4 Việt Nam',
            'upgrade' => 'Nâng cấp phần cứng CPU / RAM / SSD',
        ];
        $scopeLabel = $scopeMap[$campaign->target_scope] ?? 'Toàn bộ dịch vụ';
        $hasServiceDiscount = $campaign->discount_type !== 'none' && (int) $campaign->discount_value > 0;
        $hasTopupBonus = (float) $campaign->topup_bonus_percent > 0;
        $ctaUrl = $campaign->target_scope === 'upgrade'
            ? route('vps.index')
            : (($hasTopupBonus && !$hasServiceDiscount) ? route('topup.index') : route('store.index'));
        $ctaText = $campaign->target_scope === 'upgrade'
            ? 'Nâng cấp cấu hình VPS ngay'
            : (($hasTopupBonus && !$hasServiceDiscount) ? 'Nạp ví nhận thưởng ngay' : 'Xem bảng giá & Nhận ưu đãi');
    @endphp

    @if($campaign->badge_text)
        <div style="margin:0 0 12px 0;">
            <span style="display:inline-block;padding:4px 10px;border-radius:6px;background-color:#EFF6FF;color:#1D6AE5;font-size:11px;font-weight:700;letter-spacing:0.4px;text-transform:uppercase;">
                {{ $campaign->badge_text }}
            </span>
        </div>
    @endif

    <h1 style="margin:0 0 12px 0;font-size:21px;line-height:1.35;color:#0B1437;font-weight:700;">
        {{ $campaign->name }}
    </h1>

    <p style="margin:0 0 18px 0;">
        Chào {{ $userName }}, chương trình ưu đãi đặc biệt của LugiaCloud đã chính thức mở áp dụng trên hệ thống.
        @if($campaign->description)
            {{ $campaign->description }}
        @endif
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E2E8F0;border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 20px 0;">
        @if($hasServiceDiscount)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:38%;font-size:13px;font-weight:600;color:#475569;">Ưu đãi giảm giá</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:14px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#1D6AE5;">
                @if($campaign->discount_type === 'percent')
                    Giảm {{ (int) $campaign->discount_value }}%
                @else
                    Giảm {{ number_format((int) $campaign->discount_value, 0, ',', '.') }}đ
                @endif
                @if($campaign->max_discount_amount)
                    <span style="font-family:-apple-system,sans-serif;font-size:12px;color:#64748B;font-weight:500;">(tối đa {{ number_format((int) $campaign->max_discount_amount, 0, ',', '.') }}đ)</span>
                @endif
            </td>
        </tr>
        @endif
        @if($hasTopupBonus)
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:38%;font-size:13px;font-weight:600;color:#475569;">Thưởng nạp ví</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:14px;font-family:Consolas,Menlo,monospace;font-weight:700;color:#059669;">
                +{{ rtrim(rtrim(number_format((float) $campaign->topup_bonus_percent, 2, '.', ''), '0'), '.') }}% giá trị nạp VietQR / PayOS
            </td>
        </tr>
        @endif
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;border-bottom:1px solid #E2E8F0;width:38%;font-size:13px;font-weight:600;color:#475569;">Phạm vi áp dụng</td>
            <td style="padding:11px 16px;border-bottom:1px solid #E2E8F0;font-size:13px;font-weight:600;color:#0F172A;">
                {{ $scopeLabel }}@if($campaign->apply_on_renew) (gồm cả gia hạn &amp; nâng cấp)@endif
            </td>
        </tr>
        <tr>
            <td style="padding:11px 16px;background-color:#F8FAFC;font-size:13px;font-weight:600;color:#475569;">Thời gian hiệu lực</td>
            <td style="padding:11px 16px;font-size:13px;font-family:Consolas,Menlo,monospace;font-weight:600;color:#0F172A;">
                {{ $campaign->starts_at?->format('d/m/Y H:i') ?: 'Ngay hôm nay' }} &rarr; {{ $campaign->ends_at?->format('d/m/Y H:i') ?: 'Đến khi hết lượt' }}
            </td>
        </tr>
    </table>

    @if(!empty($couponCodes))
        <div style="padding:14px 16px;background-color:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin:0 0 22px 0;">
            <div style="font-size:12px;font-weight:600;color:#475569;margin:0 0 6px 0;">Mã ưu đãi đi kèm sự kiện (nhập khi thanh toán):</div>
            <div style="font-family:Consolas,Menlo,monospace;font-size:15px;font-weight:700;color:#0B1437;letter-spacing:1px;">
                {{ implode('   ·   ', $couponCodes) }}
            </div>
        </div>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="border-radius:8px;background-color:#1D6AE5;">
                <a href="{{ $ctaUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    {{ $ctaText }}
                </a>
            </td>
            @if($hasServiceDiscount && $hasTopupBonus)
            <td style="width:10px;"></td>
            <td style="border-radius:8px;background-color:#0B1437;">
                <a href="{{ route('topup.index') }}" target="_blank" style="display:inline-block;padding:12px 20px;font-size:13px;font-weight:700;color:#FFFFFF;text-decoration:none;border-radius:8px;">
                    Nạp ví nhận +{{ rtrim(rtrim(number_format((float) $campaign->topup_bonus_percent, 2, '.', ''), '0'), '.') }}%
                </a>
            </td>
            @endif
        </tr>
    </table>
@endsection

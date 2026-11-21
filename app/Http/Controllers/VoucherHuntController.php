<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\UserVoucher;
use App\Services\FlashSaleService;
use App\Services\WelcomeOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class VoucherHuntController extends Controller
{
    /**
     * Trang Ví Voucher cá nhân: xem toàn bộ voucher đang sở hữu (Khả dụng / Đã dùng / Hết hạn) và săn thêm voucher mới.
     */
    public function index(FlashSaleService $flashSales, WelcomeOfferService $welcomeOffer)
    {
        $user = Auth::user();
        $hasOrderedBefore = $welcomeOffer->hasCompletedServiceOrder($user);
        $now = now();

        $rows = UserVoucher::with('coupon')
            ->where('user_id', $user->id)
            ->orderByDesc('claimed_at')
            ->orderByDesc('id')
            ->get();

        $vouchers = [];
        $counts = ['available' => 0, 'used' => 0, 'expired' => 0];

        foreach ($rows as $uv) {
            $c = $uv->coupon;
            if (!$c) {
                continue;
            }

            $effectiveExpiry = $uv->expires_at ?: $c->ends_at;
            $isUsed = $uv->used_at !== null;
            $isExpiredByTime = $effectiveExpiry && $effectiveExpiry->isPast();
            $isDepleted = $c->max_uses !== null && $c->used_count >= $c->max_uses;
            $isBlockedFirstOrder = !empty($c->first_order_only) && $hasOrderedBefore;
            $isInactive = !$c->is_active;

            if ($isUsed) {
                $status = 'used';
                $statusReason = 'Đã sử dụng ngày ' . $uv->used_at->format('d/m/Y H:i');
            } elseif ($isExpiredByTime || $isInactive || $isDepleted || $isBlockedFirstOrder) {
                $status = 'expired';
                $statusReason = $isBlockedFirstOrder
                    ? 'Chỉ áp dụng cho đơn đầu tiên'
                    : ($isExpiredByTime
                        ? 'Đã hết hạn sử dụng'
                        : ($isDepleted ? 'Mã đã hết lượt áp dụng' : 'Chương trình đã kết thúc'));
            } else {
                $status = 'available';
                $statusReason = $c->starts_at && $c->starts_at->isFuture()
                    ? 'Có hiệu lực từ ' . $c->starts_at->format('H:i d/m/Y')
                    : 'Sẵn sàng áp dụng khi thanh toán';
            }

            $counts[$status]++;

            $scopeLabel = match ($c->applies_to) {
                'vps' => 'Cloud VPS',
                'proxy' => 'Proxy IPv4',
                default => 'VPS & Proxy',
            };

            $vouchers[] = [
                'id' => $uv->id,
                'coupon_id' => $c->id,
                'code' => $c->code,
                'description' => $c->description ?: ($c->discount_type === 'percent' ? "Giảm {$c->discount_value}% giá trị đơn hàng" : 'Giảm trực tiếp ' . number_format($c->discount_value, 0, ',', '.') . 'đ'),
                'discount_label' => $c->discount_type === 'percent'
                    ? "Giảm {$c->discount_value}%"
                    : ('Giảm ' . number_format($c->discount_value, 0, ',', '.') . 'đ'),
                'applies_to' => $c->applies_to ?: 'all',
                'scope_label' => $scopeLabel,
                'apply_on_renew' => (bool) $c->apply_on_renew,
                'min_order_amount' => (int) $c->min_order_amount,
                'max_discount_amount' => $c->max_discount_amount ? (int) $c->max_discount_amount : null,
                'max_months' => $c->max_months ? (int) $c->max_months : null,
                'is_welcome' => (bool) ($c->first_order_only ?? false),
                'status' => $status,
                'status_reason' => $statusReason,
                'claimed_at' => $uv->claimed_at?->format('d/m/Y H:i'),
                'used_at' => $uv->used_at?->format('d/m/Y H:i'),
                'expires_at' => $effectiveExpiry?->format('d/m/Y H:i'),
                'is_upcoming' => (bool) ($c->starts_at && $c->starts_at->isFuture() && $status === 'available'),
            ];
        }

        $huntable = array_values(array_filter(
            $flashSales->getHuntableVouchers($user),
            static fn (array $v) => empty($v['is_claimed']) && empty($v['is_out_of_stock'])
        ));

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
            'counts' => $counts,
            'huntableVouchers' => $huntable,
        ]);
    }

    /**
     * Săn & lưu Voucher vào ví cá nhân (khóa dòng lockForUpdate chống săn vượt hạn mức).
     */
    public function claim(Request $request, Coupon $coupon, FlashSaleService $flashSales)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập để lưu mã ưu đãi vào ví.');
        }

        try {
            $claimedCoupon = DB::transaction(function () use ($coupon, $user) {
                $locked = Coupon::whereKey($coupon->id)->lockForUpdate()->first();

                if (!$locked || !$locked->is_active || !$locked->is_huntable) {
                    throw new RuntimeException('Mã ưu đãi này hiện không còn trong chương trình săn voucher.');
                }

                if ($locked->starts_at && $locked->starts_at->isFuture()) {
                    throw new RuntimeException('Mã ưu đãi này chưa đến giờ mở săn.');
                }

                if ($locked->ends_at && $locked->ends_at->isPast()) {
                    throw new RuntimeException('Mã ưu đãi này đã hết thời hạn săn.');
                }

                $already = UserVoucher::where('user_id', $user->id)
                    ->where('coupon_id', $locked->id)
                    ->exists();
                if ($already) {
                    throw new RuntimeException("Bạn đã lưu mã {$locked->code} vào ví rồi.");
                }

                $limit = $locked->claim_limit ?: $locked->max_uses;
                if ($limit !== null && $locked->claimed_count >= $limit) {
                    throw new RuntimeException("Rất tiếc, mã {$locked->code} vừa hết lượt săn!");
                }

                UserVoucher::create([
                    'user_id' => $user->id,
                    'coupon_id' => $locked->id,
                    'claimed_at' => now(),
                ]);

                $locked->increment('claimed_count');

                return $locked;
            });

            $flashSales->flushCache();

            $msg = "Đã lưu mã {$claimedCoupon->code} vào ví của bạn! Khi mua dịch vụ chỉ cần bấm chọn mã để giảm ngay.";
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['ok' => true, 'message' => $msg, 'code' => $claimedCoupon->code]);
            }

            return back()->with('success', $msg);
        } catch (RuntimeException $e) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}

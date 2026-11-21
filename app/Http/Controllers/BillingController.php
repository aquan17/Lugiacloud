<?php

namespace App\Http\Controllers;

use App\Models\TopupRequest;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $topups = TopupRequest::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'approved'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (TopupRequest $r) {
                $amount = (int) ($r->approved_amount ?? $r->amount);
                return [
                    'id' => 'topup_' . $r->id,
                    'category' => 'topup',
                    'is_credit' => true,
                    'title' => 'Nạp tiền vào ví',
                    'code' => $r->code,
                    'amount' => $amount,
                    'description' => 'Cổng thanh toán ' . $this->providerLabel($r->provider),
                    'at_iso' => ($r->paid_at ?? $r->created_at)?->toIso8601String(),
                    'at_formatted' => ($r->paid_at ?? $r->created_at)?->format('d/m/Y H:i'),
                    'timestamp' => ($r->paid_at ?? $r->created_at)?->timestamp ?? 0,
                    'status' => 'completed',
                    'invoice_url' => route('invoices.show', $r->code),
                ];
            })
            ->toBase();

        $transactions = Transaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Transaction $t) {
                $rawAmount = abs((int) $t->amount);
                $isCredit = in_array($t->type, ['refund', 'affiliate_bonus', 'topup_bonus', 'admin_add'], true);
                $signedAmount = $isCredit ? $rawAmount : -$rawAmount;
                $code = $t->code ?: ('#' . $t->id);

                return [
                    'id' => 'txn_' . $t->id,
                    'category' => $t->type,
                    'is_credit' => $isCredit,
                    'title' => $this->transactionTitle($t),
                    'code' => $code,
                    'amount' => $signedAmount,
                    'discount_amount' => (int) $t->discount_amount,
                    'coupon_code' => $t->coupon_code,
                    'description' => $t->description ?: $this->transactionTitle($t),
                    'at_iso' => $t->created_at?->toIso8601String(),
                    'at_formatted' => $t->created_at?->format('d/m/Y H:i'),
                    'timestamp' => $t->created_at?->timestamp ?? 0,
                    'status' => $t->status ?: 'completed',
                    'balance_before' => $t->balance_before,
                    'balance_after' => $t->balance_after,
                    'invoice_url' => route('invoices.show', $code),
                ];
            })
            ->toBase();

        $timeline = $topups->merge($transactions)
            ->sortByDesc('timestamp')
            ->values();

        $currentBalance = (int) $user->balance;
        $running = $currentBalance;

        $timeline = $timeline->map(function ($item) use (&$running) {
            if (!isset($item['balance_after']) || $item['balance_after'] === null) {
                $balanceAfter = $running;
                $balanceBefore = $running - $item['amount'];
                $item['balance_after'] = $balanceAfter;
                $item['balance_before'] = max(0, $balanceBefore);
                $running = $balanceBefore;
            } else {
                $running = (int) $item['balance_before'];
            }
            return $item;
        });

        $totalTopup = (int) $topups->sum('amount');
        $totalSpend = (int) $transactions
            ->filter(fn ($t) => !$t['is_credit'] && $t['status'] !== 'refunded')
            ->sum(fn ($t) => abs($t['amount']));

        return Inertia::render('Billing/Index', [
            'timeline' => $timeline,
            'summary' => [
                'total_topup' => $totalTopup,
                'total_spend' => $totalSpend,
                'current_balance' => $currentBalance,
            ],
        ]);
    }

    private function providerLabel(?string $provider): string
    {
        return [
            'payos' => 'PayOS (VietQR)',
            'manual' => 'Chuyển khoản ngân hàng',
        ][strtolower((string) $provider)] ?? ucfirst((string) ($provider ?: 'VietQR'));
    }

    private function transactionTitle(Transaction $t): string
    {
        return match (strtolower((string) $t->type)) {
            'admin_deduct' => 'Điều chỉnh giảm số dư',
            'admin_add' => 'Điều chỉnh tăng số dư',
            'renew' => $t->dedicated_server_id ? 'Gia hạn Máy chủ vật lý' : ($t->proxy_instance_id ? 'Gia hạn Proxy' : 'Gia hạn VPS'),
            'upgrade' => 'Nâng cấp tài nguyên VPS',
            'change_ip' => 'Đổi địa chỉ IPv4 VPS',
            'buy_server' => 'Đặt thuê Máy chủ vật lý',
            'refund' => 'Hoàn tiền tự động',
            'affiliate_bonus' => 'Hoa hồng giới thiệu (F1)',
            'topup_bonus' => 'Thưởng sự kiện nạp tiền',
            default => $t->dedicated_server_id ? 'Đặt thuê Máy chủ vật lý' : ($t->proxy_instance_id ? 'Khởi tạo Proxy mới' : 'Khởi tạo dịch vụ mới'),
        };
    }
}

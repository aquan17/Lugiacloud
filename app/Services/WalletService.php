<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /**
     * Trừ tiền ví nguyên tử (có lockForUpdate + chống trừ đúp bằng idempotency_key).
     */
    public function debit(User $user, int $amount, array $meta = []): Transaction
    {
        $amount = max(0, $amount);
        $idempotencyKey = $meta['idempotency_key'] ?? null;

        return DB::transaction(function () use ($user, $amount, $meta, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = Transaction::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->balance < $amount) {
                throw new RuntimeException('INSUFFICIENT_BALANCE');
            }

            $before = (int) $lockedUser->balance;
            $after = $before - $amount;

            $lockedUser->balance = $after;
            $lockedUser->save();

            $txn = Transaction::create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $lockedUser->id,
                'vps_instance_id' => $meta['vps_instance_id'] ?? null,
                'proxy_instance_id' => $meta['proxy_instance_id'] ?? null,
                'dedicated_server_id' => $meta['dedicated_server_id'] ?? null,
                'campaign_id' => $meta['campaign_id'] ?? null,
                'coupon_code' => $meta['coupon_code'] ?? null,
                'type' => $meta['type'] ?? 'buy',
                'status' => $meta['status'] ?? 'completed',
                'amount' => $amount,
                'provider_cost' => (int) ($meta['provider_cost'] ?? 0),
                'discount_amount' => (int) ($meta['discount_amount'] ?? 0),
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $meta['description'] ?? 'Thanh toán dịch vụ',
            ]);

            if (in_array($txn->type, ['buy', 'buy_vps', 'buy_proxy', 'buy_server'], true)) {
                app(WelcomeOfferService::class)->forgetUserCache((int) $lockedUser->id);
            }

            return $txn;
        });
    }

    /**
     * Cộng tiền ví nguyên tử (nạp tiền, thưởng sự kiện, hoa hồng affiliate, admin cộng).
     */
    public function credit(User $user, int $amount, array $meta = []): Transaction
    {
        $amount = max(0, $amount);
        $idempotencyKey = $meta['idempotency_key'] ?? null;

        return DB::transaction(function () use ($user, $amount, $meta, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = Transaction::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            $before = (int) $lockedUser->balance;
            $after = $before + $amount;

            $lockedUser->balance = $after;
            $lockedUser->save();

            return Transaction::create([
                'idempotency_key' => $idempotencyKey,
                'user_id' => $lockedUser->id,
                'vps_instance_id' => $meta['vps_instance_id'] ?? null,
                'proxy_instance_id' => $meta['proxy_instance_id'] ?? null,
                'dedicated_server_id' => $meta['dedicated_server_id'] ?? null,
                'campaign_id' => $meta['campaign_id'] ?? null,
                'coupon_code' => $meta['coupon_code'] ?? null,
                'type' => $meta['type'] ?? 'admin_add',
                'status' => 'completed',
                'amount' => $amount,
                'provider_cost' => 0,
                'discount_amount' => 0,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $meta['description'] ?? 'Cộng số dư ví',
            ]);
        });
    }

    /**
     * Hoàn tiền an toàn đúng 1 lần duy nhất khi API đối tác lỗi; đánh dấu giao dịch gốc là 'refunded'
     * để không bao giờ tính nhầm vào báo cáo Doanh thu & Lợi nhuận của Admin.
     */
    public function refundTransaction(Transaction $originalTxn, string $reason = 'Lỗi cấp phát dịch vụ'): ?Transaction
    {
        return DB::transaction(function () use ($originalTxn, $reason) {
            $lockedTxn = Transaction::where('id', $originalTxn->id)->lockForUpdate()->first();
            if (!$lockedTxn || $lockedTxn->status === 'refunded') {
                return null;
            }

            $refundKey = "refund_txn_{$lockedTxn->id}";
            $existingRefund = Transaction::where('idempotency_key', $refundKey)->first();
            if ($existingRefund) {
                $lockedTxn->update(['status' => 'refunded']);
                return $existingRefund;
            }

            $lockedUser = User::where('id', $lockedTxn->user_id)->lockForUpdate()->firstOrFail();
            $refundAmount = abs((int) $lockedTxn->amount);

            $before = (int) $lockedUser->balance;
            $after = $before + $refundAmount;

            $lockedUser->balance = $after;
            $lockedUser->save();

            $lockedTxn->update([
                'status' => 'refunded',
                'description' => $lockedTxn->description . ' [Đã hoàn tiền]',
            ]);

            $refund = Transaction::create([
                'idempotency_key' => $refundKey,
                'user_id' => $lockedUser->id,
                'vps_instance_id' => $lockedTxn->vps_instance_id,
                'proxy_instance_id' => $lockedTxn->proxy_instance_id,
                'dedicated_server_id' => $lockedTxn->dedicated_server_id,
                'type' => 'refund',
                'status' => 'completed',
                'amount' => $refundAmount,
                'provider_cost' => 0,
                'discount_amount' => 0,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => "Hoàn tiền cho mã {$lockedTxn->code}: {$reason}",
            ]);

            app(PromotionEngine::class)->releaseCouponByTransaction((int) $lockedTxn->id);
            app(WelcomeOfferService::class)->forgetUserCache((int) $lockedUser->id);

            return $refund;
        });
    }
}

<?php

namespace App\Services;

use App\Models\AffiliateLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AffiliateService
{
    public const COMMISSION_RATE = 0.05; // 5%

    public function __construct(
        private WalletService $wallet
    ) {
    }

    public function processCommission(User $buyer, $amount, string $type, ?string $idempotencySuffix = null): void
    {
        if (!$buyer->referred_by || $amount <= 0) {
            return;
        }

        $referrer = User::find($buyer->referred_by);
        if (!$referrer) {
            return;
        }

        $commission = (int) round(((float) $amount) * self::COMMISSION_RATE);
        if ($commission <= 0) {
            return;
        }

        DB::transaction(function () use ($referrer, $buyer, $amount, $commission, $type, $idempotencySuffix) {
            $idemKey = $idempotencySuffix ? "aff_{$referrer->id}_{$idempotencySuffix}" : null;

            $txn = $this->wallet->credit($referrer, $commission, [
                'idempotency_key' => $idemKey,
                'type' => 'affiliate_bonus',
                'description' => "Hoa hồng giới thiệu 5% từ {$buyer->email}",
            ]);

            if ($txn->wasRecentlyCreated) {
                AffiliateLog::create([
                    'user_id' => $referrer->id,
                    'buyer_id' => $buyer->id,
                    'transaction_type' => $type,
                    'amount' => (int) $amount,
                    'commission' => $commission,
                ]);
            }
        });
    }
}

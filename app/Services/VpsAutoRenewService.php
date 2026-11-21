<?php

namespace App\Services;

use App\Mail\ServiceAutoRenewFailed;
use App\Mail\ServiceRenewed;
use App\Models\VpsInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class VpsAutoRenewService
{
    public function __construct(
        protected CloudSunnyApiService $apiService,
        protected CloudSunnyPricingService $pricingService,
        protected WalletService $wallet
    ) {
    }

    public function processAutoRenewal(VpsInstance $vps): bool
    {
        $lock = Cache::lock("vps_autorenew_{$vps->id}", 90);
        if (!$lock->get()) {
            return false;
        }

        try {
            $vps->refresh();
            if (!$vps->is_auto_renew || in_array($vps->status, ['Đã xóa', 'Đã xoá', 'Hết hạn'], true)) {
                return false;
            }
            if (!$vps->expires_at || $vps->expires_at->isPast() || $vps->expires_at->gt(Carbon::now()->addDay())) {
                return false;
            }

            $user = $vps->user;

            if (!$user || !$vps->provider_vps_id || !$vps->cloudSunnyAccount) {
                Log::warning("Auto renew VPS {$vps->id} skipped: Missing provider info.");
                return false;
            }

            $plan = $this->pricingService->getPlan((string) $vps->plan_id, $user);
            if (!$plan) {
                Log::warning("Auto renew VPS {$vps->id} skipped: Plan {$vps->plan_id} not found.");
                return false;
            }

            $allowedCycles = array_keys(config('cloudsunny.billing_cycles', ['monthly' => []]));
            $billingCycle = in_array($vps->billing_cycle, $allowedCycles, true) ? $vps->billing_cycle : 'monthly';
            $months = $this->pricingService->monthsForCycle($billingCycle);
            $cycleLabel = config("cloudsunny.billing_cycles.{$billingCycle}.label") ?? "{$months} Tháng";

            $addonCpu = max(0, (int) $vps->cpu - (int) ($plan['cores'] ?? $vps->cpu));
            $addonRam = max(0, (int) $vps->ram - (int) ($plan['ram'] ?? $vps->ram));
            $addonDisk = max(0, (int) $vps->disk - (int) ($plan['disk'] ?? $vps->disk));

            $quote = $this->pricingService->quoteCycleForPlan(
                $plan,
                $billingCycle,
                $user,
                $addonCpu,
                $addonRam,
                $addonDisk,
                true
            );

            $amount = (int) $quote['final_price'];
            $providerCost = (int) $quote['provider_cost'];

            if ($amount <= 0) {
                return false;
            }

            $idempotencyKey = "autorenew_vps_{$vps->id}_" . $vps->expires_at->format('Ymd');
            if (\App\Models\Transaction::where('idempotency_key', $idempotencyKey)->exists()) {
                return false;
            }

            try {
                $transaction = $this->wallet->debit($user, $amount, [
                    'idempotency_key' => $idempotencyKey,
                    'vps_instance_id' => $vps->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'type' => 'renew',
                    'provider_cost' => $providerCost,
                    'discount_amount' => (int) ($quote['discount_amount'] ?? 0),
                    'description' => "Tự động gia hạn VPS: {$vps->label} ({$cycleLabel})",
                ]);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                    Log::info("Auto renew VPS {$vps->id} skipped: User {$user->id} insufficient balance.");
                    $this->notifyInsufficientBalance($vps, $amount);
                }
                return false;
            }

            try {
                $this->apiService->forAccount($vps->cloudSunnyAccount)->renewVps((int) $vps->provider_vps_id, $billingCycle);

                $baseDate = $vps->expires_at && $vps->expires_at->isFuture()
                    ? $vps->expires_at->copy()
                    : Carbon::now();
                $newExpiresAt = $baseDate->addMonths($months);

                $vps->update([
                    'billing_cycle' => $billingCycle,
                    'paid_amount' => (int) $vps->paid_amount + $amount,
                    'provider_cost' => (int) $vps->provider_cost + $providerCost,
                    'expires_at' => $newExpiresAt,
                    'status' => $vps->status === 'Hết hạn' ? 'Sẵn sàng' : $vps->status,
                ]);

                app(AffiliateService::class)->processCommission($user, $amount, 'renew_vps', "txn_{$transaction->id}");

                if (!empty($user->email)) {
                    try {
                        Mail::to($user->email)->queue(new ServiceRenewed(
                            userName: $user->name ?: 'bạn',
                            serviceType: 'vps',
                            serviceName: $vps->label,
                            ipAddress: $vps->public_ip,
                            cycleLabel: $cycleLabel,
                            amountPaid: $amount,
                            newExpiresAt: $newExpiresAt->format('d/m/Y H:i'),
                            manageUrl: route('vps.show', $vps->id),
                            isAuto: true
                        ));
                    } catch (\Throwable $mailErr) {
                        Log::error("Auto renew VPS {$vps->id} mail failed: " . $mailErr->getMessage());
                    }
                }

                return true;
            } catch (\Throwable $e) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
                Log::error("API Provider Auto renew VPS {$vps->id} failed: " . $e->getMessage());
                return false;
            }
        } finally {
            $lock->release();
        }
    }

    private function notifyInsufficientBalance(VpsInstance $vps, int $requiredAmount): void
    {
        $user = $vps->user;
        if (!$user || empty($user->email)) {
            return;
        }

        // Chỉ gửi tối đa 1 email cảnh báo thiếu số dư mỗi 24h cho cùng 1 VPS
        $cacheKey = "vps_autorenew_low_bal_{$vps->id}_" . now()->format('Y-m-d');
        if (!Cache::add($cacheKey, true, 86400)) {
            return;
        }

        try {
            Mail::to($user->email)->queue(new ServiceAutoRenewFailed(
                userName: $user->name ?: 'bạn',
                serviceName: "VPS {$vps->label}",
                requiredAmount: $requiredAmount,
                currentBalance: (int) $user->balance,
                expiresAt: $vps->expires_at?->format('d/m/Y H:i') ?: '—'
            ));
        } catch (\Throwable $e) {
            Log::error("Failed to queue auto-renew low balance email for VPS {$vps->id}: " . $e->getMessage());
        }
    }
}

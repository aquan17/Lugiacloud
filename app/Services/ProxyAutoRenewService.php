<?php

namespace App\Services;

use App\Mail\ServiceAutoRenewFailed;
use App\Mail\ServiceRenewed;
use App\Models\ProxyInstance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class ProxyAutoRenewService
{
    public function __construct(
        protected CloudSunnyApiService $apiService,
        protected CloudSunnyProxyPricingService $pricingService,
        protected WalletService $wallet
    ) {
    }

    public function processAutoRenewal(ProxyInstance $proxy): bool
    {
        $lock = Cache::lock("proxy_autorenew_{$proxy->id}", 90);
        if (!$lock->get()) {
            return false;
        }

        try {
            $proxy->refresh();
            if (!$proxy->is_auto_renew || in_array($proxy->status, ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã hủy', 'Đang khởi tạo', 'Đang cấp phát'], true)) {
                return false;
            }
            if (!$proxy->expires_at || $proxy->expires_at->isPast() || $proxy->expires_at->gt(Carbon::now()->addDay())) {
                return false;
            }

            $user = $proxy->user;

            if (!$user || !$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
                Log::warning("Auto renew proxy {$proxy->id} failed: Missing provider info.");
                return false;
            }

            $billingCycle = 'monthly';
            $product = $this->pricingService->findProductById((int) $proxy->product_id, $user);

            if (!$product) {
                Log::warning("Auto renew proxy {$proxy->id}: Product {$proxy->product_id} not in catalog.");
                return false;
            }

            $quote = $this->pricingService->quoteProxyOrder($product, 1, $billingCycle, $user, true);
            $pricePerCycle = $quote['final_price'];
            $providerCost = $quote['provider_cost'];

            if ($pricePerCycle <= 0) {
                return false;
            }

            $idempotencyKey = "autorenew_proxy_{$proxy->id}_" . $proxy->expires_at->format('Ymd');
            if (\App\Models\Transaction::where('idempotency_key', $idempotencyKey)->exists()) {
                return false;
            }

            try {
                $transaction = $this->wallet->debit($user, $pricePerCycle, [
                    'idempotency_key' => $idempotencyKey,
                    'proxy_instance_id' => $proxy->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'type' => 'renew',
                    'provider_cost' => $providerCost,
                    'discount_amount' => $quote['discount_amount'],
                    'description' => "Tự động gia hạn Proxy #{$proxy->id} ({$billingCycle})",
                ]);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                    Log::info("Auto renew proxy {$proxy->id} skipped: User {$user->id} insufficient balance.");
                    if (!empty($user->email)) {
                        $cacheKey = "proxy_autorenew_low_bal_{$proxy->id}_" . now()->format('Y-m-d');
                        if (Cache::add($cacheKey, true, 86400)) {
                            try {
                                Mail::to($user->email)->queue(new ServiceAutoRenewFailed(
                                    userName: $user->name ?: 'bạn',
                                    serviceName: "Proxy #{$proxy->id} ({$proxy->ip})",
                                    requiredAmount: (int) $pricePerCycle,
                                    currentBalance: (int) $user->balance,
                                    expiresAt: $proxy->expires_at?->format('d/m/Y H:i') ?: '—'
                                ));
                            } catch (\Throwable $mailErr) {
                                Log::error("Auto renew proxy {$proxy->id} low-balance mail failed: " . $mailErr->getMessage());
                            }
                        }
                    }
                }
                return false;
            }

            try {
                $apiClient = $this->apiService->forAccount($proxy->cloudSunnyAccount);
                $apiClient->actionProxy([(int) $proxy->provider_proxy_id], 'renew', [
                    'billing_cycle' => $billingCycle,
                ]);

                $remote = $apiClient->getProxy((int) $proxy->provider_proxy_id);
                $monthsMeta = (int) config('cloudsunny.billing_cycles.' . $billingCycle . '.months', 1);
                $baseDate = $proxy->expires_at && $proxy->expires_at->isFuture()
                    ? $proxy->expires_at->copy()
                    : Carbon::now();

                $expiresAt = $baseDate->addMonths($monthsMeta);
                if ($remote) {
                    foreach (['next_due_date', 'expires_at', 'expired_at', 'expire_at', 'expiration_at', 'end_at', 'end_date', 'ngay_het_han'] as $key) {
                        if (!empty($remote[$key])) {
                            try {
                                $expiresAt = Carbon::parse($remote[$key]);
                                break;
                            } catch (\Throwable $err) {
                            }
                        }
                    }
                }

                $proxy->update([
                    'expires_at' => $expiresAt,
                    'paid_amount' => (int) $proxy->paid_amount + $pricePerCycle,
                    'billing_cycle' => $billingCycle,
                    'status' => 'Hoạt động',
                ]);

                app(AffiliateService::class)->processCommission($user, $pricePerCycle, 'renew_proxy', "txn_{$transaction->id}");

                if (!empty($user->email)) {
                    try {
                        Mail::to($user->email)->queue(new ServiceRenewed(
                            userName: $user->name ?: 'bạn',
                            serviceType: 'proxy',
                            serviceName: "Proxy #{$proxy->id}",
                            ipAddress: $proxy->ip ? "{$proxy->ip}:{$proxy->port}" : null,
                            cycleLabel: '1 Tháng',
                            amountPaid: (int) $pricePerCycle,
                            newExpiresAt: $expiresAt->format('d/m/Y H:i'),
                            manageUrl: route('proxy.show', $proxy->id),
                            isAuto: true
                        ));
                    } catch (\Throwable $mailErr) {
                        Log::error("Auto renew proxy {$proxy->id} receipt mail failed: " . $mailErr->getMessage());
                    }
                }

                return true;
            } catch (\Throwable $e) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
                Log::error("API Provider Auto renew proxy {$proxy->id} failed: " . $e->getMessage());
                return false;
            }
        } finally {
            $lock->release();
        }
    }
}

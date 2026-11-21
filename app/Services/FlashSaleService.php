<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\FlashSaleClaim;
use App\Models\PricingCatalog;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FlashSaleService
{
    public const CACHE_KEY = 'lugiacloud.flash_sales.public_v1';
    public const LIVE_MODELS_CACHE_KEY = 'lugiacloud.flash_sales.live_models_v1';
    public const VOUCHER_HUNT_CACHE_KEY = 'lugiacloud.vouchers.hunt_v1';

    private ?array $liveDealsMemo = null;
    private array $userClaimsMemo = [];

    public function flushCache(): void
    {
        $this->liveDealsMemo = null;
        $this->userClaimsMemo = [];
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LIVE_MODELS_CACHE_KEY);
        Cache::forget(self::VOUCHER_HUNT_CACHE_KEY);
    }

    /**
     * Lấy toàn bộ Flash Sale đang diễn ra hoặc sắp mở trong 24h tới (để hiển thị ngoài Web & Store).
     */
    public function getPublicDeals(?User $user = null): array
    {
        $rawDeals = Cache::remember(self::CACHE_KEY, 30, function () {
            if (!Schema::hasTable('flash_sales') || !Schema::hasTable('pricing_catalogs')) {
                return [];
            }

            $now = now();
            $deals = FlashSale::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now->copy()->addHours(24)))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->orderByRaw('CASE WHEN starts_at IS NULL OR starts_at <= ? THEN 0 ELSE 1 END', [$now])
                ->orderBy('ends_at')
                ->orderByDesc('id')
                ->get();

            if ($deals->isEmpty()) {
                return [];
            }

            $catalogMap = PricingCatalog::whereIn('slug', $deals->pluck('plan_slug')->unique()->all())
                ->get()
                ->keyBy('slug');

            return $deals->map(function (FlashSale $deal) use ($catalogMap) {
                $cat = $catalogMap->get($deal->plan_slug);
                if (!$cat) {
                    return null;
                }

                $listPrice = $cat->effectiveMonthlyRetail();
                $cost = (int) $cat->provider_cost_monthly;
                $salePrice = $this->computeUnitSalePrice($deal, $listPrice, $cost, (float) ($cat->min_margin_percent ?? 5.0));
                $discAmount = max(0, $listPrice - $salePrice);
                $discPct = $listPrice > 0 ? (int) round(($discAmount / $listPrice) * 100) : 0;
                $specs = $cat->specs ?? [];

                $specSummary = $deal->service_type === 'vps'
                    ? sprintf('%d vCPU · %d GB RAM · %d GB SSD', (int) ($specs['cores'] ?? 1), (int) ($specs['ram'] ?? 1), (int) ($specs['disk'] ?? 20))
                    : (($specs['category'] ?? 'datacenter') === 'residential' ? 'IP Dân cư Việt Nam' : 'IP Datacenter 1Gbps')
                        . (!empty($specs['dedicated']) ? ' · Riêng 1:1' : ' · Shared');

                $buyUrl = $deal->service_type === 'vps'
                    ? "/store/{$deal->plan_slug}"
                    : ('/proxy/store/' . (int) ($cat->provider_product_id ?: $cat->id));

                return [
                    'id' => $deal->id,
                    'name' => $deal->name,
                    'badge_text' => $deal->badge_text ?: "FLASH SALE -{$discPct}%",
                    'service_type' => $deal->service_type,
                    'plan_slug' => $deal->plan_slug,
                    'plan_name' => $cat->name,
                    'spec_summary' => $specSummary,
                    'product_id' => (int) ($cat->provider_product_id ?: $cat->id),
                    'list_price' => $listPrice,
                    'sale_price' => $salePrice,
                    'discount_amount' => $discAmount,
                    'discount_percent' => $discPct,
                    'total_slots' => (int) $deal->total_slots,
                    'sold_slots' => (int) $deal->sold_slots,
                    'remaining_slots' => $deal->remainingSlots(),
                    'max_per_user' => (int) $deal->max_per_user,
                    'max_months' => (int) $deal->max_months,
                    'is_live' => $deal->isLiveNow(),
                    'is_upcoming' => $deal->isUpcoming(),
                    'is_sold_out' => $deal->remainingSlots() <= 0,
                    'starts_at' => $deal->starts_at?->toIso8601String(),
                    'ends_at' => $deal->ends_at?->toIso8601String(),
                    'starts_display' => $deal->starts_at?->format('H:i d/m'),
                    'buy_url' => $buyUrl,
                ];
            })->filter()->values()->all();
        });

        if (!$user || empty($rawDeals)) {
            return $rawDeals;
        }

        $userClaims = $this->getUserClaimsMap($user, array_column($rawDeals, 'id'));

        return array_map(function (array $d) use ($userClaims) {
            $used = (int) ($userClaims[$d['id']] ?? 0);
            $d['user_used_slots'] = $used;
            $d['user_reached_limit'] = $used >= $d['max_per_user'];
            return $d;
        }, $rawDeals);
    }

    /**
     * Batch-load & memoize per-user Flash Sale claim counts within the request.
     *
     * @param  array<int>  $dealIds
     * @return array<int, int>
     */
    private function getUserClaimsMap(User $user, array $dealIds): array
    {
        if (empty($dealIds)) {
            return [];
        }

        if (!array_key_exists($user->id, $this->userClaimsMemo)) {
            $this->userClaimsMemo[$user->id] = [];
        }

        $missing = array_values(array_diff(array_map('intval', $dealIds), array_keys($this->userClaimsMemo[$user->id])));
        if (!empty($missing)) {
            $fetched = FlashSaleClaim::where('user_id', $user->id)
                ->whereIn('flash_sale_id', $missing)
                ->selectRaw('flash_sale_id, SUM(quantity) as used_qty')
                ->groupBy('flash_sale_id')
                ->pluck('used_qty', 'flash_sale_id')
                ->all();

            foreach ($missing as $id) {
                $this->userClaimsMemo[$user->id][$id] = (int) ($fetched[$id] ?? 0);
            }
        }

        return $this->userClaimsMemo[$user->id];
    }

    private function hasUserVouchersTable(): bool
    {
        return Cache::remember('lugiacloud.schema.has_user_vouchers', 86400, fn () => Schema::hasTable('user_vouchers'));
    }

    /**
     * Tìm suất Flash Sale đang mở áp dụng cho 1 đơn hàng cụ thể (chỉ mua mới, đúng gói, đủ suất, không vượt chu kỳ tối đa).
     */
    public function findActiveDealForItem(
        string $serviceType,
        string $planSlug,
        int $months,
        ?User $user = null,
        int $quantity = 1,
        bool $isRenew = false,
        bool $isUpgrade = false
    ): ?FlashSale {
        if ($isRenew || $isUpgrade || $planSlug === '') {
            return null;
        }

        if ($this->liveDealsMemo === null) {
            $this->liveDealsMemo = Cache::remember(self::LIVE_MODELS_CACHE_KEY, 30, function () {
                if (!Schema::hasTable('flash_sales')) {
                    return [];
                }
                return FlashSale::liveNow()->get()->all();
            });
        }

        if (empty($this->liveDealsMemo)) {
            return null;
        }

        $userClaimsMap = null;
        if ($user) {
            $dealIds = array_map(fn (FlashSale $d) => $d->id, $this->liveDealsMemo);
            $userClaimsMap = $this->getUserClaimsMap($user, $dealIds);
        }

        foreach ($this->liveDealsMemo as $deal) {
            if ($deal->service_type !== $serviceType || $deal->plan_slug !== $planSlug) {
                continue;
            }
            if ($months > (int) $deal->max_months) {
                continue;
            }
            if ($deal->remainingSlots() < $quantity) {
                continue;
            }
            if ($user) {
                $userUsed = (int) ($userClaimsMap[$deal->id] ?? 0);
                if (($userUsed + $quantity) > (int) $deal->max_per_user) {
                    continue;
                }
            } elseif ($quantity > (int) $deal->max_per_user) {
                continue;
            }

            return $deal;
        }

        return null;
    }

    public function computeRawDiscount(FlashSale $deal, int $listPrice): int
    {
        if ($listPrice <= 0) {
            return 0;
        }

        if ($deal->discount_type === 'fixed_price') {
            return max(0, $listPrice - (int) $deal->discount_value);
        }

        if ($deal->discount_type === 'fixed') {
            return min($listPrice, max(0, (int) $deal->discount_value));
        }

        $pct = min(90, max(0, (int) $deal->discount_value));

        return (int) round($listPrice * ($pct / 100));
    }

    public function computeUnitSalePrice(FlashSale $deal, int $listPrice, int $providerCost, float $minMarginPercent = 5.0): int
    {
        $raw = max(0, $listPrice - $this->computeRawDiscount($deal, $listPrice));
        if ($deal->protect_cost_floor && $providerCost > 0) {
            $floor = (int) ceil($providerCost * (1 + max(0, $minMarginPercent) / 100));
            return max($raw, min($floor, $listPrice));
        }

        return $raw;
    }

    /**
     * Khóa và trừ suất Flash Sale nguyên tử (lockForUpdate — tuyệt đối không bao giờ bán lố suất).
     */
    public function reserveAndConsumeSlot(
        int $flashSaleId,
        User $user,
        int $quantity,
        ?int $transactionId,
        int $discountAmount
    ): FlashSaleClaim {
        return DB::transaction(function () use ($flashSaleId, $user, $quantity, $transactionId, $discountAmount) {
            $deal = FlashSale::whereKey($flashSaleId)->lockForUpdate()->first();
            if (!$deal || !$deal->isLiveNow()) {
                throw new RuntimeException('Chương trình Flash Sale này vừa kết thúc.');
            }

            if (($deal->sold_slots + $quantity) > $deal->total_slots) {
                throw new RuntimeException('Rất tiếc, suất Flash Sale vừa được săn hết! Vui lòng tải lại trang để cập nhật giá mới.');
            }

            $userUsed = (int) FlashSaleClaim::where('flash_sale_id', $deal->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->sum('quantity');

            if (($userUsed + $quantity) > (int) $deal->max_per_user) {
                throw new RuntimeException("Mỗi tài khoản chỉ được săn tối đa {$deal->max_per_user} suất cho Flash Sale này.");
            }

            $deal->increment('sold_slots', $quantity);

            $claim = FlashSaleClaim::create([
                'flash_sale_id' => $deal->id,
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'quantity' => $quantity,
                'discount_amount' => max(0, $discountAmount),
            ]);

            $this->flushCache();

            return $claim;
        });
    }

    /**
     * Hoàn lại suất Flash Sale nếu khởi tạo máy chủ/proxy lỗi và đơn bị hoàn tiền.
     */
    public function releaseSlotByTransaction(?int $transactionId): void
    {
        if (!$transactionId || !Schema::hasTable('flash_sale_claims')) {
            return;
        }

        DB::transaction(function () use ($transactionId) {
            $claim = FlashSaleClaim::where('transaction_id', $transactionId)->lockForUpdate()->first();
            if (!$claim) {
                return;
            }

            $deal = FlashSale::whereKey($claim->flash_sale_id)->lockForUpdate()->first();
            if ($deal && $deal->sold_slots >= $claim->quantity) {
                $deal->decrement('sold_slots', $claim->quantity);
            }

            $claim->delete();
            $this->flushCache();
        });
    }

    /**
     * Danh sách Voucher đang mở săn (Kho Săn Voucher) kèm trạng thái đã lưu của user.
     */
    public function getHuntableVouchers(?User $user = null): array
    {
        $rawVouchers = Cache::remember(self::VOUCHER_HUNT_CACHE_KEY, 30, function () {
            if (!Schema::hasTable('coupons') || !Schema::hasColumn('coupons', 'is_huntable')) {
                return [];
            }

            return Coupon::activeNow()
                ->where('is_huntable', true)
                ->orderByDesc('id')
                ->get()
                ->map(function (Coupon $c) {
                    $limit = $c->claim_limit ? (int) $c->claim_limit : ($c->max_uses ? (int) $c->max_uses : null);
                    $claimed = (int) $c->claimed_count;
                    $remaining = $limit !== null ? max(0, $limit - $claimed) : null;

                    $label = $c->discount_type === 'percent'
                        ? "Giảm {$c->discount_value}%"
                        : ('Giảm ' . number_format($c->discount_value, 0, ',', '.') . 'đ');

                    return [
                        'id' => $c->id,
                        'code' => $c->code,
                        'description' => $c->description ?: $label,
                        'discount_label' => $label,
                        'discount_type' => $c->discount_type,
                        'discount_value' => (int) $c->discount_value,
                        'max_discount_amount' => $c->max_discount_amount ? (int) $c->max_discount_amount : null,
                        'min_order_amount' => (int) $c->min_order_amount,
                        'applies_to' => $c->applies_to,
                        'claim_limit' => $limit,
                        'claimed_count' => $claimed,
                        'remaining_claims' => $remaining,
                        'is_out_of_stock' => $remaining !== null && $remaining <= 0,
                        'ends_at' => $c->ends_at?->format('d/m/Y H:i'),
                    ];
                })
                ->values()
                ->all();
        });

        if (!$user || empty($rawVouchers) || !$this->hasUserVouchersTable()) {
            return $rawVouchers;
        }

        $claimedIds = UserVoucher::where('user_id', $user->id)
            ->whereIn('coupon_id', array_column($rawVouchers, 'id'))
            ->pluck('used_at', 'coupon_id');

        return array_map(function (array $v) use ($claimedIds) {
            $v['is_claimed'] = $claimedIds->has($v['id']);
            $v['is_used'] = $claimedIds->has($v['id']) && $claimedIds->get($v['id']) !== null;
            return $v;
        }, $rawVouchers);
    }

    /**
     * Danh sách Voucher user đã săn thành công và còn hiệu lực (để bấm chọn 1-click ở trang Mua VPS / Proxy).
     */
    public function getUserClaimedVouchers(?User $user, string $serviceType = 'all'): array
    {
        if (!$user || !$this->hasUserVouchersTable()) {
            return [];
        }

        $now = now();
        $hasOrderedBefore = null;

        return UserVoucher::with('coupon')
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->orderByDesc('claimed_at')
            ->get()
            ->filter(function (UserVoucher $uv) use ($now, $serviceType, $user, &$hasOrderedBefore) {
                $c = $uv->coupon;
                if (!$c || !$c->is_active) {
                    return false;
                }
                if ($uv->expires_at && $uv->expires_at->isPast()) {
                    return false;
                }
                if ($c->starts_at && $c->starts_at->isFuture()) {
                    return false;
                }
                if ($c->ends_at && $c->ends_at->isPast()) {
                    return false;
                }
                if ($c->max_uses !== null && $c->used_count >= $c->max_uses) {
                    return false;
                }
                if ($serviceType !== 'all' && $c->applies_to !== 'all' && $c->applies_to !== $serviceType) {
                    return false;
                }
                if (!empty($c->first_order_only)) {
                    if ($hasOrderedBefore === null) {
                        $hasOrderedBefore = app(WelcomeOfferService::class)->hasCompletedServiceOrder($user);
                    }
                    if ($hasOrderedBefore) {
                        return false;
                    }
                }
                return true;
            })
            ->map(function (UserVoucher $uv) {
                $c = $uv->coupon;
                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'discount_label' => $c->discount_type === 'percent'
                        ? "-{$c->discount_value}%"
                        : ('-' . number_format($c->discount_value, 0, ',', '.') . 'đ'),
                    'min_order_amount' => (int) $c->min_order_amount,
                    'max_discount_amount' => $c->max_discount_amount ? (int) $c->max_discount_amount : null,
                    'max_months' => $c->max_months ? (int) $c->max_months : null,
                    'is_welcome' => (bool) ($c->first_order_only ?? false),
                    'expires_display' => $uv->expires_at?->format('d/m/Y'),
                    'description' => $c->description,
                ];
            })
            ->values()
            ->all();
    }
}

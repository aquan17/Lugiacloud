<?php

namespace App\Services;

use App\Models\PricingCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CloudSunnyProxyPricingService
{
    public const CACHE_KEY = 'lugiacloud.pricing.proxy_catalog_v2';

    public function __construct(
        private PromotionEngine $promotions
    ) {
    }

    /**
     * Lấy danh sách gói Proxy từ bảng pricing_catalogs (tốc độ cao, luôn áp dụng Markup an toàn + Sự kiện giảm giá).
     */
    public function getCatalogProducts(?User $user = null): array
    {
        app(CloudSunnyPricingService::class)->ensureCatalogBootstrapped();

        $user = $user ?? Auth::user();

        $baseRows = Cache::remember(self::CACHE_KEY, 300, function () {
            if (!Schema::hasTable('pricing_catalogs')) {
                return [];
            }

            return PricingCatalog::where('service_type', 'proxy')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(function (PricingCatalog $row) {
                    $specs = $row->specs ?? [];
                    return [
                        'catalog_id' => $row->id,
                        'id' => (int) ($row->provider_product_id ?: $row->id),
                        'slug' => $row->slug,
                        'title' => $row->name,
                        'description' => $row->description,
                        'badge' => $row->badge,
                        'is_featured' => (bool) $row->is_featured,
                        'category' => $specs['category'] ?? 'datacenter', // residential | datacenter
                        'dedicated' => (bool) ($specs['dedicated'] ?? false),
                        'protocols' => $specs['protocols'] ?? ['HTTP', 'SOCKS5'],
                        'country' => $specs['country'] ?? 'VN',
                        'bandwidth' => $specs['bandwidth'] ?? 'Unlimited',
                        'list_price' => $row->effectiveMonthlyRetail(),
                        'provider_price' => (int) $row->provider_cost_monthly,
                        'provider_cycle_costs' => $row->provider_cycle_costs ?? [],
                        'retail_cycle_prices' => $row->retail_cycle_prices ?? [],
                        'protect_cost_floor' => (bool) $row->protect_cost_floor,
                        'min_margin_percent' => (float) $row->min_margin_percent,
                    ];
                })
                ->all();
        });

        $proxyCycleKeys = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $allCycles = config('cloudsunny.billing_cycles', []);

        return collect($baseRows)->map(function (array $product) use ($user, $proxyCycleKeys, $allCycles) {
            $quote = $this->quoteProxyOrder($product, 1, 'monthly', $user);
            $product['price'] = $quote['final_price'];
            $product['has_discount'] = $quote['discount_amount'] > 0;
            $product['discount_amount'] = $quote['discount_amount'];
            $product['campaign'] = $quote['campaign'];
            $product['flash_sale'] = $quote['flash_sale'] ?? null;

            $cycleQuotes = [];
            $dataPricing = [];
            foreach ($proxyCycleKeys as $cycleKey) {
                $meta = $allCycles[$cycleKey] ?? null;
                if (!$meta) {
                    continue;
                }
                $cq = $this->quoteProxyOrder($product, 1, $cycleKey, $user);
                $dataPricing[$cycleKey] = $cq['final_price'];
                $cycleQuotes[$cycleKey] = [
                    'cycle' => $cycleKey,
                    'months' => (int) ($meta['months'] ?? 1),
                    'label' => $meta['label'] ?? $cycleKey,
                    'cycle_discount_percent' => (int) ($meta['discount_percent'] ?? 0),
                    'list_price' => $cq['list_price'],
                    'final_price' => $cq['final_price'],
                    'discount_amount' => $cq['discount_amount'],
                    'campaign' => $cq['campaign'],
                    'flash_sale' => $cq['flash_sale'] ?? null,
                ];
            }
            $product['data_pricing'] = $dataPricing;
            $product['cycle_quotes'] = $cycleQuotes;

            return $product;
        })->values()->all();
    }

    /**
     * Xuất danh sách gói Proxy ra trình duyệt (ẩn tuyệt đối giá vốn gốc).
     */
    public function getPublicCatalogProducts(?User $user = null): array
    {
        return array_map(function (array $p) {
            unset($p['provider_price'], $p['provider_cycle_costs']);
            return $p;
        }, $this->getCatalogProducts($user));
    }

    public function findProductById(int $productId, ?User $user = null): ?array
    {
        foreach ($this->getCatalogProducts($user) as $p) {
            if ((int) $p['id'] === $productId || (int) ($p['catalog_id'] ?? 0) === $productId) {
                return $p;
            }
        }

        return null;
    }

    public function quoteProxyOrder(
        array $product,
        int $quantity = 1,
        string $billingCycle = 'monthly',
        ?User $user = null,
        bool $isRenew = false,
        ?string $couponCode = null
    ): array {
        $months = (int) (config("cloudsunny.billing_cycles.{$billingCycle}.months") ?? 1);
        $cycleDiscountPercent = (int) (config("cloudsunny.billing_cycles.{$billingCycle}.discount_percent") ?? 0);
        $qty = max(1, $quantity);
        $retailOverrides = $product['retail_cycle_prices'] ?? [];
        if (isset($retailOverrides[$billingCycle]) && (int) $retailOverrides[$billingCycle] > 0) {
            $unitList = (int) $retailOverrides[$billingCycle];
        } else {
            $unitList = (int) ($product['list_price'] ?? $product['price'] ?? 0) * $months;
            if ($cycleDiscountPercent > 0 && $cycleDiscountPercent <= 100) {
                $unitList = (int) round($unitList * (1 - ($cycleDiscountPercent / 100)));
            }
        }

        $providerCycleCosts = $product['provider_cycle_costs'] ?? [];
        if (isset($providerCycleCosts[$billingCycle]) && (int) $providerCycleCosts[$billingCycle] > 0) {
            $unitCost = (int) $providerCycleCosts[$billingCycle];
        } else {
            $unitCost = (int) ($product['provider_price'] ?? 0) * $months;
        }

        return $this->promotions->quoteOrder([
            'service_type' => 'proxy',
            'plan_slug' => $product['slug'] ?? ('proxy-' . ($product['id'] ?? 0)),
            'list_price' => $unitList * $qty,
            'base_plan_list_price' => $unitList * $qty,
            'provider_cost' => $unitCost * $qty,
            'months' => $months,
            'quantity' => $qty,
            'is_renew' => $isRenew,
            'user' => $user ?? Auth::user(),
            'coupon_code' => $couponCode,
            'protect_cost_floor' => $product['protect_cost_floor'] ?? true,
            'min_margin_percent' => $product['min_margin_percent'] ?? 5.0,
        ]);
    }

    public function enrichProducts(array $products): array
    {
        // Giữ tương thích ngược nếu nơi nào truyền mảng thô từ API gốc
        $catalogById = collect($this->getCatalogProducts())->keyBy('id');

        return collect($products)->map(function (array $raw) use ($catalogById) {
            $pid = (int) ($raw['id'] ?? 0);
            if ($catalogById->has($pid)) {
                return array_merge($raw, $catalogById->get($pid));
            }

            // Nếu gói lạ chưa có trong catalog: bắt buộc cộng markup +35% thay vì bán bằng giá gốc!
            $wholesale = (int) ($raw['data_pricing']['monthly'] ?? $raw['amount'] ?? 0);
            $safeRetail = (int) (ceil(($wholesale * 1.35) / 1000) * 1000);

            $raw['list_price'] = $safeRetail;
            $raw['price'] = $this->applyUserDiscount($safeRetail);
            $raw['provider_price'] = $wholesale;
            $raw['data_pricing']['monthly'] = $raw['price'];
            $raw['title'] = $this->nameFor($raw);

            return $raw;
        })->values()->all();
    }

    public function priceFor(array $product, string $billingCycle = 'monthly'): int
    {
        $pid = (int) ($product['id'] ?? 0);
        $cat = $this->findProductById($pid);
        if ($cat) {
            return $this->quoteProxyOrder($cat, 1, $billingCycle)['final_price'];
        }

        $wholesale = (int) ($product['data_pricing'][$billingCycle] ?? 0);
        $safeRetail = (int) (ceil(($wholesale * 1.35) / 1000) * 1000);

        return max($wholesale, $this->applyUserDiscount($safeRetail));
    }

    public function providerCostFor(array $product, string $billingCycle = 'monthly'): int
    {
        $pid = (int) ($product['id'] ?? 0);
        $cat = $this->findProductById($pid);
        if ($cat) {
            $months = (int) (config("cloudsunny.billing_cycles.{$billingCycle}.months") ?? 1);
            return (int) $cat['provider_price'] * $months;
        }

        return (int) ($product['data_pricing'][$billingCycle] ?? 0);
    }

    public function applyUserDiscount(int $amount, ?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if ($user && $user->proxy_discount_percent > 0) {
            $discount = min(100, max(0, (int) $user->proxy_discount_percent));
            return (int) round($amount * (1 - ($discount / 100)));
        }

        return $amount;
    }

    public function nameFor(array $product): string
    {
        $pid = (int) ($product['id'] ?? 0);
        $cat = $this->findProductById($pid);

        return $cat['title']
            ?? config("cloudsunny.proxy_plans.{$pid}.name")
            ?? (string) ($product['title'] ?? ('Proxy #' . $pid));
    }
}

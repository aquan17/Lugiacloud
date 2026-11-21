<?php

namespace App\Services;

use App\Models\DedicatedServer;
use App\Models\PricingCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CloudSunnyDedicatedPricingService
{
    public const CACHE_KEY = 'lugiacloud.dedicated.catalog_v1';

    public const CYCLE_META = [
        'monthly' => ['label' => '1 Tháng', 'months' => 1],
        'twomonthly' => ['label' => '2 Tháng', 'months' => 2],
        'quarterly' => ['label' => '3 Tháng', 'months' => 3],
        'semi_annually' => ['label' => '6 Tháng', 'months' => 6],
        'annually' => ['label' => '1 Năm', 'months' => 12],
        'biennially' => ['label' => '2 Năm', 'months' => 24],
        'triennially' => ['label' => '3 Năm', 'months' => 36],
    ];

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Lấy toàn bộ gói Máy chủ vật lý đang mở bán từ bảng giá Admin (pricing_catalogs: service_type = 'server').
     */
    public function getCatalogServers(?User $user = null): array
    {
        $rows = Cache::remember(self::CACHE_KEY, 180, function () {
            if (!Schema::hasTable('pricing_catalogs')) {
                return [];
            }

            $catalogs = PricingCatalog::where('service_type', 'server')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('provider_cost_monthly')
                ->get();

            return $catalogs->map(fn (PricingCatalog $c) => $this->formatCatalogItem($c))->values()->all();
        });

        $discountPct = $user ? max(0, min(50, (int) $user->discount_percent)) : 0;

        return array_map(function (array $item) use ($discountPct) {
            $cycles = [];
            foreach ($item['cycles'] as $cycleKey => $cyc) {
                $retail = (int) $cyc['retail'];
                $cost = (int) $cyc['cost'];
                $floor = (int) ceil($cost * (1 + max(0, (float) $item['min_margin_percent']) / 100));
                $finalRetail = $retail;
                if ($discountPct > 0) {
                    $discounted = (int) round($retail * (1 - $discountPct / 100));
                    $finalRetail = $item['protect_cost_floor'] ? max($floor, $discounted) : $discounted;
                }

                $cycles[$cycleKey] = array_merge($cyc, [
                    'final_price' => $finalRetail,
                    'discount_amount' => max(0, $retail - $finalRetail),
                ]);
            }

            $item['cycles'] = $cycles;
            $item['monthly_price'] = $cycles['monthly']['final_price'] ?? $item['retail_monthly'];
            $item['monthly_list_price'] = $cycles['monthly']['retail'] ?? $item['retail_monthly'];

            return $item;
        }, $rows);
    }

    public function findBySlug(string $slug, ?User $user = null): ?array
    {
        foreach ($this->getCatalogServers($user) as $item) {
            if ($item['slug'] === $slug || (string) $item['provider_product_id'] === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function findByProductId(int $productId, ?User $user = null): ?array
    {
        foreach ($this->getCatalogServers($user) as $item) {
            if ((int) $item['provider_product_id'] === $productId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Tính bảng giá cho tất cả các chu kỳ của 1 Server (dùng cho mua mới & gia hạn).
     */
    public function computeCycleQuote(array $serverProduct, string $cycle, int $quantity = 1, bool $isRenew = false): ?array
    {
        $cyc = $serverProduct['cycles'][$cycle] ?? null;
        if (!$cyc) {
            return null;
        }

        $qty = max(1, min(10, $quantity));
        $unitFinal = (int) ($cyc['final_price'] ?? $cyc['retail']);
        $unitList = (int) $cyc['retail'];
        $unitCost = (int) $cyc['cost'];
        $setupFee = $isRenew ? 0 : (int) ($serverProduct['setup_fee'] ?? 0);
        $providerSetupFee = $isRenew ? 0 : (int) ($serverProduct['provider_setup_fee'] ?? 0);

        return [
            'cycle' => $cycle,
            'cycle_label' => $cyc['label'],
            'months' => (int) $cyc['months'],
            'quantity' => $qty,
            'unit_list_price' => $unitList,
            'unit_final_price' => $unitFinal,
            'unit_cost' => $unitCost,
            'setup_fee' => $setupFee * $qty,
            'provider_setup_fee' => $providerSetupFee * $qty,
            'subtotal' => $unitList * $qty,
            'discount_amount' => max(0, ($unitList - $unitFinal) * $qty),
            'total_price' => ($unitFinal * $qty) + ($setupFee * $qty),
            'total_provider_cost' => ($unitCost * $qty) + ($providerSetupFee * $qty),
        ];
    }

    private function formatCatalogItem(PricingCatalog $c): array
    {
        $specs = is_array($c->specs) ? $c->specs : [];
        $cycleCosts = is_array($c->provider_cycle_costs) ? $c->provider_cycle_costs : [];
        $costMonthly = max(1, (int) $c->provider_cost_monthly);
        $retailMonthly = $c->effectiveMonthlyRetail();
        $ratio = $costMonthly > 0 ? ($retailMonthly / $costMonthly) : 1.18;

        $providerSetupFee = max(0, (int) ($specs['setup_fee'] ?? 0));
        $setupFee = $providerSetupFee; // Phí khởi tạo thu theo cấu hình gốc (không giảm theo chiết khấu)

        $cycles = [];
        foreach (self::CYCLE_META as $key => $meta) {
            $rawCost = (int) ($cycleCosts[$key] ?? 0);
            if ($key === 'monthly' && $rawCost <= 0) {
                $rawCost = $costMonthly;
            }
            if ($rawCost <= 0) {
                continue;
            }

            if ($key === 'monthly') {
                $retail = $retailMonthly;
            } else {
                // Giữ đúng tỷ lệ biên lãi so với chu kỳ gốc của CloudSunny (bao gồm ưu đãi dài kỳ của hãng)
                $computed = (int) (ceil(($rawCost * $ratio) / 1000) * 1000);
                $fullNoDiscount = $retailMonthly * $meta['months'];
                $retail = min($fullNoDiscount, max($rawCost, $computed));
            }

            $baseNoDisc = $retailMonthly * $meta['months'];
            $savePct = $baseNoDisc > 0 && $retail < $baseNoDisc
                ? (int) round((($baseNoDisc - $retail) / $baseNoDisc) * 100)
                : 0;

            $cycles[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'months' => $meta['months'],
                'cost' => $rawCost,
                'retail' => $retail,
                'save_percent' => $savePct,
            ];
        }

        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'provider_product_id' => (int) $c->provider_product_id,
            'name' => $c->name,
            'description' => $c->description,
            'badge' => $c->badge,
            'is_featured' => (bool) $c->is_featured,
            'provider_cost_monthly' => $costMonthly,
            'retail_monthly' => $retailMonthly,
            'setup_fee' => $setupFee,
            'provider_setup_fee' => $providerSetupFee,
            'protect_cost_floor' => (bool) $c->protect_cost_floor,
            'min_margin_percent' => (float) ($c->min_margin_percent ?? 5.0),
            'config' => [
                'cpu_model' => (string) ($specs['cpu_model'] ?? 'Intel Xeon Enterprise'),
                'cpu_cores' => $specs['cpu_cores'] ?? 0,
                'cpu_threads' => $specs['cpu_threads'] ?? 0,
                'ram_gb' => $specs['ram_gb'] ?? 0,
                'ram_type' => (string) ($specs['ram_type'] ?? 'DDR4 ECC'),
                'storage' => (string) ($specs['storage'] ?? 'SSD Enterprise'),
                'raid' => (string) ($specs['raid'] ?? 'Non-RAID'),
                'port_speed' => (string) ($specs['port_speed'] ?? '1 Gbps'),
                'traffic' => (string) ($specs['traffic'] ?? 'Không giới hạn'),
                'datacenter' => (string) ($specs['datacenter'] ?? 'HCM / HN'),
                'ip_count' => $specs['ip_count'] ?? 1,
                'gpu_model' => (string) ($specs['gpu_model'] ?? ''),
                'ipmi' => (bool) ($specs['ipmi'] ?? false),
                'note' => (string) ($specs['note'] ?? ''),
            ],
            'cycles' => $cycles,
        ];
    }
}

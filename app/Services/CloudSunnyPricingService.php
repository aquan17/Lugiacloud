<?php

namespace App\Services;

use App\Models\CloudSunnyAccount;
use App\Models\PricingCatalog;
use App\Models\Setting;
use App\Models\User;
use App\Models\VpsInstance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CloudSunnyPricingService
{
    public const CACHE_KEY = 'lugiacloud.pricing.vps_catalog_v2';
    public const ADDON_CACHE_KEY = 'lugiacloud.pricing.addon_rates_v2';
    public const BOOTSTRAP_CACHE_KEY = 'lugiacloud.pricing.bootstrapped_v2';

    /**
     * Tương thích ngược 100% với dữ liệu VPS cũ trên DB Production & đối tác tích hợp Reseller API cũ.
     */
    public const LEGACY_PLAN_ALIASES = [
        'nova-starter' => 'lg-1',
        'nova-basic' => 'lg-2',
        'nova-standard' => 'lg-4',
        'nova-pro' => 'lg-8',
        'nova-premium' => 'lg-16',
        'nova-vip' => 'lg-32',
        '7' => 'lg-4',
    ];

    private static bool $bootstrappedInProcess = false;
    private ?array $baseCatalogMemo = null;
    private ?array $addonRatesMemo = null;

    public function __construct(
        private CloudSunnyApiService $api,
        private PromotionEngine $promotions
    ) {
    }

    public function flushCache(): void
    {
        $this->baseCatalogMemo = null;
        $this->addonRatesMemo = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::ADDON_CACHE_KEY);
        Cache::forget(CloudSunnyProxyPricingService::CACHE_KEY);
        Cache::forget(CloudSunnyDedicatedPricingService::CACHE_KEY);
    }

    /**
     * Khởi tạo tự động bảng pricing_catalogs từ config/cloudsunny.php ngay lần đầu tiên (0 tốn SQL ở các request sau).
     */
    public function ensureCatalogBootstrapped(): void
    {
        if (self::$bootstrappedInProcess && !app()->runningUnitTests()) {
            return;
        }

        if (!app()->runningUnitTests() && Cache::get(self::BOOTSTRAP_CACHE_KEY) === true) {
            self::$bootstrappedInProcess = true;
            return;
        }

        if (!Schema::hasTable('pricing_catalogs')) {
            return;
        }

        if (PricingCatalog::limit(1)->exists()) {
            self::$bootstrappedInProcess = true;
            Cache::put(self::BOOTSTRAP_CACHE_KEY, true, 86400);
            return;
        }

        $order = 10;
        // Giá gốc tham chiếu mặc định an toàn nếu chưa bấm Sync API lần đầu (~72% giá bán lẻ)
        foreach (config('cloudsunny.plans', []) as $slug => $conf) {
            $retail = (int) ($conf['price_per_month'] ?? 50000);
            $estimatedProvider = (int) ($conf['provider_price_per_month'] ?? round(($retail * 0.72) / 1000) * 1000);

            PricingCatalog::updateOrCreate(
                ['slug' => $slug],
                [
                    'service_type' => 'vps',
                    'provider_code' => $conf['provider_id'] ?? $slug,
                    'provider_product_id' => isset($conf['product_id']) ? (int) $conf['product_id'] : null,
                    'name' => $conf['name'] ?? $slug,
                    'description' => $conf['desc'] ?? 'Máy chủ ảo hiệu năng cao 100% ổ cứng SSD chuyên dụng',
                    'badge' => $conf['badge'] ?? null,
                    'specs' => [
                        'cores' => (int) ($conf['cores'] ?? 1),
                        'ram' => (int) ($conf['ram'] ?? 1),
                        'disk' => (int) ($conf['disk'] ?? 20),
                        'transfer_tb' => $conf['transfer_tb'] ?? 'Unlimited',
                        'network_out_mbps' => (int) ($conf['network_out_mbps'] ?? 100),
                    ],
                    'provider_cost_monthly' => $estimatedProvider,
                    'markup_mode' => 'fixed_price',
                    'markup_percent' => 35,
                    'retail_price_monthly' => $retail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 5.0,
                    'is_active' => true,
                    'is_featured' => in_array($slug, ['lg-8'], true),
                    'sort_order' => $order,
                ]
            );
            $order += 10;
        }

        $proxyOrder = 10;
        foreach (config('cloudsunny.proxy_plans', []) as $pid => $pconf) {
            $retail = (int) ($pconf['price_per_month'] ?? 20000);
            $wholesale = (int) ($pconf['provider_price_per_month'] ?? round($retail * 0.7));
            $isResidential = stripos($pconf['name'] ?? '', 'Dân Cư') !== false;
            $isPrivate = stripos($pconf['name'] ?? '', 'Riêng') !== false;

            PricingCatalog::updateOrCreate(
                ['slug' => 'proxy-' . $pid],
                [
                    'service_type' => 'proxy',
                    'provider_code' => 'proxy-' . $pid,
                    'provider_product_id' => (int) $pid,
                    'name' => $pconf['name'] ?? ('Proxy #' . $pid),
                    'description' => $isResidential
                        ? ($isPrivate ? 'IP dân cư Việt Nam tĩnh riêng biệt, độ trust cao cho tài khoản nhạy cảm.' : 'IP dân cư Việt Nam tối ưu chi phí cho nuôi tài khoản & crawl.')
                        : ($isPrivate ? 'IPv4 Datacenter riêng biệt, tốc độ 1Gbps ổn định liên tục.' : 'IPv4 Datacenter chia sẻ băng thông không giới hạn, tiết kiệm.'),
                    'badge' => ($isResidential && $isPrivate) ? 'Bán chạy' : null,
                    'specs' => [
                        'category' => $isResidential ? 'residential' : 'datacenter',
                        'dedicated' => $isPrivate,
                        'protocols' => ['HTTP', 'SOCKS5'],
                        'country' => 'VN',
                        'bandwidth' => 'Unlimited',
                    ],
                    'provider_cost_monthly' => $wholesale,
                    'markup_mode' => 'fixed_price',
                    'markup_percent' => $wholesale > 0 ? round((($retail - $wholesale) / $wholesale) * 100, 1) : 35,
                    'retail_price_monthly' => $retail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 5.0,
                    'is_active' => true,
                    'is_featured' => $isResidential && $isPrivate,
                    'sort_order' => $proxyOrder,
                ]
            );
            $proxyOrder += 10;
        }

        $addonRetail = config('cloudsunny.addon_prices', []);
        $addonWholesale = config('cloudsunny.provider_addon_prices', []);
        $addons = [
            'addon-cpu' => [
                'name' => 'Thêm 1 vCPU Core',
                'retail' => (int) ($addonRetail['cpu_monthly'] ?? 25000),
                'cost' => (int) ($addonWholesale['cpu_monthly'] ?? 16200),
            ],
            'addon-ram' => [
                'name' => 'Thêm 1 GB RAM DDR4',
                'retail' => (int) ($addonRetail['ram_monthly'] ?? 25000),
                'cost' => (int) ($addonWholesale['ram_monthly'] ?? 16200),
            ],
            'addon-disk-10gb' => [
                'name' => 'Thêm 10 GB ổ cứng SSD',
                'retail' => (int) ($addonRetail['disk_10gb_monthly'] ?? 15000),
                'cost' => (int) ($addonWholesale['disk_10gb_monthly'] ?? 5400),
            ],
            'addon-change-ip' => [
                'name' => 'Đổi địa chỉ IPv4 VPS (mỗi lần)',
                'retail' => (int) ($addonRetail['change_ip'] ?? 30000),
                'cost' => (int) ($addonWholesale['change_ip'] ?? 20000),
            ],
        ];
        $aOrder = 10;
        foreach ($addons as $slug => $a) {
            PricingCatalog::updateOrCreate(
                ['slug' => $slug],
                [
                    'service_type' => 'addon',
                    'provider_code' => $slug,
                    'name' => $a['name'],
                    'provider_cost_monthly' => $a['cost'],
                    'markup_mode' => 'fixed_price',
                    'markup_percent' => 40,
                    'retail_price_monthly' => $a['retail'],
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 5.0,
                    'is_active' => true,
                    'sort_order' => $aOrder,
                ]
            );
            $aOrder += 10;
        }

        self::$bootstrappedInProcess = true;
        Cache::put(self::BOOTSTRAP_CACHE_KEY, true, 86400);
        $this->flushCache();
    }

    /**
     * Đồng bộ giá gốc thực tế từ API đại lý CloudSunny về bảng pricing_catalogs.
     * Tuyệt đối KHÔNG bán gói mới với giá gốc 0% lời: gói mới tự động áp dụng Default Markup % (mặc định +35%).
     */
    public function syncFromUpstream(?CloudSunnyAccount $account = null): array
    {
        $this->ensureCatalogBootstrapped();

        $account = $account ?? app(CloudSunnyAccountRouter::class)->firstActive();
        if (!$account) {
            return ['vps_synced' => 0, 'proxy_synced' => 0];
        }

        $defaultMarkup = (float) (Setting::where('key', 'default_reseller_markup_percent')->value('value') ?? 35);
        $autoPublishNew = (Setting::where('key', 'auto_publish_new_upstream_plans')->value('value') ?? '0') === '1';

        $data = $this->api->forAccount($account)->listProducts(true);
        $vpsSynced = 0;
        $proxySynced = 0;

        foreach (($data['vps'] ?? []) as $item) {
            $providerId = (int) ($item['id'] ?? 0);
            $providerCode = $this->planKey($item);
            $pricing = is_array($item['data_pricing'] ?? null) ? $item['data_pricing'] : [];
            $providerMonthly = (int) ($pricing['monthly'] ?? $item['amount'] ?? 0);

            $catalog = PricingCatalog::where('service_type', 'vps')
                ->where(function ($q) use ($providerId, $providerCode) {
                    if ($providerId > 0) {
                        $q->where('provider_product_id', $providerId)->orWhere('provider_code', $providerCode);
                    } else {
                        $q->where('provider_code', $providerCode);
                    }
                })
                ->first();

            $cores = (int) ($item['cpu'] ?? ($catalog->specs['cores'] ?? 1));
            $ram = (int) ($item['memory'] ?? ($catalog->specs['ram'] ?? 1));
            $disk = (int) ($item['disk'] ?? ($catalog->specs['disk'] ?? 20));
            $isHighEnd = $cores >= 4 && $ram >= 8 && $disk >= 50;

            if ($catalog) {
                $specs = array_merge($catalog->specs ?? [], [
                    'cores' => $cores,
                    'ram' => $ram,
                    'disk' => $disk,
                    'network_out_mbps' => $catalog->specs['network_out_mbps'] ?? ($isHighEnd ? 1000 : 100),
                    'transfer_tb' => $catalog->specs['transfer_tb'] ?? 'Unlimited',
                ]);

                $update = [
                    'provider_product_id' => $providerId ?: $catalog->provider_product_id,
                    'provider_code' => $providerCode,
                    'provider_cost_monthly' => $providerMonthly > 0 ? $providerMonthly : $catalog->provider_cost_monthly,
                    'provider_cycle_costs' => !empty($pricing) ? $pricing : $catalog->provider_cycle_costs,
                    'specs' => $specs,
                    'last_synced_at' => now(),
                ];

                if ($catalog->markup_mode === 'percent_markup' && $providerMonthly > 0) {
                    $update['retail_price_monthly'] = (int) (ceil(($providerMonthly * (1 + $catalog->markup_percent / 100)) / 1000) * 1000);
                }

                $catalog->update($update);
            } elseif ($providerMonthly > 0) {
                // Gói mới bên API gốc: áp dụng ngay markup mặc định, mặc định ẩn chờ Admin duyệt (trừ khi bật auto_publish)
                $retail = (int) (ceil(($providerMonthly * (1 + $defaultMarkup / 100)) / 1000) * 1000);
                PricingCatalog::create([
                    'service_type' => 'vps',
                    'slug' => $providerCode,
                    'provider_code' => $providerCode,
                    'provider_product_id' => $providerId ?: null,
                    'name' => $item['title'] ?? ('Cloud VPS ' . $cores . 'C-' . $ram . 'G'),
                    'description' => 'Máy chủ ảo 100% ổ cứng SSD chuyên dụng hiệu năng cao',
                    'specs' => [
                        'cores' => $cores,
                        'ram' => $ram,
                        'disk' => $disk,
                        'transfer_tb' => 'Unlimited',
                        'network_out_mbps' => $isHighEnd ? 1000 : 100,
                    ],
                    'provider_cost_monthly' => $providerMonthly,
                    'provider_cycle_costs' => $pricing,
                    'markup_mode' => 'percent_markup',
                    'markup_percent' => $defaultMarkup,
                    'retail_price_monthly' => $retail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 8.0,
                    'is_active' => $autoPublishNew,
                    'sort_order' => 100 + $providerId,
                    'last_synced_at' => now(),
                ]);
            }
            $vpsSynced++;
        }

        foreach (($data['proxy'] ?? []) as $pItem) {
            $pid = (int) ($pItem['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $pPricing = is_array($pItem['data_pricing'] ?? null) ? $pItem['data_pricing'] : [];
            $providerMonthly = (int) ($pPricing['monthly'] ?? $pItem['amount'] ?? 0);

            $catalog = PricingCatalog::where('service_type', 'proxy')
                ->where('provider_product_id', $pid)
                ->first();

            if ($catalog) {
                $update = [
                    'provider_cost_monthly' => $providerMonthly > 0 ? $providerMonthly : $catalog->provider_cost_monthly,
                    'provider_cycle_costs' => !empty($pPricing) ? $pPricing : $catalog->provider_cycle_costs,
                    'last_synced_at' => now(),
                ];
                if ($catalog->markup_mode === 'percent_markup' && $providerMonthly > 0) {
                    $update['retail_price_monthly'] = (int) (ceil(($providerMonthly * (1 + $catalog->markup_percent / 100)) / 1000) * 1000);
                }
                $catalog->update($update);
            } elseif ($providerMonthly > 0) {
                $retail = (int) (ceil(($providerMonthly * (1 + $defaultMarkup / 100)) / 1000) * 1000);
                PricingCatalog::create([
                    'service_type' => 'proxy',
                    'slug' => 'proxy-' . $pid,
                    'provider_code' => 'proxy-' . $pid,
                    'provider_product_id' => $pid,
                    'name' => $pItem['title'] ?? ('Proxy IPv4 #' . $pid),
                    'description' => 'Proxy IPv4 Việt Nam tốc độ cao hỗ trợ HTTP & SOCKS5.',
                    'specs' => [
                        'category' => stripos($pItem['title'] ?? '', 'dân cư') !== false ? 'residential' : 'datacenter',
                        'dedicated' => stripos($pItem['title'] ?? '', 'riêng') !== false,
                        'protocols' => ['HTTP', 'SOCKS5'],
                        'country' => 'VN',
                        'bandwidth' => 'Unlimited',
                    ],
                    'provider_cost_monthly' => $providerMonthly,
                    'provider_cycle_costs' => $pPricing,
                    'markup_mode' => 'percent_markup',
                    'markup_percent' => $defaultMarkup,
                    'retail_price_monthly' => $retail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 8.0,
                    'is_active' => $autoPublishNew,
                    'sort_order' => 100 + $pid,
                    'last_synced_at' => now(),
                ]);
            }
            $proxySynced++;
        }

        // Đồng bộ danh sách Máy chủ vật lý (Dedicated Server) nếu tài khoản đại lý đã được cấp quyền
        $serverSynced = 0;
        foreach (($data['server'] ?? []) as $sItem) {
            $sid = (int) ($sItem['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $sPricing = is_array($sItem['data_pricing'] ?? null) ? $sItem['data_pricing'] : [];
            $providerMonthly = (int) ($sPricing['monthly'] ?? 0);
            $cfg = is_array($sItem['config'] ?? null) ? $sItem['config'] : [];
            $setupFee = max(0, (int) ($sItem['setup_fee'] ?? 0));
            $specs = array_merge($cfg, ['setup_fee' => $setupFee]);

            $catalog = PricingCatalog::where('service_type', 'server')
                ->where('provider_product_id', $sid)
                ->first();

            if ($catalog) {
                $update = [
                    'provider_cost_monthly' => $providerMonthly > 0 ? $providerMonthly : $catalog->provider_cost_monthly,
                    'provider_cycle_costs' => !empty($sPricing) ? $sPricing : $catalog->provider_cycle_costs,
                    'specs' => array_merge($catalog->specs ?? [], $specs),
                    'last_synced_at' => now(),
                ];
                if ($catalog->markup_mode === 'percent_markup' && $providerMonthly > 0) {
                    $update['retail_price_monthly'] = (int) (ceil(($providerMonthly * (1 + $catalog->markup_percent / 100)) / 10000) * 10000);
                }
                $catalog->update($update);
            } elseif ($providerMonthly > 0) {
                // Biên lợi nhuận mặc định 18% cho phân khúc Máy chủ vật lý (làm tròn 10.000đ)
                $serverMarkup = 18.0;
                $retail = (int) (ceil(($providerMonthly * (1 + $serverMarkup / 100)) / 10000) * 10000);
                $cpuText = trim((string) ($cfg['cpu_model'] ?? 'Intel Xeon'));
                $ramText = !empty($cfg['ram_gb']) ? ($cfg['ram_gb'] . 'GB ' . ($cfg['ram_type'] ?? 'ECC')) : '';
                $storageText = (string) ($cfg['storage'] ?? '');

                PricingCatalog::create([
                    'service_type' => 'server',
                    'slug' => 'server-' . $sid,
                    'provider_code' => 'server-' . $sid,
                    'provider_product_id' => $sid,
                    'name' => (string) ($sItem['title'] ?? ('Dedicated Server #' . $sid)),
                    'description' => trim(implode(' · ', array_filter([$cpuText, $ramText, $storageText]))),
                    'badge' => !empty($cfg['ipmi']) ? 'IPMI · 1Gbps' : 'Dedicated',
                    'specs' => $specs,
                    'provider_cost_monthly' => $providerMonthly,
                    'provider_cycle_costs' => $sPricing,
                    'markup_mode' => 'percent_markup',
                    'markup_percent' => $serverMarkup,
                    'retail_price_monthly' => $retail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 5.0,
                    'is_active' => true,
                    'sort_order' => 10 + $sid,
                    'last_synced_at' => now(),
                ]);
            }
            $serverSynced++;
        }

        // Đồng bộ đơn giá Đổi IPv4 VPS từ API cấu hình đổi IP
        try {
            $changeIpCfg = $this->api->forAccount($account)->getChangeIpConfig(null, true);
            $changeIpCost = (int) ($changeIpCfg['price'] ?? config('cloudsunny.provider_addon_prices.change_ip', 20000));
            $changeIpCatalog = PricingCatalog::where('slug', 'addon-change-ip')->first();
            if ($changeIpCatalog) {
                $update = [
                    'provider_cost_monthly' => $changeIpCost > 0 ? $changeIpCost : $changeIpCatalog->provider_cost_monthly,
                    'last_synced_at' => now(),
                ];
                if ($changeIpCatalog->markup_mode === 'percent_markup' && $changeIpCost > 0) {
                    $update['retail_price_monthly'] = (int) (ceil(($changeIpCost * (1 + $changeIpCatalog->markup_percent / 100)) / 1000) * 1000);
                }
                $changeIpCatalog->update($update);
            } elseif ($changeIpCost > 0) {
                $defaultRetail = max((int) config('cloudsunny.addon_prices.change_ip', 30000), (int) (ceil(($changeIpCost * 1.5) / 1000) * 1000));
                PricingCatalog::create([
                    'service_type' => 'addon',
                    'slug' => 'addon-change-ip',
                    'provider_code' => 'addon-change-ip',
                    'name' => 'Đổi địa chỉ IPv4 VPS (mỗi lần)',
                    'description' => 'Cấp mới địa chỉ IPv4 cho máy chủ Cloud VPS đang hoạt động.',
                    'provider_cost_monthly' => $changeIpCost,
                    'markup_mode' => 'fixed_price',
                    'markup_percent' => 50,
                    'retail_price_monthly' => $defaultRetail,
                    'protect_cost_floor' => true,
                    'min_margin_percent' => 10.0,
                    'is_active' => (bool) ($changeIpCfg['enable'] ?? true),
                    'sort_order' => 40,
                    'last_synced_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Không làm gián đoạn luồng sync sản phẩm chính
        }

        $this->flushCache();
        app(CloudSunnyDedicatedPricingService::class)->flushCache();

        return ['vps_synced' => $vpsSynced, 'proxy_synced' => $proxySynced, 'server_synced' => $serverSynced];
    }

    private function getBaseCatalogItems(): array
    {
        if ($this->baseCatalogMemo !== null && !app()->runningUnitTests()) {
            return $this->baseCatalogMemo;
        }

        $this->ensureCatalogBootstrapped();

        return $this->baseCatalogMemo = Cache::remember(self::CACHE_KEY, 300, function () {
            if (!Schema::hasTable('pricing_catalogs')) {
                return config('cloudsunny.plans', []);
            }

            $rows = PricingCatalog::where('service_type', 'vps')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $result = [];
            foreach ($rows as $index => $row) {
                $specs = $row->specs ?? [];
                $monthlyRetail = $row->effectiveMonthlyRetail();

                $result[$row->slug] = [
                    'catalog_id' => $row->id,
                    'slug' => $row->slug,
                    'name' => $row->name,
                    'desc' => $row->description ?: 'Máy chủ ảo 100% ổ cứng SSD chuyên dụng hiệu năng cao',
                    'badge' => $row->badge,
                    'is_featured' => (bool) $row->is_featured,
                    'product_id' => (int) ($row->provider_product_id ?: ($index + 1)),
                    'provider_plan_key' => $row->provider_code ?: $row->slug,
                    'cores' => (int) ($specs['cores'] ?? 1),
                    'ram' => (int) ($specs['ram'] ?? 1),
                    'disk' => (int) ($specs['disk'] ?? 20),
                    'transfer_tb' => $specs['transfer_tb'] ?? 'Unlimited',
                    'network_out_mbps' => (int) ($specs['network_out_mbps'] ?? 100),
                    'list_price_per_month' => $monthlyRetail,
                    'price_per_month' => $monthlyRetail,
                    'provider_price_per_month' => (int) $row->provider_cost_monthly,
                    'provider_data_pricing' => $row->provider_cycle_costs ?? [],
                    'retail_cycle_prices' => $row->retail_cycle_prices ?? [],
                    'protect_cost_floor' => (bool) $row->protect_cost_floor,
                    'min_margin_percent' => (float) $row->min_margin_percent,
                ];
            }

            return $result;
        });
    }

    public function getPlans(?User $user = null): array
    {
        $plans = $this->getBaseCatalogItems();
        $user = $user ?? Auth::user();
        $cycles = config('cloudsunny.billing_cycles', []);

        foreach ($plans as $slug => &$plan) {
            $monthlyQuote = $this->quoteCycleForPlan($plan, 'monthly', $user);
            $plan['price_per_month'] = $monthlyQuote['final_price'];
            $plan['has_discount'] = $monthlyQuote['discount_amount'] > 0;
            $plan['discount_amount_monthly'] = $monthlyQuote['discount_amount'];
            $plan['campaign'] = $monthlyQuote['campaign'];
            $plan['flash_sale'] = $monthlyQuote['flash_sale'] ?? null;

            $cycleQuotes = [];
            foreach ($cycles as $cycleKey => $cycleMeta) {
                $q = $this->quoteCycleForPlan($plan, $cycleKey, $user);
                $cycleQuotes[$cycleKey] = [
                    'months' => (int) ($cycleMeta['months'] ?? 1),
                    'label' => $cycleMeta['label'] ?? $cycleKey,
                    'cycle_discount_percent' => (int) ($cycleMeta['discount_percent'] ?? 0),
                    'list_price' => $q['list_price'],
                    'final_price' => $q['final_price'],
                    'monthly_equivalent' => (int) round($q['final_price'] / max(1, (int) ($cycleMeta['months'] ?? 1))),
                    'discount_amount' => $q['discount_amount'],
                    'campaign' => $q['campaign'],
                    'flash_sale' => $q['flash_sale'] ?? null,
                ];
            }
            $plan['cycle_quotes'] = $cycleQuotes;
        }

        return $plans;
    }

    /**
     * Xuất danh sách gói ra trình duyệt (ẩn tuyệt đối giá vốn gốc).
     */
    public function getPublicPlans(?User $user = null): array
    {
        $plans = $this->getPlans($user);
        foreach ($plans as &$plan) {
            unset($plan['provider_price_per_month'], $plan['provider_data_pricing']);
        }
        return $plans;
    }

    public function getPlan(string $planId, ?User $user = null): ?array
    {
        $plans = $this->getPlans($user);
        if (isset($plans[$planId])) {
            return $plans[$planId];
        }

        $aliased = self::LEGACY_PLAN_ALIASES[$planId] ?? null;
        if ($aliased && isset($plans[$aliased])) {
            return $plans[$aliased];
        }

        foreach ($plans as $plan) {
            $key = $plan['provider_plan_key'] ?? '';
            $productId = (string) ($plan['product_id'] ?? '');

            if ($key === $planId || $productId === (string) $planId) {
                return $plan;
            }
        }

        return null;
    }

    public function getAddonRates(): array
    {
        if ($this->addonRatesMemo !== null && !app()->runningUnitTests()) {
            return $this->addonRatesMemo;
        }

        $this->ensureCatalogBootstrapped();

        return $this->addonRatesMemo = Cache::remember(self::ADDON_CACHE_KEY, 300, function () {
            if (!Schema::hasTable('pricing_catalogs')) {
                $retail = config('cloudsunny.addon_prices', []);
                $cost = config('cloudsunny.provider_addon_prices', []);
                return [
                    'cpu_monthly' => (int) ($retail['cpu_monthly'] ?? 25000),
                    'ram_monthly' => (int) ($retail['ram_monthly'] ?? 25000),
                    'disk_10gb_monthly' => (int) ($retail['disk_10gb_monthly'] ?? 15000),
                    'change_ip' => (int) ($retail['change_ip'] ?? 30000),
                    'change_ip_active' => true,
                    'cost_cpu_monthly' => (int) ($cost['cpu_monthly'] ?? 16200),
                    'cost_ram_monthly' => (int) ($cost['ram_monthly'] ?? 16200),
                    'cost_disk_10gb_monthly' => (int) ($cost['disk_10gb_monthly'] ?? 5400),
                    'cost_change_ip' => (int) ($cost['change_ip'] ?? 20000),
                ];
            }

            $rows = PricingCatalog::where('service_type', 'addon')->get()->keyBy('slug');

            if (!isset($rows['addon-change-ip'])) {
                $changeIpRow = PricingCatalog::firstOrCreate(
                    ['slug' => 'addon-change-ip'],
                    [
                        'service_type' => 'addon',
                        'provider_code' => 'addon-change-ip',
                        'name' => 'Đổi địa chỉ IPv4 VPS (mỗi lần)',
                        'description' => 'Cấp mới địa chỉ IPv4 cho máy chủ Cloud VPS đang hoạt động.',
                        'provider_cost_monthly' => (int) config('cloudsunny.provider_addon_prices.change_ip', 20000),
                        'markup_mode' => 'fixed_price',
                        'markup_percent' => 50,
                        'retail_price_monthly' => (int) config('cloudsunny.addon_prices.change_ip', 30000),
                        'protect_cost_floor' => true,
                        'min_margin_percent' => 10.0,
                        'is_active' => true,
                        'sort_order' => 40,
                    ]
                );
                $rows->put('addon-change-ip', $changeIpRow);
            }

            return [
                'cpu_monthly' => isset($rows['addon-cpu']) ? $rows['addon-cpu']->effectiveMonthlyRetail() : 25000,
                'ram_monthly' => isset($rows['addon-ram']) ? $rows['addon-ram']->effectiveMonthlyRetail() : 25000,
                'disk_10gb_monthly' => isset($rows['addon-disk-10gb']) ? $rows['addon-disk-10gb']->effectiveMonthlyRetail() : 15000,
                'change_ip' => isset($rows['addon-change-ip']) ? $rows['addon-change-ip']->effectiveMonthlyRetail() : 30000,
                'change_ip_active' => isset($rows['addon-change-ip']) ? (bool) $rows['addon-change-ip']->is_active : true,
                'cost_cpu_monthly' => (int) ($rows['addon-cpu']->provider_cost_monthly ?? 16200),
                'cost_ram_monthly' => (int) ($rows['addon-ram']->provider_cost_monthly ?? 16200),
                'cost_disk_10gb_monthly' => (int) ($rows['addon-disk-10gb']->provider_cost_monthly ?? 5400),
                'cost_change_ip' => (int) ($rows['addon-change-ip']->provider_cost_monthly ?? 20000),
            ];
        });
    }

    public function quoteCycleForPlan(
        array $plan,
        string $billingCycle,
        ?User $user = null,
        int $addonCpu = 0,
        int $addonRam = 0,
        int $addonDisk = 0,
        bool $isRenew = false,
        ?string $couponCode = null,
        int $quantity = 1
    ): array {
        $qty = max(1, $quantity);
        $months = $this->monthsForCycle($billingCycle);
        $cycleDiscountPercent = (int) (config("cloudsunny.billing_cycles.{$billingCycle}.discount_percent") ?? 0);

        $baseMonthlyRetail = (int) ($plan['list_price_per_month'] ?? $plan['price_per_month'] ?? 0);
        $retailOverrides = $plan['retail_cycle_prices'] ?? [];

        if (isset($retailOverrides[$billingCycle]) && (int) $retailOverrides[$billingCycle] > 0) {
            $planListTotal = (int) $retailOverrides[$billingCycle];
        } else {
            $planListTotal = $baseMonthlyRetail * $months;
            if ($cycleDiscountPercent > 0 && $cycleDiscountPercent <= 100) {
                $planListTotal = (int) round($planListTotal * (1 - $cycleDiscountPercent / 100));
            }
        }

        $addons = $this->getAddonRates();
        $addonRetailMonthly = ($addonCpu * $addons['cpu_monthly'])
            + ($addonRam * $addons['ram_monthly'])
            + ((int) ($addonDisk / 10) * $addons['disk_10gb_monthly']);
        $addonListTotal = $addonRetailMonthly * $months;
        if ($cycleDiscountPercent > 0 && $addonListTotal > 0) {
            $addonListTotal = (int) round($addonListTotal * (1 - $cycleDiscountPercent / 100));
        }

        $listTotal = ($planListTotal + $addonListTotal) * $qty;

        $providerPlanCost = $this->calculateProviderCost($plan, $billingCycle);
        $providerAddonCost = (($addonCpu * $addons['cost_cpu_monthly'])
            + ($addonRam * $addons['cost_ram_monthly'])
            + ((int) ($addonDisk / 10) * $addons['cost_disk_10gb_monthly'])) * $months;
        $totalProviderCost = ($providerPlanCost + $providerAddonCost) * $qty;

        return $this->promotions->quoteOrder([
            'service_type' => 'vps',
            'plan_slug' => $plan['slug'] ?? '',
            'list_price' => $listTotal,
            'base_plan_list_price' => $planListTotal * $qty,
            'provider_cost' => $totalProviderCost,
            'months' => $months,
            'quantity' => $qty,
            'is_renew' => $isRenew,
            'user' => $user,
            'coupon_code' => $couponCode,
            'protect_cost_floor' => $plan['protect_cost_floor'] ?? true,
            'min_margin_percent' => $plan['min_margin_percent'] ?? 5.0,
        ]);
    }

    /**
     * Tính giá nâng cấp phần cứng giữa chu kỳ đồng nhất cho cả Web và Reseller API.
     */
    public function quoteMidCycleUpgrade(
        VpsInstance $vps,
        int $addonCpu,
        int $addonRam,
        int $addonDisk,
        ?User $user = null
    ): array {
        $days = $vps->expires_at ? max(1, (int) now()->diffInDays($vps->expires_at, false)) : 30;
        $fullMonths = floor($days / 30);
        $remainderDays = $days % 30;

        $chargeFactor = $fullMonths;
        if ($remainderDays > 15) {
            $chargeFactor += 1;
        } elseif ($remainderDays > 0) {
            $chargeFactor += 0.5;
        }
        $chargeFactor = max(0.5, $chargeFactor);

        $addons = $this->getAddonRates();
        $retailMonthly = ($addonCpu * $addons['cpu_monthly'])
            + ($addonRam * $addons['ram_monthly'])
            + ((int) ($addonDisk / 10) * $addons['disk_10gb_monthly']);
        $costMonthly = ($addonCpu * $addons['cost_cpu_monthly'])
            + ($addonRam * $addons['cost_ram_monthly'])
            + ((int) ($addonDisk / 10) * $addons['cost_disk_10gb_monthly']);

        $listPrice = (int) round($retailMonthly * $chargeFactor);
        $providerCost = (int) round($costMonthly * $chargeFactor);

        return $this->promotions->quoteOrder([
            'service_type' => 'vps',
            'plan_slug' => (string) $vps->plan_id,
            'list_price' => $listPrice,
            'provider_cost' => $providerCost,
            'months' => max(1, (int) ceil($chargeFactor)),
            'is_renew' => false,
            'is_upgrade' => true,
            'user' => $user ?? Auth::user(),
            'protect_cost_floor' => true,
            'min_margin_percent' => 5.0,
        ]);
    }

    public function applyUserDiscount(int $amount, ?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if ($user && $user->discount_percent > 0) {
            $discount = min(100, max(0, (int) $user->discount_percent));
            return (int) round($amount * (1 - ($discount / 100)));
        }

        return $amount;
    }

    public function getRegions(): array
    {
        return [
            'vn' => [
                'code' => 'vn',
                'name' => 'Việt Nam — FPT Datacenter (TP.HCM)',
                'datacenter' => 'FPT Telecom TP.HCM · Tích hợp sẵn Anti DDoS',
                'ping' => 'Ping Việt Nam cực thấp',
            ],
        ];
    }

    public function getImages(int $productId): array
    {
        return Cache::remember("cloudsunny.products.{$productId}.os.v3", 21600, function () use ($productId) {
            try {
                $account = app(CloudSunnyAccountRouter::class)->firstActive();
                if ($account && ($account->access_token || config('cloudsunny.api_username'))) {
                    $items = $this->api->forAccount($account)->listOperatingSystems($productId);
                    if (empty($items) && $productId !== 1) {
                        $items = $this->api->forAccount($account)->listOperatingSystems(1);
                    }

                    if (!empty($items)) {
                        return collect($items)->mapWithKeys(function (array $item) {
                            $id = (int) ($item['os-id'] ?? $item['id'] ?? 0);
                            $name = $item['os-name'] ?? $item['name'] ?? ('OS #' . $id);
                            $distro = $this->detectDistroKey($name);

                            return [$id => [
                                'id' => $id,
                                'label' => $name,
                                'distro' => $distro,
                                'group' => $distro === 'windows' ? 'Windows' : 'Linux',
                            ]];
                        })->filter(fn ($item, $id) => $id > 0)->all();
                    }
                }
            } catch (\Throwable $e) {
                // Fallback sang danh mục chuẩn bên dưới
            }

            return collect(config('cloudsunny.images', []))->mapWithKeys(function ($img, $id) {
                $name = $img['label'] ?? ('OS #' . $id);
                $distro = $this->detectDistroKey($name);
                return [(int) $id => [
                    'id' => (int) $id,
                    'label' => $name,
                    'distro' => $distro,
                    'group' => $img['group'] ?? ($distro === 'windows' ? 'Windows' : 'Linux'),
                ]];
            })->all();
        });
    }

    public function detectDistroKey(string $label): string
    {
        $l = strtolower($label);
        if (str_contains($l, 'windows')) {
            return 'windows';
        }
        if (str_contains($l, 'ubuntu')) {
            return 'ubuntu';
        }
        if (str_contains($l, 'debian')) {
            return 'debian';
        }
        if (str_contains($l, 'alma')) {
            return 'almalinux';
        }
        if (str_contains($l, 'centos')) {
            return 'centos';
        }

        return 'linux';
    }

    public function getDurations(): array
    {
        return collect(config('cloudsunny.billing_cycles', []))
            ->mapWithKeys(fn ($item, $key) => [$key => $item['label']])
            ->all();
    }

    public function calculatePrice(array $plan, string $billingCycle, ?User $user = null): int
    {
        return $this->quoteCycleForPlan($plan, $billingCycle, $user ?? Auth::user())['final_price'];
    }

    public function calculateProviderCost(array $plan, string $billingCycle): int
    {
        if (isset($plan['provider_data_pricing'][$billingCycle]) && (int) $plan['provider_data_pricing'][$billingCycle] > 0) {
            return (int) $plan['provider_data_pricing'][$billingCycle];
        }

        $months = $this->monthsForCycle($billingCycle);
        $baseCost = (int) ($plan['provider_price_per_month'] ?? 0);

        return $baseCost * max(1, $months);
    }

    public function monthsForCycle(string $billingCycle): int
    {
        return (int) (config("cloudsunny.billing_cycles.{$billingCycle}.months") ?? 1);
    }

    public function formatVnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.') . 'đ';
    }

    private function planKey(array $item): string
    {
        $title = strtolower((string) ($item['title'] ?? 'vps-' . ($item['id'] ?? uniqid())));
        $key = preg_replace('/[^a-z0-9]+/', '-', $title);
        $key = trim((string) $key, '-');

        return $key ?: 'vps-' . (int) ($item['id'] ?? 0);
    }
}

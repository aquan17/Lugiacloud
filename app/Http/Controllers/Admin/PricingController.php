<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingCatalog;
use App\Models\Setting;
use App\Services\CloudSunnyPricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index(CloudSunnyPricingService $pricing)
    {
        $pricing->ensureCatalogBootstrapped();
        $pricing->getAddonRates();

        $items = PricingCatalog::orderBy('service_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (PricingCatalog $row) {
                $effectiveRetail = $row->effectiveMonthlyRetail();
                $cost = (int) $row->provider_cost_monthly;
                $profit = $effectiveRetail - $cost;
                $marginPct = $cost > 0 ? round(($profit / $cost) * 100, 1) : 100;

                return [
                    'id' => $row->id,
                    'service_type' => $row->service_type,
                    'slug' => $row->slug,
                    'provider_code' => $row->provider_code,
                    'provider_product_id' => $row->provider_product_id,
                    'name' => $row->name,
                    'description' => $row->description,
                    'badge' => $row->badge,
                    'specs' => $row->specs ?? [],
                    'provider_cost_monthly' => $cost,
                    'markup_mode' => $row->markup_mode,
                    'markup_percent' => (float) $row->markup_percent,
                    'retail_price_monthly' => $effectiveRetail,
                    'raw_retail_price_monthly' => (int) $row->retail_price_monthly,
                    'profit_monthly' => $profit,
                    'margin_percent' => $marginPct,
                    'protect_cost_floor' => (bool) $row->protect_cost_floor,
                    'min_margin_percent' => (float) $row->min_margin_percent,
                    'is_active' => (bool) $row->is_active,
                    'is_featured' => (bool) $row->is_featured,
                    'sort_order' => (int) $row->sort_order,
                    'last_synced_at' => $row->last_synced_at?->format('d/m/Y H:i'),
                ];
            });

        $defaultMarkup = (float) (Setting::where('key', 'default_reseller_markup_percent')->value('value') ?? 35);
        $autoPublish = (Setting::where('key', 'auto_publish_new_upstream_plans')->value('value') ?? '0') === '1';

        return Inertia::render('Admin/Pricing/Index', [
            'items' => $items,
            'defaults' => [
                'default_markup_percent' => $defaultMarkup,
                'auto_publish_new' => $autoPublish,
            ],
        ]);
    }

    public function update(Request $request, PricingCatalog $catalog, CloudSunnyPricingService $pricing)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:140',
            'description' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:60',
            'provider_cost_monthly' => 'required|integer|min:0',
            'markup_mode' => 'required|in:fixed_price,percent_markup',
            'markup_percent' => 'required|numeric|min:0|max:500',
            'retail_price_monthly' => 'required|integer|min:0',
            'protect_cost_floor' => 'required|boolean',
            'min_margin_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'required|boolean',
            'is_featured' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:9999',
        ]);

        if ($validated['markup_mode'] === 'percent_markup' && $validated['provider_cost_monthly'] > 0) {
            $validated['retail_price_monthly'] = (int) (ceil(($validated['provider_cost_monthly'] * (1 + $validated['markup_percent'] / 100)) / 1000) * 1000);
        }

        $catalog->update($validated);
        $pricing->flushCache();

        return back()->with('success', "Đã lưu cấu hình giá & biên lợi nhuận cho gói {$catalog->name}.");
    }

    public function updateDefaults(Request $request, CloudSunnyPricingService $pricing)
    {
        $validated = $request->validate([
            'default_markup_percent' => 'required|numeric|min:5|max:300',
            'auto_publish_new' => 'required|boolean',
        ]);

        Setting::updateOrCreate(
            ['key' => 'default_reseller_markup_percent'],
            ['value' => (string) $validated['default_markup_percent'], 'type' => 'float']
        );
        Setting::updateOrCreate(
            ['key' => 'auto_publish_new_upstream_plans'],
            ['value' => $validated['auto_publish_new'] ? '1' : '0', 'type' => 'boolean']
        );

        $pricing->flushCache();

        return back()->with('success', 'Đã lưu quy tắc tăng giá mặc định cho các gói mới từ nhà cung cấp.');
    }

    public function syncUpstream(CloudSunnyPricingService $pricing)
    {
        try {
            $res = $pricing->syncFromUpstream();
            $srvMsg = !empty($res['server_synced']) ? ", {$res['server_synced']} gói Máy chủ vật lý" : '';
            return back()->with('success', "Đồng bộ giá gốc thành công: {$res['vps_synced']} gói VPS, {$res['proxy_synced']} gói Proxy{$srvMsg}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể kết nối API gốc để đồng bộ: ' . $e->getMessage());
        }
    }
}

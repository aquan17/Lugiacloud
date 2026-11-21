<?php

namespace App\Http\Controllers;

use App\Models\CloudSunnyAccount;
use App\Models\Setting;
use App\Services\CloudSunnyPricingService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\FlashSaleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(
        CloudSunnyPricingService $pricing,
        CloudSunnyProxyPricingService $proxyPricing,
        FlashSaleService $flashSales
    ) {
        $user = Auth::user();
        $plans = $pricing->getPublicPlans($user);
        $proxyProducts = $proxyPricing->getPublicCatalogProducts($user);

        $vpsOutOfStock = Cache::remember('setting_vps_out_of_stock', 300, function () {
            if (!Schema::hasTable('settings')) {
                return false;
            }
            return Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        });

        $hasProvider = $vpsOutOfStock
            ? false
            : Cache::remember('lugiacloud.has_active_provider', 60, function () {
                if (!Schema::hasTable('cloud_sunny_accounts')) {
                    return true;
                }
                $hasDbAccount = CloudSunnyAccount::where('is_active', true)->where('is_full', false)->exists();
                return $hasDbAccount || (bool) config('cloudsunny.api_username') || app()->environment('local');
            });

        return Inertia::render('Home/Index', [
            'plans' => array_values($plans),
            'proxyProducts' => $proxyProducts,
            'billingCycles' => config('cloudsunny.billing_cycles', []),
            'regions' => array_values($pricing->getRegions()),
            'availablePlanSlugs' => $hasProvider ? array_keys($plans) : [],
            'flashSales' => $flashSales->getPublicDeals($user),
            'huntVouchers' => $flashSales->getHuntableVouchers($user),
        ]);
    }

    public function support()
    {
        return Inertia::render('Support/Index');
    }

    public function notifications()
    {
        $user = auth()->user();
        $alerts = [];

        if ($user) {
            $expiringVps = $user->vpsInstances()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(7))
                ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
                ->orderBy('expires_at')
                ->take(10)
                ->get();

            foreach ($expiringVps as $v) {
                $isPast = $v->expires_at->isPast();
                $alerts[] = [
                    'id' => 'vps_' . $v->id,
                    'level' => $isPast ? 'critical' : 'warning',
                    'title' => $isPast ? "Máy chủ {$v->label} đã quá hạn thanh toán" : "Máy chủ {$v->label} sắp đến hạn gia hạn",
                    'body' => 'IP: ' . ($v->public_ip ?: 'Đang cấp phát') . ' • Hạn sử dụng: ' . $v->expires_at->format('d/m/Y H:i'),
                    'href' => "/vps/{$v->id}",
                    'time' => $v->expires_at->diffForHumans(),
                ];
            }

            $expiringProxies = $user->proxyInstances()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(5))
                ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
                ->orderBy('expires_at')
                ->take(10)
                ->get();

            foreach ($expiringProxies as $p) {
                $isPast = $p->expires_at->isPast();
                $alerts[] = [
                    'id' => 'prx_' . $p->id,
                    'level' => $isPast ? 'critical' : 'warning',
                    'title' => $isPast ? "Proxy #{$p->id} đã hết hạn" : "Proxy #{$p->id} sắp hết hạn",
                    'body' => ($p->ip ? "{$p->ip}:{$p->port}" : 'Đang cấp phát') . ' • Hạn: ' . $p->expires_at->format('d/m/Y H:i'),
                    'href' => '/proxy',
                    'time' => $p->expires_at->diffForHumans(),
                ];
            }
        }

        return Inertia::render('Notifications/Index', [
            'alerts' => $alerts,
        ]);
    }
}

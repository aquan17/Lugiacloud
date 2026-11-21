<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\PromotionEngine;
use App\Services\WelcomeOfferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $promotions = app(PromotionEngine::class)->getActiveState();
        $promotions['welcome_offer'] = app(WelcomeOfferService::class)->getActiveOfferForUser($user);

        $vpsOutOfStock = Cache::remember('setting_vps_out_of_stock', 300, function () {
            if (!Schema::hasTable('settings')) {
                return false;
            }
            return Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'balance' => (int) $user->balance,
                    'is_admin' => $user->isAdmin(),
                    'is_reseller' => $user->isReseller(),
                    'role_label' => $user->roleLabel(),
                    'discount_percent' => (int) $user->discount_percent,
                    'proxy_discount_percent' => (int) $user->proxy_discount_percent,
                    'ref_code' => $user->ref_code,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'promotions' => $promotions,
            'system' => [
                'vps_out_of_stock' => $vpsOutOfStock,
                'topup_bonus' => app(PromotionEngine::class)->calculateTopupBonus(100000),
            ],
        ];
    }
}

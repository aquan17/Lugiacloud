<?php

namespace App\Http\Controllers;

use App\Services\CloudSunnyPricingService;
use App\Services\CloudSunnyProxyPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionQuoteController extends Controller
{
    /**
     * API nội bộ kiểm tra mã giảm giá & tính báo giá thời gian thực ngay trên màn hình Cloud Configurator.
     */
    public function preview(
        Request $request,
        CloudSunnyPricingService $vpsPricing,
        CloudSunnyProxyPricingService $proxyPricing
    ): JsonResponse {
        $validated = $request->validate([
            'service_type' => 'required|in:vps,proxy,vps_upgrade',
            'plan' => 'nullable|string',
            'vps_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'billing_cycle' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1|max:50',
            'addon_cpu' => 'nullable|integer|min:0|max:16',
            'addon_ram' => 'nullable|integer|min:0|max:64',
            'addon_disk' => 'nullable|integer|min:0|max:1000',
            'coupon_code' => 'nullable|string|max:48',
            'is_renew' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $isRenew = (bool) ($validated['is_renew'] ?? false);
        $couponCode = $validated['coupon_code'] ?? null;

        if ($validated['service_type'] === 'vps_upgrade') {
            $vps = \App\Models\VpsInstance::where('id', (int) ($validated['vps_id'] ?? 0))
                ->where('user_id', $user?->id)
                ->first();
            if (!$vps) {
                return response()->json(['error' => 'Máy chủ không hợp lệ.'], 404);
            }
            $quote = $vpsPricing->quoteMidCycleUpgrade(
                $vps,
                (int) ($validated['addon_cpu'] ?? 0),
                (int) ($validated['addon_ram'] ?? 0),
                (int) ($validated['addon_disk'] ?? 0),
                $user
            );
        } elseif ($validated['service_type'] === 'vps') {
            $plan = $vpsPricing->getPlan((string) ($validated['plan'] ?? ''), $user);
            if (!$plan) {
                return response()->json(['error' => 'Gói cấu hình không tồn tại.'], 404);
            }

            $quote = $vpsPricing->quoteCycleForPlan(
                $plan,
                (string) ($validated['billing_cycle'] ?? 'monthly'),
                $user,
                (int) ($validated['addon_cpu'] ?? 0),
                (int) ($validated['addon_ram'] ?? 0),
                (int) ($validated['addon_disk'] ?? 0),
                $isRenew,
                $couponCode,
                max(1, (int) ($validated['quantity'] ?? 1))
            );
        } else {
            $product = $proxyPricing->findProductById((int) ($validated['product_id'] ?? 0), $user);
            if (!$product) {
                return response()->json(['error' => 'Gói Proxy không tồn tại.'], 404);
            }

            $quote = $proxyPricing->quoteProxyOrder(
                $product,
                (int) ($validated['quantity'] ?? 1),
                (string) ($validated['billing_cycle'] ?? 'monthly'),
                $user,
                $isRenew,
                $couponCode
            );
        }

        // Tuyệt đối không lộ provider_cost hay estimated_profit ra ngoài trình duyệt khách hàng!
        unset($quote['provider_cost'], $quote['estimated_profit']);

        return response()->json($quote);
    }
}

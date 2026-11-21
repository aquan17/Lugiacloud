<?php

namespace App\Http\Controllers;

use App\Models\CloudSunnyAccount;
use App\Models\Setting;
use App\Models\VpsInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\FlashSaleService;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use RuntimeException;

class StoreController extends Controller
{
    public function index(CloudSunnyPricingService $pricing, FlashSaleService $flashSales)
    {
        $user = Auth::user();
        $plans = $pricing->getPublicPlans($user);
        $outOfStock = Cache::remember('setting_vps_out_of_stock', 3600, function () {
            return Schema::hasTable('settings')
                && Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        });

        $hasProvider = !$outOfStock && Cache::remember('lugiacloud.has_active_provider', 60, function () {
            if (!Schema::hasTable('cloud_sunny_accounts')) {
                return true;
            }
            return CloudSunnyAccount::where('is_active', true)->where('is_full', false)->exists()
                || (bool) config('cloudsunny.api_username')
                || app()->environment('local');
        });

        $vpsFlashSales = array_values(array_filter(
            $flashSales->getPublicDeals($user),
            fn (array $d) => $d['service_type'] === 'vps'
        ));
        $huntVouchers = array_values(array_filter(
            $flashSales->getHuntableVouchers($user),
            fn (array $v) => in_array($v['applies_to'], ['all', 'vps'], true)
        ));

        return Inertia::render('Store/Index', [
            'plans' => array_values($plans),
            'regions' => array_values($pricing->getRegions()),
            'billingCycles' => config('cloudsunny.billing_cycles', []),
            'availablePlanSlugs' => $hasProvider ? array_keys($plans) : [],
            'isOutOfStock' => $outOfStock,
            'flashSales' => $vpsFlashSales,
            'huntVouchers' => $huntVouchers,
        ]);
    }

    public function create(string $plan, CloudSunnyPricingService $pricing, FlashSaleService $flashSales)
    {
        $outOfStock = Cache::remember('setting_vps_out_of_stock', 3600, function () {
            return Schema::hasTable('settings')
                && Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        });
        if ($outOfStock) {
            return redirect()
                ->route('store.index')
                ->with('error', 'Hệ thống tạm thời hết tài nguyên Cloud VPS. Chức năng khởi tạo VPS mới đang tạm khóa.');
        }

        $user = Auth::user();
        $allPlans = $pricing->getPublicPlans($user);
        $planData = $pricing->getPlan($plan, $user);
        if (!$planData) {
            abort(404);
        }
        unset($planData['provider_price_per_month'], $planData['provider_data_pricing']);

        $addonRates = $pricing->getAddonRates();

        return Inertia::render('Store/Create', [
            'selectedPlanSlug' => $planData['slug'],
            'plan' => $planData,
            'allPlans' => array_values($allPlans),
            'regions' => array_values($pricing->getRegions()),
            'billingCycles' => config('cloudsunny.billing_cycles', []),
            'images' => array_values($pricing->getImages((int) ($planData['product_id'] ?? 1))),
            'addonPrices' => [
                'cpu_monthly' => $addonRates['cpu_monthly'],
                'ram_monthly' => $addonRates['ram_monthly'],
                'disk_10gb_monthly' => $addonRates['disk_10gb_monthly'],
            ],
            'claimedVouchers' => $flashSales->getUserClaimedVouchers($user, 'vps'),
        ]);
    }

    public function store(
        Request $request,
        CloudSunnyPricingService $pricing,
        CloudSunnyAccountRouter $router,
        CloudSunnyApiService $api,
        WalletService $wallet,
        PromotionEngine $promotions,
        FlashSaleService $flashSales
    ) {
        $plans = array_keys($pricing->getPlans());
        $cycles = array_keys(config('cloudsunny.billing_cycles', []));

        $validated = $request->validate([
            'plan' => 'required|in:' . implode(',', $plans),
            'region' => 'nullable|string|max:32',
            'duration' => 'required|in:' . implode(',', $cycles),
            'quantity' => 'nullable|integer|min:1|max:10',
            'label' => 'required|string|min:3|max:32|regex:/^[a-zA-Z0-9\-]+$/',
            'image' => 'required|integer|min:1',
            'addon_cpu' => 'nullable|integer|min:0|max:16',
            'addon_ram' => 'nullable|integer|min:0|max:64',
            'addon_disk' => 'nullable|integer|min:0|max:1000',
            'coupon_code' => 'nullable|string|max:48',
            'expected_total' => 'nullable|integer|min:0',
        ], [
            'label.regex' => 'Tên máy chủ (Hostname) chỉ gồm chữ cái, chữ số và dấu gạch ngang.',
            'quantity.max' => 'Mỗi đơn khởi tạo tối đa 10 máy chủ Cloud VPS cùng lúc.',
        ]);

        $user = Auth::user();
        $plan = $pricing->getPlan($validated['plan'], $user);
        if (!$plan) {
            return back()->with('error', 'Gói cấu hình không hợp lệ.');
        }

        $billingCycle = $validated['duration'];
        $months = $pricing->monthsForCycle($billingCycle);
        $quantity = max(1, (int) ($validated['quantity'] ?? 1));
        $addonCpu = (int) ($validated['addon_cpu'] ?? 0);
        $addonRam = (int) ($validated['addon_ram'] ?? 0);
        $addonDisk = (int) ($validated['addon_disk'] ?? 0);
        $couponCode = $validated['coupon_code'] ?? null;

        if ($addonDisk > 0 && $addonDisk % 10 !== 0) {
            return back()->withInput()->with('error', 'Dung lượng ổ cứng nâng cấp phải là bội số của 10 GB.');
        }

        $quote = $pricing->quoteCycleForPlan(
            $plan,
            $billingCycle,
            $user,
            $addonCpu,
            $addonRam,
            $addonDisk,
            false,
            $couponCode,
            $quantity
        );

        if (!empty($couponCode) && isset($quote['coupon']) && !$quote['coupon']['valid']) {
            return back()->withInput()->with('error', $quote['coupon']['message']);
        }

        $totalPrice = $quote['final_price'];
        $providerCost = $quote['provider_cost'];

        // Chốt chặn an toàn: nếu giá trên màn hình lúc bấm khác với tổng tiền thực tế sau khi kéo cấu hình/số lượng, yêu cầu xác nhận lại
        if ($request->filled('expected_total') && (int) $request->input('expected_total') !== (int) $totalPrice) {
            return back()->withInput()->with(
                'error',
                'Tổng thanh toán cho cấu hình mới là ' . number_format($totalPrice, 0, ',', '.') . 'đ (khác với mức giá trước đó). Vui lòng kiểm tra lại và bấm Thanh toán.'
            );
        }

        if ($user->balance < $totalPrice) {
            return back()->withInput()->with('error', 'Số dư ví không đủ (' . number_format($totalPrice, 0, ',', '.') . 'đ). Vui lòng nạp thêm số dư.');
        }

        // Chọn account gốc theo đúng tổng GIÁ VỐN GỐC ($providerCost) của toàn bộ số lượng máy chủ
        $account = $router->pickForOrder($providerCost);
        if (!$account) {
            return back()->withInput()->with('error', 'Cụm máy chủ hiện tạm hết slot khởi tạo tự động cho số lượng này. Vui lòng thử lại sau ít phút.');
        }

        $expiresAt = Carbon::now()->addMonths($months);
        $unitPaid = (int) round($totalPrice / $quantity);
        $unitProviderCost = (int) round($providerCost / $quantity);
        $baseLabel = rtrim(substr($validated['label'], 0, 28), '-');

        /** @var array<int, VpsInstance> $createdList */
        $createdList = [];
        for ($i = 0; $i < $quantity; $i++) {
            $itemLabel = $quantity > 1 ? "{$baseLabel}-" . ($i + 1) : $validated['label'];
            $createdList[] = VpsInstance::create([
                'user_id' => $user->id,
                'cloudsunny_account_id' => $account->id,
                'label' => $itemLabel,
                'root_password' => '',
                'region' => $validated['region'] ?? 'vn',
                'plan_id' => $validated['plan'],
                'status' => 'Đang khởi tạo...',
                'cpu' => (int) $plan['cores'] + $addonCpu,
                'ram' => (int) $plan['ram'] + $addonRam,
                'disk' => (int) $plan['disk'] + $addonDisk,
                'cost_monthly_usd' => 0,
                'provider_cost' => $unitProviderCost,
                'provider_product_id' => (int) ($plan['product_id'] ?? 0),
                'provider_os_id' => (int) $validated['image'],
                'billing_cycle' => $billingCycle,
                'paid_amount' => $unitPaid,
                'expires_at' => $expiresAt,
            ]);
        }

        $primaryVps = $createdList[0];

        try {
            $appliedCoupon = ($quote['coupon']['valid'] ?? false) ? $quote['coupon']['code'] : null;
            $flashSaleInfo = $quote['flash_sale'] ?? null;
            $descLabel = $quantity > 1
                ? "Khởi tạo {$quantity} VPS: {$baseLabel}-1..{$quantity} ({$plan['name']} - {$billingCycle})"
                : "Khởi tạo VPS: {$validated['label']} ({$plan['name']} - {$billingCycle})";
            if (!empty($flashSaleInfo['name'])) {
                $descLabel .= " [Flash Sale: {$flashSaleInfo['name']}]";
            }

            $transaction = DB::transaction(function () use (
                $wallet,
                $user,
                $totalPrice,
                $primaryVps,
                $quantity,
                $quote,
                $appliedCoupon,
                $providerCost,
                $descLabel,
                $flashSaleInfo,
                $flashSales,
                $promotions
            ) {
                $txn = $wallet->debit($user, $totalPrice, [
                    'idempotency_key' => "buy_vps_{$primaryVps->id}_q{$quantity}",
                    'vps_instance_id' => $primaryVps->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'coupon_code' => $appliedCoupon,
                    'type' => 'buy',
                    'provider_cost' => $providerCost,
                    'discount_amount' => $quote['discount_amount'],
                    'description' => $descLabel,
                ]);

                if (!empty($flashSaleInfo['id'])) {
                    $flashSales->reserveAndConsumeSlot(
                        (int) $flashSaleInfo['id'],
                        $user,
                        $quantity,
                        $txn->id,
                        (int) ($flashSaleInfo['discount_amount'] ?? 0)
                    );
                }

                if ($appliedCoupon && ($quote['coupon']['discount_amount'] ?? 0) > 0) {
                    $promotions->recordCouponUsage($appliedCoupon, $user, $txn, (int) $quote['coupon']['discount_amount']);
                }

                return $txn;
            });
        } catch (\Throwable $e) {
            VpsInstance::whereIn('id', collect($createdList)->pluck('id'))->delete();
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->withInput()->with('error', 'Số dư ví không đủ để hoàn tất thanh toán.');
            }
            return back()->withInput()->with('error', CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        try {
            $remoteList = $api->forAccount($account)->createVpsOrder(
                (int) ($plan['product_id'] ?? 0),
                $billingCycle,
                (int) $validated['image'],
                $quantity,
                $addonCpu,
                $addonRam,
                $addonDisk
            );

            $remoteItems = is_array($remoteList) && isset($remoteList[0]) && is_array($remoteList[0])
                ? array_values($remoteList)
                : (is_array($remoteList) ? [$remoteList] : []);

            foreach ($createdList as $idx => $vpsItem) {
                $remote = $remoteItems[$idx] ?? ($quantity === 1 ? ($remoteItems[0] ?? []) : []);
                if (!empty($remote)) {
                    $vpsItem->update([
                        'provider_vps_id' => $this->remoteValue($remote, ['id', 'vps_id', 'server_id', 'service_id'], null),
                        'provider_order_id' => $this->remoteValue($remote, ['order_id', 'orderId'], null),
                        'public_ip' => $this->remoteValue($remote, ['ip', 'ip_address', 'ipv4', 'main_ip', 'public_ip'], null),
                        'login_username' => $this->remoteValue($remote, ['username', 'user', 'login_username', 'login_user'], 'root'),
                        'root_password' => $this->remoteValue($remote, ['password', 'root_password', 'login_password'], ''),
                        'status' => VpsInstance::mapUpstreamStatus((string) $this->remoteValue($remote, ['status', 'state', 'power_status', 'vm_status', 'trang_thai'], 'progressing')),
                        'provider_payload' => $remote,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Upstream VPS create failed', ['vps_id' => $primaryVps->id, 'qty' => $quantity, 'msg' => $e->getMessage()]);

            $safeReason = CloudSunnyApiService::sanitizeErrorMessage($e->getMessage());
            $wallet->refundTransaction($transaction, $safeReason);
            $flashSales->releaseSlotByTransaction($transaction->id);
            VpsInstance::whereIn('id', collect($createdList)->pluck('id'))->update(['status' => 'Lỗi - đã hoàn tiền']);

            return redirect()->route('vps.index')->with('error', 'Khởi tạo máy chủ không thành công, hệ thống đã hoàn 100% tiền về ví của bạn. (' . $safeReason . ')');
        }

        app(AffiliateService::class)->processCommission($user, $totalPrice, 'buy_vps', "txn_{$transaction->id}");

        if ($quantity > 1) {
            return redirect()->route('vps.index')->with('success', "Đã khởi tạo thành công {$quantity} máy chủ Cloud VPS! Hệ thống đang cài đặt HĐH và cấp phát IP tự động.");
        }

        return redirect()->route('vps.show', $primaryVps)->with('success', 'Máy chủ đang được cấp phát tự động. Địa chỉ IP và thông tin truy cập sẽ hiển thị sau ít phút.');
    }

    private function remoteValue(array $remote, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $remote) && $remote[$key] !== null && $remote[$key] !== '') {
                return $remote[$key];
            }
        }

        return $default;
    }
}

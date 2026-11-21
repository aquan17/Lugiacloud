<?php

namespace App\Http\Controllers\Api\Reseller;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessResellerVpsCreation;
use App\Models\ProxyInstance;
use App\Models\VpsInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ResellerEndpointController extends Controller
{
    public function __construct(
        protected CloudSunnyPricingService $pricingService,
        protected CloudSunnyProxyPricingService $proxyPricingService,
        protected CloudSunnyApiService $apiService,
        protected WalletService $wallet
    ) {
    }

    public function profile()
    {
        $user = Auth::user();

        $vpsActive = VpsInstance::where('user_id', $user->id)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Lỗi', 'Lỗi - đã hoàn tiền'])
            ->count();

        $proxyActive = ProxyInstance::where('user_id', $user->id)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt', 'Lỗi', 'Lỗi - đã hoàn tiền'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'email' => $user->email,
                'balance' => (int) $user->balance,
                'vps_active' => $vpsActive,
                'proxy_active' => $proxyActive,
                'discount_percent' => (int) $user->discount_percent,
                'proxy_discount_percent' => (int) $user->proxy_discount_percent,
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    public function plans()
    {
        $user = Auth::user();
        $plansData = $this->pricingService->getPlans($user);
        $addonRates = $this->pricingService->getAddonRates();

        $firstPlan = reset($plansData);
        $firstProductId = $firstPlan ? ($firstPlan['product_id'] ?? 1) : 1;
        $imagesData = $this->pricingService->getImages((int) $firstProductId);

        $osList = [];
        foreach ($imagesData as $osId => $os) {
            $osList[] = [
                'id' => $osId,
                'name' => $os['label'] ?? null,
                'type' => strtolower($os['group'] ?? 'linux'),
            ];
        }

        $plans = [];
        foreach ($plansData as $slug => $plan) {
            $plans[] = [
                'id' => $plan['product_id'] ?? $slug,
                'slug' => $slug,
                'name' => $plan['name'] ?? null,
                'cpu' => $plan['cores'] ?? 1,
                'ram' => $plan['ram'] ?? 1,
                'disk' => $plan['disk'] ?? 25,
                'price_monthly' => $plan['price_per_month'] ?? 0,
                'list_price_monthly' => $plan['list_price_per_month'] ?? $plan['price_per_month'] ?? 0,
            ];
        }

        $changeIpPrice = max(
            (int) ($addonRates['cost_change_ip'] ?? 20000),
            $this->pricingService->applyUserDiscount((int) ($addonRates['change_ip'] ?? 30000), $user)
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'location' => 'Vietnam (VN)',
                'durations' => [1, 2, 3, 6, 12],
                'addons' => [
                    'cpu_monthly' => (int) ($addonRates['cpu_monthly'] ?? 25000),
                    'ram_monthly' => (int) ($addonRates['ram_monthly'] ?? 25000),
                    'disk_10gb_monthly' => (int) ($addonRates['disk_10gb_monthly'] ?? 15000),
                    'change_ip' => $changeIpPrice,
                    'change_ip_enabled' => (bool) ($addonRates['change_ip_active'] ?? true),
                ],
                'os_list' => $osList,
                'plans' => $plans,
            ],
        ]);
    }

    public function createVps(Request $request)
    {
        $request->validate([
            'plan_id' => 'required',
            'os_id' => 'required|integer',
            'duration' => 'required|integer|in:1,2,3,6,12',
            'hostname' => 'nullable|string|max:64',
        ]);

        $user = Auth::user();
        $planId = (string) $request->plan_id;
        $osId = (int) $request->os_id;
        $duration = (int) $request->duration;
        $hostname = $request->hostname ?: ('server-' . time());

        $cycleCode = $this->durationToCycle($duration);

        $selectedPlan = $this->pricingService->getPlan($planId, $user);
        if (!$selectedPlan) {
            return response()->json(['status' => 'error', 'message' => 'Gói cấu hình không hợp lệ.'], 400);
        }

        $quote = $this->pricingService->quoteCycleForPlan($selectedPlan, $cycleCode, $user);
        $totalPrice = $quote['final_price'];
        $providerCost = $quote['provider_cost'];

        $orderId = time() . random_int(100, 999);

        $vpsInstance = VpsInstance::create([
            'user_id' => $user->id,
            'label' => $hostname,
            'plan_id' => $selectedPlan['slug'] ?? $planId,
            'provider_product_id' => (int) ($selectedPlan['product_id'] ?? $planId),
            'cpu' => (int) ($selectedPlan['cores'] ?? 1),
            'ram' => (int) ($selectedPlan['ram'] ?? 1),
            'disk' => (int) ($selectedPlan['disk'] ?? 25),
            'paid_amount' => $totalPrice,
            'provider_cost' => $providerCost,
            'status' => 'Đang xử lý',
            'provider_os_id' => $osId,
            'billing_cycle' => $cycleCode,
            'expires_at' => now()->addMonths($duration),
            'provider_order_id' => $orderId,
            'root_password' => '',
            'login_username' => 'root',
            'region' => 'vn',
            'cost_monthly_usd' => 0,
        ]);

        try {
            $txn = $this->wallet->debit($user, $totalPrice, [
                'idempotency_key' => "reseller_buy_{$orderId}",
                'vps_instance_id' => $vpsInstance->id,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'type' => 'buy',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Đại lý mua gói {$selectedPlan['name']} ({$cycleCode}) qua API",
            ]);
        } catch (RuntimeException $e) {
            $vpsInstance->delete();
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            return response()->json(['status' => 'error', 'message' => 'Lỗi thanh toán ví đại lý.'], 500);
        }

        ProcessResellerVpsCreation::dispatch(
            $vpsInstance->id,
            $user->id,
            (int) ($selectedPlan['product_id'] ?? $planId),
            $osId
        );

        return response()->json([
            'status' => 202,
            'message' => 'VPS đang được khởi tạo. Hệ thống sẽ tự động gọi Webhook khi hoàn tất.',
            'data' => [
                'order_id' => $orderId,
                'amount_deducted' => $totalPrice,
                'balance_left' => (int) $txn->balance_after,
                'status' => 'processing',
                'status_url' => url("/api/v1/vps/{$orderId}/status"),
            ],
        ], 202);
    }

    public function vpsStatus($orderId)
    {
        $user = Auth::user();
        $vps = VpsInstance::where('user_id', $user->id)->where('provider_order_id', $orderId)->first();

        if (!$vps) {
            return response()->json(['status' => 404, 'message' => 'Order ID không tồn tại.'], 404);
        }

        $state = $vps->normalizedState();
        $apiStatus = match ($state) {
            'running' => 'active',
            'stopped' => 'stopped',
            'expired' => 'expired',
            'failed', 'deleted' => 'failed',
            default => 'processing',
        };

        $vpsInfo = null;
        if (!empty($vps->public_ip)) {
            $osName = 'Linux';
            if ($vps->provider_os_id) {
                $images = $this->pricingService->getImages((int) ($vps->provider_product_id ?: 1));
                $osName = $images[$vps->provider_os_id]['label'] ?? $osName;
            }

            $vpsInfo = [
                'ip_address' => $vps->public_ip,
                'username' => $vps->login_username ?: 'root',
                'password' => $vps->root_password,
                'cpu' => (int) $vps->cpu,
                'ram' => (int) $vps->ram,
                'disk' => (int) $vps->disk,
                'os_name' => $osName,
                'created_at' => $vps->created_at->toIso8601String(),
                'expired_at' => $vps->expires_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_id' => $orderId,
                'status' => $apiStatus,
                'status_label' => $vps->displayStatus(),
                'vps_info' => $vpsInfo,
            ],
        ]);
    }

    public function actionVps(Request $request, $orderId)
    {
        $vps = VpsInstance::where('user_id', Auth::id())->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        $validated = $request->validate(['action' => 'required|in:start,stop,restart']);

        try {
            $apiClient = $this->apiService->forAccount($vps->cloudSunnyAccount);
            $providerVpsId = (int) $vps->provider_vps_id;

            match ($validated['action']) {
                'start' => [$apiClient->bootVps($providerVpsId), $vps->update(['status' => 'Sẵn sàng'])],
                'stop' => [$apiClient->shutdownVps($providerVpsId), $vps->update(['status' => 'Đã tắt'])],
                'restart' => [$apiClient->rebootVps($providerVpsId), $vps->update(['status' => 'Đang khởi động lại'])],
            };

            return response()->json(['status' => 'success', 'message' => 'Lệnh đã được thực thi.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function renewVps(Request $request, $orderId)
    {
        $user = Auth::user();
        $vps = VpsInstance::where('user_id', $user->id)->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->isOverdue5Days()) {
            return response()->json(['status' => 'error', 'message' => 'VPS đã quá hạn quá 5 ngày và bị thu hồi, không thể gia hạn.'], 400);
        }

        $validated = $request->validate(['duration' => 'required|integer|in:1,2,3,6,12']);
        $cycleCode = $this->durationToCycle((int) $validated['duration']);

        $plan = $this->pricingService->getPlan((string) $vps->plan_id, $user);
        if (!$plan) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy cấu hình gói.'], 400);
        }

        $addonCpu = max(0, (int) $vps->cpu - (int) $plan['cores']);
        $addonRam = max(0, (int) $vps->ram - (int) $plan['ram']);
        $addonDisk = max(0, (int) $vps->disk - (int) $plan['disk']);

        $quote = $this->pricingService->quoteCycleForPlan($plan, $cycleCode, $user, $addonCpu, $addonRam, $addonDisk, true);
        $amount = $quote['final_price'];
        $providerCost = $quote['provider_cost'];
        $months = $this->pricingService->monthsForCycle($cycleCode);

        $transaction = null;
        try {
            $transaction = $this->wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'type' => 'renew',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Gia hạn VPS API: {$vps->label} ({$cycleCode})",
            ]);

            $this->apiService->forAccount($vps->cloudSunnyAccount)->renewVps((int) $vps->provider_vps_id, $cycleCode);

            $baseDate = $vps->expires_at && $vps->expires_at->isFuture() ? $vps->expires_at->copy() : Carbon::now();
            $vps->update([
                'billing_cycle' => $cycleCode,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'expires_at' => $baseDate->addMonths($months),
                'status' => $vps->status === 'Hết hạn' ? 'Sẵn sàng' : $vps->status,
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'renew_vps', "txn_{$transaction->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Gia hạn thành công.',
                'data' => [
                    'amount_deducted' => $amount,
                    'expired_at' => $vps->expires_at->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            if ($transaction) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        } catch (\Throwable $e) {
            if ($transaction) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function rebuildVps(Request $request, $orderId)
    {
        $vps = VpsInstance::where('user_id', Auth::id())->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        $validated = $request->validate(['os_id' => 'required|integer|min:1']);

        try {
            $res = $this->apiService->forAccount($vps->cloudSunnyAccount)->rebuildVps((int) $vps->provider_vps_id, (int) $validated['os_id']);
            $info = is_array($res) ? ($res[0] ?? $res) : [];
            $update = [
                'provider_os_id' => (int) $validated['os_id'],
                'status' => 'Đang cài đặt lại...',
            ];
            if (!empty($info['password'])) {
                $update['root_password'] = $info['password'];
            }
            $vps->update($update);

            return response()->json(['status' => 'success', 'message' => 'Đã gửi yêu cầu cài lại OS.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function upgradeVps(Request $request, $orderId)
    {
        $user = Auth::user();
        $vps = VpsInstance::where('user_id', $user->id)->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->isOverdue5Days() || $vps->isExpired()) {
            return response()->json(['status' => 'error', 'message' => 'VPS đã hết hạn hoặc bị hủy, không thể nâng cấp.'], 400);
        }

        $validated = $request->validate([
            'addon_cpu' => 'nullable|integer|min:0|max:16',
            'addon_ram' => 'nullable|integer|min:0|max:64',
            'addon_disk' => 'nullable|integer|min:0|max:1000',
        ]);

        $addonCpu = (int) ($validated['addon_cpu'] ?? 0);
        $addonRam = (int) ($validated['addon_ram'] ?? 0);
        $addonDisk = (int) ($validated['addon_disk'] ?? 0);

        if ($addonCpu < 1 && $addonRam < 1 && $addonDisk < 1) {
            return response()->json(['status' => 'error', 'message' => 'Chọn CPU, RAM hoặc Disk cần nâng cấp.'], 400);
        }
        if ($addonDisk > 0 && $addonDisk % 10 !== 0) {
            return response()->json(['status' => 'error', 'message' => 'Disk nâng cấp phải là bội số của 10GB.'], 400);
        }

        $quote = $this->pricingService->quoteMidCycleUpgrade($vps, $addonCpu, $addonRam, $addonDisk, $user);
        $amount = $quote['final_price'];
        $providerCost = $quote['provider_cost'];

        $transaction = null;
        try {
            $transaction = $this->wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'type' => 'upgrade',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Nâng cấp VPS API: {$vps->label} (+{$addonCpu}C/+{$addonRam}G/+{$addonDisk}GB)",
            ]);

            $this->apiService->forAccount($vps->cloudSunnyAccount)->upgradeVps((int) $vps->provider_vps_id, $addonCpu, $addonRam, $addonDisk);

            $vps->update([
                'cpu' => (int) $vps->cpu + $addonCpu,
                'ram' => (int) $vps->ram + $addonRam,
                'disk' => (int) $vps->disk + $addonDisk,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'status' => 'Đang khởi động lại',
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'upgrade_vps', "txn_{$transaction->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi yêu cầu nâng cấp VPS.',
                'data' => [
                    'amount_deducted' => $amount,
                    'cpu' => (int) $vps->cpu,
                    'ram' => (int) $vps->ram,
                    'disk' => (int) $vps->disk,
                ],
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            if ($transaction) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        } catch (\Throwable $e) {
            if ($transaction) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    /**
     * Đổi mật khẩu OS cho VPS qua Reseller API.
     */
    public function changePasswordVps(Request $request, $orderId)
    {
        $vps = VpsInstance::where('user_id', Auth::id())->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->normalizedState() !== 'running') {
            return response()->json(['status' => 'error', 'message' => 'VPS cần đang ở trạng thái hoạt động (active) mới đổi được mật khẩu.'], 400);
        }

        $validated = $request->validate([
            'password_old' => 'nullable|string',
            'password_new' => [
                'required',
                'string',
                'min:12',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z0-9]+$/',
            ],
        ], [
            'password_new.min' => 'Mật khẩu mới cần tối thiểu 12 ký tự.',
            'password_new.regex' => 'Mật khẩu mới cần có chữ hoa, chữ thường, chữ số và không ký tự đặc biệt.',
        ]);

        $oldPass = !empty($validated['password_old']) ? $validated['password_old'] : (string) $vps->root_password;
        if ($oldPass === '') {
            return response()->json(['status' => 'error', 'message' => 'Thiếu mật khẩu hiện tại (password_old).'], 422);
        }

        try {
            $this->apiService->forAccount($vps->cloudSunnyAccount)->changePasswordVps(
                (int) $vps->provider_vps_id,
                $oldPass,
                $validated['password_new'],
                $validated['password_new']
            );
            $vps->update(['root_password' => $validated['password_new']]);

            return response()->json(['status' => 'success', 'message' => 'Đã cập nhật mật khẩu VPS thành công.']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    /**
     * Đổi địa chỉ IPv4 mới cho VPS (có tính phí, trừ ví Đại lý theo giá bán sau chiết khấu + chốt chặn giá vốn).
     */
    public function changeIpVps(Request $request, $orderId)
    {
        $user = Auth::user();
        $vps = VpsInstance::where('user_id', $user->id)->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->normalizedState() !== 'running') {
            return response()->json(['status' => 'error', 'message' => 'VPS cần đang ở trạng thái hoạt động (active) để đổi IPv4.'], 400);
        }

        $validated = $request->validate([
            'password_vps' => 'nullable|string|max:128',
        ]);
        $passwordVps = !empty($validated['password_vps']) ? $validated['password_vps'] : (string) $vps->root_password;
        if ($passwordVps === '') {
            return response()->json(['status' => 'error', 'message' => 'Vui lòng truyền mật khẩu hiện tại của VPS (password_vps).'], 422);
        }

        $addonRates = $this->pricingService->getAddonRates();
        if (empty($addonRates['change_ip_active'])) {
            return response()->json(['status' => 'error', 'message' => 'Chức năng đổi IPv4 đang tạm bảo trì.'], 503);
        }

        $apiClient = $this->apiService->forAccount($vps->cloudSunnyAccount);
        $providerCost = (int) ($addonRates['cost_change_ip'] ?? 20000);
        $retailBase = (int) ($addonRates['change_ip'] ?? 30000);

        try {
            $cfg = $apiClient->getChangeIpConfig((int) $vps->provider_vps_id);
            if (isset($cfg['enable']) && $cfg['enable'] === false) {
                return response()->json(['status' => 'error', 'message' => 'Chức năng đổi IPv4 đang tạm bảo trì.'], 503);
            }
            if (!empty($cfg['price'])) {
                $providerCost = (int) $cfg['price'];
            }
            if (is_array($cfg['vps'] ?? null) && ($cfg['vps']['can_change_ip'] ?? true) === false) {
                return response()->json([
                    'status' => 'error',
                    'message' => CloudSunnyApiService::sanitizeErrorMessage((string) ($cfg['vps']['reason'] ?? 'VPS chưa đủ điều kiện đổi IP.')),
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }

        $amount = max($providerCost, $this->pricingService->applyUserDiscount($retailBase, $user));
        $lock = Cache::lock("vps_change_ip_{$vps->id}", 135);
        if (!$lock->get()) {
            return response()->json(['status' => 'error', 'message' => 'VPS đang có một yêu cầu đổi IP khác chờ xử lý.'], 409);
        }

        $oldIp = $vps->public_ip;
        $transaction = null;
        try {
            $transaction = $this->wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'type' => 'change_ip',
                'provider_cost' => $providerCost,
                'discount_amount' => max(0, $retailBase - $amount),
                'description' => "Đổi IPv4 VPS API: {$vps->label}",
            ]);

            $apiClient->changeIpVps((int) $vps->provider_vps_id, $passwordVps);
            $vps->update([
                'status' => 'Đang đổi IP...',
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'change_ip_vps', "txn_{$transaction->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu đổi IPv4 đã được tiếp nhận. IP mới sẽ cập nhật sau 2–5 phút qua GET /vps/{order_id}/status.',
                'data' => [
                    'order_id' => $orderId,
                    'amount_deducted' => $amount,
                    'status' => 'processing',
                ],
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            if ($transaction) {
                $accepted = false;
                try {
                    $latest = $apiClient->getVps((int) $vps->provider_vps_id);
                    $st = strtolower((string) ($latest['status'] ?? ''));
                    $ip = (string) ($latest['ip'] ?? $latest['ip_address'] ?? '');
                    if ($st === 'change_ip' || ($oldIp && $ip !== '' && $ip !== $oldIp)) {
                        $accepted = true;
                        $vps->update(['public_ip' => $ip ?: $vps->public_ip, 'status' => VpsInstance::mapUpstreamStatus($st)]);
                    }
                } catch (\Throwable $ignore) {
                }
                if ($accepted) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Yêu cầu đổi IPv4 đã được tiếp nhận trên hạ tầng.',
                        'data' => ['order_id' => $orderId, 'amount_deducted' => $amount, 'status' => 'processing'],
                    ]);
                }
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        } catch (\Throwable $e) {
            if ($transaction) {
                $this->wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        } finally {
            $lock->release();
        }
    }

    /**
     * Mở rộng phân vùng SSD sau khi nâng cấp ổ cứng (miễn phí).
     */
    public function extendDiskVps(Request $request, $orderId)
    {
        $vps = VpsInstance::where('user_id', Auth::id())->where('provider_order_id', $orderId)->first();
        if (!$vps || !$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->normalizedState() !== 'running') {
            return response()->json(['status' => 'error', 'message' => 'VPS cần đang ở trạng thái hoạt động (active) mới mở rộng được ổ cứng.'], 400);
        }

        $validated = $request->validate(['password_vps' => 'nullable|string|max:128']);
        $passwordVps = !empty($validated['password_vps']) ? $validated['password_vps'] : (string) $vps->root_password;
        if ($passwordVps === '') {
            return response()->json(['status' => 'error', 'message' => 'Vui lòng truyền mật khẩu hiện tại của VPS (password_vps).'], 422);
        }

        try {
            $this->apiService->forAccount($vps->cloudSunnyAccount)->extendDiskVps((int) $vps->provider_vps_id, $passwordVps);
            $vps->update(['status' => 'Đang mở rộng ổ cứng...']);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi lệnh mở rộng phân vùng SSD. Máy chủ sẽ tạm bảo trì dưới 5 phút rồi tự bật lại.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function deleteVps($orderId)
    {
        $vps = VpsInstance::where('user_id', Auth::id())->where('provider_order_id', $orderId)->first();
        if (!$vps) {
            return response()->json(['status' => 'error', 'message' => 'Order ID không tồn tại.'], 404);
        }

        if ($vps->cloudSunnyAccount && $vps->provider_vps_id) {
            try {
                $this->apiService->forAccount($vps->cloudSunnyAccount)->shutdownVps((int) $vps->provider_vps_id);
            } catch (\Throwable $e) {
                Log::warning('Provider shutdown failed during API delete', ['id' => $vps->id]);
            }
        }

        $vps->update([
            'status' => 'Đã xóa',
            'is_auto_renew' => false,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Đã xóa VPS thành công.']);
    }

    /* =========================================================================
     * NHÓM RESELLER API PROXY IPv4
     * ========================================================================= */

    public function proxyPlans()
    {
        $user = Auth::user();
        $products = $this->proxyPricingService->getPublicCatalogProducts($user);

        $plans = collect($products)->map(fn (array $p) => [
            'id' => (int) $p['id'],
            'slug' => $p['slug'],
            'name' => $p['title'],
            'category' => $p['category'],
            'dedicated' => (bool) $p['dedicated'],
            'protocols' => $p['protocols'] ?? ['HTTP', 'SOCKS5'],
            'country' => $p['country'] ?? 'VN',
            'price_monthly' => (int) $p['price'],
            'cycle_prices' => collect($p['cycle_quotes'] ?? [])->mapWithKeys(fn ($q, $k) => [$k => (int) $q['final_price']])->all(),
        ])->values()->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'cycles' => config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']),
                'plans' => $plans,
            ],
        ]);
    }

    public function createProxy(Request $request, CloudSunnyAccountRouter $router)
    {
        $allowedCycles = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'billing_cycle' => 'nullable|in:' . implode(',', $allowedCycles),
            'duration' => 'nullable|integer|in:1,2,3,6,12',
            'quantity' => 'required|integer|min:1|max:10',
            'type' => 'required|in:HTTP,SOCKS5',
            'username' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'password' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
        ]);

        $user = Auth::user();
        $cycle = $validated['billing_cycle'] ?? $this->durationToCycle((int) ($validated['duration'] ?? 1));
        $product = $this->proxyPricingService->findProductById((int) $validated['product_id'], $user);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Gói Proxy không tồn tại.'], 400);
        }

        $quantity = (int) $validated['quantity'];
        $quote = $this->proxyPricingService->quoteProxyOrder($product, $quantity, $cycle, $user, false);
        $totalPrice = $quote['final_price'];
        $totalProviderCost = $quote['provider_cost'];

        $account = $router->pickForOrder($totalProviderCost);
        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Kho IP đang bảo trì hạn mức cấp phát.'], 503);
        }

        $authUser = !empty($validated['username']) ? $validated['username'] : strtolower(Str::random(8));
        $authPass = !empty($validated['password']) ? $validated['password'] : Str::random(12);
        $typeProxy = strtoupper($validated['type']);

        // Pre-check tồn kho trước khi trừ tiền ví Đại lý
        try {
            $pre = $this->apiService->forAccount($account)->checkPrice('create_proxy', [
                'product_id' => (int) $product['id'],
                'billing_cycle' => $cycle,
                'quantity' => $quantity,
                'type' => $typeProxy,
                'username' => $authUser,
                'password' => $authPass,
            ]);
            if (isset($pre['enough_credit']) && $pre['enough_credit'] === false) {
                return response()->json(['status' => 'error', 'message' => 'Kho IP đang tạm cập nhật hạn mức cấp phát.'], 503);
            }
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 400);
        } catch (\Throwable $e) {
        }

        $months = $this->pricingService->monthsForCycle($cycle);
        $expiresAt = Carbon::now()->addMonths($months);
        $proxyIds = [];

        try {
            DB::transaction(function () use ($user, $account, $product, $cycle, $quantity, $totalPrice, $expiresAt, $typeProxy, $authUser, $authPass, &$proxyIds) {
                $unitPaid = (int) round($totalPrice / max(1, $quantity));
                for ($i = 0; $i < $quantity; $i++) {
                    $p = ProxyInstance::create([
                        'user_id' => $user->id,
                        'cloudsunny_account_id' => $account->id,
                        'product_id' => (int) $product['id'],
                        'type_proxy' => $typeProxy,
                        'status' => 'Đang khởi tạo...',
                        'billing_cycle' => $cycle,
                        'paid_amount' => $unitPaid,
                        'expires_at' => $expiresAt,
                        'username' => $authUser,
                        'password' => $authPass,
                        'sock5_username' => $typeProxy === 'SOCKS5' ? $authUser : null,
                        'sock5_password' => $typeProxy === 'SOCKS5' ? $authPass : null,
                    ]);
                    $proxyIds[] = $p->id;
                }
            });

            $transaction = $this->wallet->debit($user, $totalPrice, [
                'idempotency_key' => 'reseller_buy_proxy_' . implode('_', array_slice($proxyIds, 0, 3)) . '_' . count($proxyIds),
                'proxy_instance_id' => count($proxyIds) === 1 ? $proxyIds[0] : null,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'type' => 'buy',
                'provider_cost' => $totalProviderCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Đại lý mua Proxy API: {$product['title']} x{$quantity} ({$cycle})",
            ]);
        } catch (RuntimeException $e) {
            ProxyInstance::whereIn('id', $proxyIds)->delete();
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }

        try {
            $remoteList = $this->apiService->forAccount($account)->createProxy([
                'product_id' => (int) $product['id'],
                'billing_cycle' => $cycle,
                'quantity' => $quantity,
                'type' => $typeProxy,
                'username' => $authUser,
                'password' => $authPass,
            ]);
            if (isset($remoteList['id']) || isset($remoteList['ip'])) {
                $remoteList = [$remoteList];
            }
            if (!is_array($remoteList) || count($remoteList) < $quantity) {
                throw new RuntimeException('Cụm cấp phát IP chưa trả đủ số lượng Proxy yêu cầu.');
            }

            $instances = ProxyInstance::whereIn('id', $proxyIds)->orderBy('id')->get();
            $createdPayload = [];
            foreach ($instances as $idx => $proxy) {
                $item = $remoteList[$idx] ?? [];
                $ip = !empty($item['ip']) ? $item['ip'] : null;
                $port = $item['port'] ?? null;
                $isSocks5 = $typeProxy === 'SOCKS5';
                $proxy->update([
                    'provider_proxy_id' => $item['id'] ?? null,
                    'ip' => $ip,
                    'port' => $port,
                    'sock5_port' => $isSocks5 ? $port : null,
                    'status' => $ip ? 'Hoạt động' : 'Đang khởi tạo',
                ]);
                $createdPayload[] = $this->formatProxyApiItem($proxy);
            }

            app(AffiliateService::class)->processCommission($user, $totalPrice, 'buy_proxy', "txn_{$transaction->id}");

            return response()->json([
                'status' => 'success',
                'message' => "Đã tạo thành công {$quantity} Proxy {$typeProxy}.",
                'data' => [
                    'amount_deducted' => $totalPrice,
                    'balance_left' => (int) $transaction->balance_after,
                    'proxies' => $createdPayload,
                ],
            ], 201);
        } catch (\Throwable $e) {
            $safe = CloudSunnyApiService::sanitizeErrorMessage($e->getMessage());
            $this->wallet->refundTransaction($transaction, $safe);
            ProxyInstance::whereIn('id', $proxyIds)->update(['status' => 'Lỗi - đã hoàn tiền']);

            return response()->json(['status' => 'error', 'message' => 'Khởi tạo Proxy thất bại (đã hoàn 100% tiền): ' . $safe], 500);
        }
    }

    public function proxyStatus(int $proxyId)
    {
        $proxy = ProxyInstance::where('user_id', Auth::id())->where('id', $proxyId)->first();
        if (!$proxy) {
            return response()->json(['status' => 404, 'message' => 'Proxy ID không tồn tại.'], 404);
        }

        if ($proxy->normalizedState() !== 'deleted' && $proxy->provider_proxy_id && $proxy->cloudSunnyAccount) {
            if (empty($proxy->ip) || Cache::add("reseller_proxy_poll_{$proxy->id}", true, 5)) {
                try {
                    $remote = $this->apiService->forAccount($proxy->cloudSunnyAccount)->getProxy((int) $proxy->provider_proxy_id);
                    if ($remote) {
                        $this->syncProxyFromRemote($proxy, $remote);
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatProxyApiItem($proxy),
        ]);
    }

    public function renewProxy(Request $request, int $proxyId)
    {
        $user = Auth::user();
        $proxy = ProxyInstance::where('user_id', $user->id)->where('id', $proxyId)->first();
        if (!$proxy || !$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
            return response()->json(['status' => 'error', 'message' => 'Proxy ID không tồn tại.'], 404);
        }
        if ($proxy->normalizedState() === 'deleted') {
            return response()->json(['status' => 'error', 'message' => 'Proxy đã hết hạn/hủy, không thể gia hạn.'], 400);
        }

        $allowedCycles = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $validated = $request->validate([
            'billing_cycle' => 'nullable|in:' . implode(',', $allowedCycles),
            'duration' => 'nullable|integer|in:1,2,3,6,12',
        ]);
        $cycle = $validated['billing_cycle'] ?? $this->durationToCycle((int) ($validated['duration'] ?? 1));

        $product = $this->proxyPricingService->findProductById((int) $proxy->product_id, $user);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Không tìm thấy cấu hình giá Proxy.'], 400);
        }

        $quote = $this->proxyPricingService->quoteProxyOrder($product, 1, $cycle, $user, true);
        $amount = $quote['final_price'];
        $cost = $quote['provider_cost'];
        $months = $this->pricingService->monthsForCycle($cycle);

        $txn = null;
        try {
            $txn = $this->wallet->debit($user, $amount, [
                'proxy_instance_id' => $proxy->id,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'type' => 'renew',
                'provider_cost' => $cost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Gia hạn Proxy API #{$proxy->id} ({$cycle})",
            ]);

            $client = $this->apiService->forAccount($proxy->cloudSunnyAccount);
            $client->actionProxy([(int) $proxy->provider_proxy_id], 'renew', ['billing_cycle' => $cycle]);

            $base = $proxy->expires_at && $proxy->expires_at->isFuture() ? $proxy->expires_at->copy() : Carbon::now();
            $proxy->update([
                'billing_cycle' => $cycle,
                'expires_at' => $base->addMonths($months),
                'paid_amount' => (int) $proxy->paid_amount + $amount,
                'status' => 'Hoạt động',
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'renew_proxy', "txn_{$txn->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Gia hạn Proxy thành công.',
                'data' => [
                    'proxy_id' => $proxy->id,
                    'amount_deducted' => $amount,
                    'expired_at' => $proxy->expires_at?->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return response()->json(['status' => 'error', 'message' => 'Số dư không đủ. Vui lòng nạp thêm tiền.'], 402);
            }
            if ($txn) {
                $this->wallet->refundTransaction($txn, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        } catch (\Throwable $e) {
            if ($txn) {
                $this->wallet->refundTransaction($txn, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function updateProxyCredentials(Request $request, int $proxyId)
    {
        $proxy = ProxyInstance::where('user_id', Auth::id())->where('id', $proxyId)->first();
        if (!$proxy || !$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount || $proxy->normalizedState() === 'deleted') {
            return response()->json(['status' => 'error', 'message' => 'Proxy không tồn tại hoặc đã hủy.'], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        try {
            $this->apiService->forAccount($proxy->cloudSunnyAccount)->editProxy(
                (int) $proxy->provider_proxy_id,
                $validated['username'],
                $validated['password']
            );
            $isSocks5 = strtoupper((string) $proxy->type_proxy) === 'SOCKS5';
            $update = ['username' => $validated['username'], 'password' => $validated['password']];
            if ($isSocks5) {
                $update['sock5_username'] = $validated['username'];
                $update['sock5_password'] = $validated['password'];
            }
            $proxy->update($update);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã cập nhật tài khoản & mật khẩu Proxy.',
                'data' => $this->formatProxyApiItem($proxy),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function changeProxyType(Request $request, int $proxyId)
    {
        $proxy = ProxyInstance::where('user_id', Auth::id())->where('id', $proxyId)->first();
        if (!$proxy || !$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount || $proxy->normalizedState() === 'deleted') {
            return response()->json(['status' => 'error', 'message' => 'Proxy không tồn tại hoặc đã hủy.'], 404);
        }

        $validated = $request->validate(['type' => 'required|in:HTTP,SOCKS5']);
        $target = strtoupper($validated['type']);

        if ($target === strtoupper((string) ($proxy->type_proxy ?: 'HTTP'))) {
            return response()->json(['status' => 'error', 'message' => "Proxy hiện đã là loại {$target}."], 400);
        }

        try {
            $client = $this->apiService->forAccount($proxy->cloudSunnyAccount);
            $client->changeProxyType((int) $proxy->provider_proxy_id, strtolower($target));
            $proxy->update(['type_proxy' => $target]);
            try {
                $remote = $client->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    $this->syncProxyFromRemote($proxy, $remote);
                }
            } catch (\Throwable $ignore) {
            }

            return response()->json([
                'status' => 'success',
                'message' => "Đã chuyển giao thức Proxy sang {$target}.",
                'data' => $this->formatProxyApiItem($proxy),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function syncProxyIp(int $proxyId)
    {
        $proxy = ProxyInstance::where('user_id', Auth::id())->where('id', $proxyId)->first();
        if (!$proxy || !$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount || $proxy->normalizedState() === 'deleted') {
            return response()->json(['status' => 'error', 'message' => 'Proxy không tồn tại hoặc đã hủy.'], 404);
        }

        $prod = $this->proxyPricingService->findProductById((int) $proxy->product_id);
        if (($prod['category'] ?? 'datacenter') !== 'residential') {
            return response()->json(['status' => 'error', 'message' => 'Chỉ Proxy Dân cư (Residential) mới hỗ trợ cấp IP mới.'], 400);
        }

        try {
            $this->apiService->forAccount($proxy->cloudSunnyAccount)->syncProxyIp((int) $proxy->provider_proxy_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi yêu cầu cấp IP mới. Gọi GET /proxy/{proxy_id}/status sau 5–10 giây để nhận IP mới.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage())], 500);
        }
    }

    public function deleteProxy(int $proxyId)
    {
        $proxy = ProxyInstance::where('user_id', Auth::id())->where('id', $proxyId)->first();
        if (!$proxy) {
            return response()->json(['status' => 'error', 'message' => 'Proxy ID không tồn tại.'], 404);
        }

        $proxy->update(['status' => 'Đã xóa', 'is_auto_renew' => false]);

        return response()->json(['status' => 'success', 'message' => 'Đã hủy Proxy thành công.']);
    }

    private function durationToCycle(int $duration): string
    {
        return match ($duration) {
            2 => 'twomonthly',
            3 => 'quarterly',
            6 => 'semi_annually',
            12 => 'annually',
            default => 'monthly',
        };
    }

    private function formatProxyApiItem(ProxyInstance $p): array
    {
        $state = $p->normalizedState();
        $isDeleted = $state === 'deleted';
        $protocol = strtoupper($p->type_proxy ?: 'HTTP');
        $port = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_port ?: $p->port) : $p->port);
        $user = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_username ?: $p->username) : $p->username);
        $pass = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_password ?: $p->password) : $p->password);

        return [
            'proxy_id' => $p->id,
            'product_id' => (int) $p->product_id,
            'protocol' => $protocol,
            'ip' => $p->ip,
            'port' => $port,
            'username' => $user,
            'password' => $pass,
            'connection_string' => (!$isDeleted && $p->ip && $port) ? "{$p->ip}:{$port}:{$user}:{$pass}" : null,
            'status' => match ($state) {
                'active' => 'active',
                'provisioning' => 'processing',
                default => 'deleted',
            },
            'expired_at' => $p->expires_at?->toIso8601String(),
        ];
    }

    private function syncProxyFromRemote(ProxyInstance $proxy, array $remote): void
    {
        $type = strtoupper((string) ($remote['type'] ?? $proxy->type_proxy ?? 'HTTP'));
        $isSocks5 = $type === 'SOCKS5';
        $proxy->update(array_filter([
            'ip' => $remote['ip'] ?? $proxy->ip,
            'port' => $remote['port'] ?? $proxy->port,
            'username' => $remote['username'] ?? $proxy->username,
            'password' => $remote['password'] ?? $proxy->password,
            'sock5_port' => $isSocks5 ? ($remote['port'] ?? $proxy->port) : ($remote['sock5_port'] ?? $proxy->sock5_port),
            'sock5_username' => $isSocks5 ? ($remote['username'] ?? $proxy->username) : ($remote['sock5_username'] ?? $proxy->sock5_username),
            'sock5_password' => $isSocks5 ? ($remote['password'] ?? $proxy->password) : ($remote['sock5_password'] ?? $proxy->sock5_password),
            'type_proxy' => $type,
            'status' => str_contains(strtolower((string) ($remote['status'] ?? 'on')), 'off') ? 'Đã tắt' : 'Hoạt động',
        ], static fn ($v) => $v !== null));
        $proxy->refresh();
    }
}

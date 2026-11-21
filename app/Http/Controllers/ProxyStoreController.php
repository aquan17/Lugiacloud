<?php

namespace App\Http\Controllers;

use App\Models\ProxyInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\FlashSaleService;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

class ProxyStoreController extends Controller
{
    public function index(Request $request, CloudSunnyProxyPricingService $pricing, FlashSaleService $flashSales)
    {
        $requestedProductId = (int) ($request->query('product_id') ?: $request->query('product') ?: 0);
        if ($requestedProductId > 0) {
            return redirect()->route('proxy.store.create', ['product' => $requestedProductId]);
        }

        $user = Auth::user();
        $products = $pricing->getPublicCatalogProducts($user);

        $proxyFlashSales = array_values(array_filter(
            $flashSales->getPublicDeals($user),
            fn (array $d) => $d['service_type'] === 'proxy'
        ));
        $huntVouchers = array_values(array_filter(
            $flashSales->getHuntableVouchers($user),
            fn (array $v) => in_array($v['applies_to'], ['all', 'proxy'], true)
        ));

        $proxyCycleKeys = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $allowedCycles = array_intersect_key(config('cloudsunny.billing_cycles', []), array_flip($proxyCycleKeys));

        return Inertia::render('Proxy/StoreIndex', [
            'products' => $products,
            'billingCycles' => $allowedCycles,
            'flashSales' => $proxyFlashSales,
            'huntVouchers' => $huntVouchers,
        ]);
    }

    public function create(int $product, CloudSunnyProxyPricingService $pricing, FlashSaleService $flashSales)
    {
        $user = Auth::user();
        $products = $pricing->getPublicCatalogProducts($user);
        $selected = collect($products)->first(fn (array $p) => (int) $p['id'] === $product || (int) ($p['catalog_id'] ?? 0) === $product);
        if (!$selected) {
            abort(404);
        }

        $proxyCycleKeys = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $allowedCycles = array_intersect_key(config('cloudsunny.billing_cycles', []), array_flip($proxyCycleKeys));

        return Inertia::render('Proxy/Create', [
            'selectedProductId' => (int) $selected['id'],
            'product' => $selected,
            'products' => $products,
            'billingCycles' => $allowedCycles,
            'claimedVouchers' => $flashSales->getUserClaimedVouchers($user, 'proxy'),
        ]);
    }

    public function store(
        Request $request,
        CloudSunnyApiService $api,
        CloudSunnyAccountRouter $router,
        CloudSunnyProxyPricingService $pricing,
        WalletService $wallet,
        PromotionEngine $promotions,
        FlashSaleService $flashSales
    ) {
        $proxyCycleKeys = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);

        $validated = $request->validate([
            'product_id' => 'required|integer',
            'billing_cycle' => 'required|in:' . implode(',', $proxyCycleKeys),
            'quantity' => 'required|integer|min:1|max:10',
            'type_proxy' => 'required|in:HTTP,SOCKS5',
            'custom_username' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'custom_password' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'http_username' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'http_password' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'sock5_username' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'sock5_password' => ['nullable', 'string', 'min:5', 'max:31', 'regex:/^[a-zA-Z0-9]+$/'],
            'coupon_code' => 'nullable|string|max:48',
            'expected_total' => 'nullable|integer|min:0',
        ], [
            'custom_username.min' => 'Username xác thực Proxy cần từ 5 đến 31 ký tự.',
            'custom_username.max' => 'Username xác thực Proxy tối đa 31 ký tự.',
            'custom_username.regex' => 'Username xác thực Proxy chỉ gồm chữ cái không dấu và chữ số (không dùng khoảng trắng hay ký tự đặc biệt).',
            'custom_password.min' => 'Mật khẩu xác thực Proxy cần từ 5 đến 31 ký tự.',
            'custom_password.max' => 'Mật khẩu xác thực Proxy tối đa 31 ký tự.',
            'custom_password.regex' => 'Mật khẩu xác thực Proxy chỉ gồm chữ cái không dấu và chữ số (không dùng khoảng trắng hay ký tự đặc biệt).',
        ]);

        $user = Auth::user();
        $product = $pricing->findProductById((int) $validated['product_id'], $user);
        if (!$product) {
            return back()->with('error', 'Gói Proxy không tồn tại hoặc đang tạm ngừng cung cấp.');
        }

        $quantity = (int) $validated['quantity'];
        $couponCode = $validated['coupon_code'] ?? null;

        $quote = $pricing->quoteProxyOrder(
            $product,
            $quantity,
            $validated['billing_cycle'],
            $user,
            false,
            $couponCode
        );

        if (!empty($couponCode) && isset($quote['coupon']) && !$quote['coupon']['valid']) {
            return back()->withInput()->with('error', $quote['coupon']['message']);
        }

        $totalPrice = $quote['final_price'];
        $totalProviderCost = $quote['provider_cost'];

        if ($request->filled('expected_total') && (int) $request->input('expected_total') !== (int) $totalPrice) {
            return back()->withInput()->with(
                'error',
                'Tổng thanh toán cho số lượng IP mới là ' . number_format($totalPrice, 0, ',', '.') . 'đ. Vui lòng kiểm tra lại và xác nhận thanh toán.'
            );
        }

        if ($user->balance < $totalPrice) {
            return back()->withInput()->with('error', 'Số dư ví không đủ (' . number_format($totalPrice, 0, ',', '.') . 'đ). Vui lòng nạp thêm số dư.');
        }

        $account = $router->pickForOrder($totalProviderCost);
        if (!$account) {
            return back()->withInput()->with('error', 'Kho IP hiện đang cập nhật hạn mức cấp phát. Vui lòng thử lại sau ít phút.');
        }

        $monthsMeta = (int) config('cloudsunny.billing_cycles.' . $validated['billing_cycle'] . '.months', 1);
        $expiresAt = Carbon::now()->addMonths($monthsMeta);

        $typeProxy = $validated['type_proxy'];
        $rawUser = $validated['custom_username']
            ?? ($typeProxy === 'SOCKS5' ? ($validated['sock5_username'] ?? '') : ($validated['http_username'] ?? ''));
        $rawPass = $validated['custom_password']
            ?? ($typeProxy === 'SOCKS5' ? ($validated['sock5_password'] ?? '') : ($validated['http_password'] ?? ''));

        $authUsername = !empty(trim((string) $rawUser)) ? trim((string) $rawUser) : strtolower(Str::random(8));
        $authPassword = !empty(trim((string) $rawPass)) ? trim((string) $rawPass) : Str::random(12);

        // Kiểm tra tồn kho & khả năng cấp phát thực tế trước khi trừ tiền ví (tránh trừ rồi phải hoàn khi kho IP tạm hết)
        try {
            $precheck = $api->forAccount($account)->checkPrice('create_proxy', [
                'product_id' => (int) $validated['product_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'quantity' => $quantity,
                'type' => $typeProxy,
                'username' => $authUsername,
                'password' => $authPassword,
            ]);
            if (isset($precheck['enough_credit']) && $precheck['enough_credit'] === false) {
                return back()->withInput()->with('error', 'Kho IP hiện đang cập nhật hạn mức cấp phát. Vui lòng thử lại sau ít phút hoặc liên hệ hỗ trợ.');
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            Log::warning('Proxy checkPrice precheck skipped due to transient error', ['msg' => $e->getMessage()]);
        }

        $proxyIds = [];
        try {
            DB::transaction(function () use ($user, $account, $validated, $quantity, $totalPrice, $expiresAt, $typeProxy, $authUsername, $authPassword, &$proxyIds) {
                $unitPaid = (int) round($totalPrice / max(1, $quantity));
                for ($i = 0; $i < $quantity; $i++) {
                    $proxy = ProxyInstance::create([
                        'user_id' => $user->id,
                        'cloudsunny_account_id' => $account->id,
                        'product_id' => (int) $validated['product_id'],
                        'type_proxy' => $typeProxy,
                        'status' => 'Đang khởi tạo...',
                        'billing_cycle' => $validated['billing_cycle'],
                        'paid_amount' => $unitPaid,
                        'expires_at' => $expiresAt,
                        'username' => $authUsername,
                        'password' => $authPassword,
                        'sock5_username' => $typeProxy === 'SOCKS5' ? $authUsername : null,
                        'sock5_password' => $typeProxy === 'SOCKS5' ? $authPassword : null,
                    ]);
                    $proxyIds[] = $proxy->id;
                }
            });

            $appliedCoupon = ($quote['coupon']['valid'] ?? false) ? $quote['coupon']['code'] : null;
            $flashSaleInfo = $quote['flash_sale'] ?? null;
            $descLabel = "Mua mới Proxy: {$product['title']} x{$quantity} IP ({$typeProxy})";
            if (!empty($flashSaleInfo['name'])) {
                $descLabel .= " [Flash Sale: {$flashSaleInfo['name']}]";
            }

            $transaction = DB::transaction(function () use (
                $wallet,
                $user,
                $totalPrice,
                $proxyIds,
                $quote,
                $appliedCoupon,
                $totalProviderCost,
                $descLabel,
                $flashSaleInfo,
                $flashSales,
                $quantity,
                $promotions
            ) {
                $txn = $wallet->debit($user, $totalPrice, [
                    'idempotency_key' => 'buy_proxy_' . implode('_', array_slice($proxyIds, 0, 3)) . '_' . count($proxyIds),
                    'proxy_instance_id' => count($proxyIds) === 1 ? $proxyIds[0] : null,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'coupon_code' => $appliedCoupon,
                    'type' => 'buy',
                    'provider_cost' => $totalProviderCost,
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
            ProxyInstance::whereIn('id', $proxyIds)->delete();
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->withInput()->with('error', 'Số dư ví không đủ.');
            }
            return back()->withInput()->with('error', CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        try {
            $remoteList = $api->forAccount($account)->createProxy([
                'product_id' => (int) $validated['product_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'quantity' => $quantity,
                'type' => $typeProxy,
                'username' => $authUsername,
                'password' => $authPassword,
            ]);

            foreach (['proxy', 'proxies', 'items'] as $k) {
                if (isset($remoteList[$k]) && is_array($remoteList[$k])) {
                    $remoteList = $remoteList[$k];
                    break;
                }
            }
            if (isset($remoteList['id']) || isset($remoteList['ip'])) {
                $remoteList = [$remoteList];
            }

            if (!is_array($remoteList) || count($remoteList) < $quantity) {
                throw new RuntimeException('Cụm cấp phát IP chưa trả đủ số lượng Proxy yêu cầu.');
            }

            $instances = ProxyInstance::whereIn('id', $proxyIds)->orderBy('id')->get();
            foreach ($instances as $index => $proxy) {
                if (isset($remoteList[$index]) && is_array($remoteList[$index])) {
                    $item = $remoteList[$index];
                    $remoteIp = !empty($item['ip']) ? $item['ip'] : null;
                    $remotePort = $item['port'] ?? null;
                    $isSocks5 = $typeProxy === 'SOCKS5';

                    $proxy->update([
                        'provider_proxy_id' => $item['id'] ?? null,
                        'ip' => $remoteIp,
                        'port' => $remotePort,
                        'sock5_port' => $isSocks5 ? $remotePort : null,
                        'username' => $authUsername,
                        'password' => $authPassword,
                        'sock5_username' => $isSocks5 ? $authUsername : null,
                        'sock5_password' => $isSocks5 ? $authPassword : null,
                        'status' => $remoteIp ? 'Hoạt động' : 'Đang khởi tạo',
                    ]);
                }
            }

            app(AffiliateService::class)->processCommission($user, $totalPrice, 'buy_proxy', "txn_{$transaction->id}");

            return redirect()->route('proxy.index')->with('success', "Đã cấp phát thành công {$quantity} Proxy {$typeProxy}. Bạn có thể sao chép hoặc xuất danh sách ngay bên dưới.");
        } catch (\Throwable $e) {
            Log::error('Upstream Proxy create failed', ['msg' => $e->getMessage()]);
            $safeReason = CloudSunnyApiService::sanitizeErrorMessage($e->getMessage());
            $wallet->refundTransaction($transaction, $safeReason);
            $flashSales->releaseSlotByTransaction($transaction->id);
            ProxyInstance::whereIn('id', $proxyIds)->update(['status' => 'Lỗi - đã hoàn tiền']);

            return back()->with('error', 'Cấp phát Proxy không thành công, hệ thống đã hoàn 100% tiền về ví của bạn. (' . $safeReason . ')');
        }
    }
}

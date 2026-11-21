<?php

namespace App\Http\Controllers;

use App\Mail\ServiceRenewed;
use App\Models\ProxyInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use RuntimeException;

class ProxyController extends Controller
{
    public function index(Request $request, CloudSunnyProxyPricingService $pricing)
    {
        $user = Auth::user();
        ProxyInstance::syncExpiredToDeleted($user->id);

        $now = now();
        $in7Days = $now->copy()->addDays(7);

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'active');
        if ($status === 'expired') {
            $status = 'deleted';
        }
        if (!in_array($status, ['active', 'expiring', 'deleted', 'all'], true)) {
            $status = 'active';
        }

        $countsRow = ProxyInstance::where('user_id', $user->id)
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN status IN ('Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt') OR (expires_at IS NOT NULL AND expires_at < ?) THEN 1 ELSE 0 END) as deleted_cnt", [$now])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt') AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) as expiring_cnt", [$now, $in7Days])
            ->first();

        $countAll = (int) ($countsRow->all_cnt ?? 0);
        $countDeleted = (int) ($countsRow->deleted_cnt ?? 0);
        $countExpiring = (int) ($countsRow->expiring_cnt ?? 0);
        $countActive = max(0, $countAll - $countDeleted);

        $query = ProxyInstance::where('user_id', $user->id)->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('port', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('type_proxy', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->whereNotIn('status', ['Hết hạn', 'Đã tắt', 'Đã xóa', 'Đã xoá'])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($status === 'expiring') {
            $query->whereNotIn('status', ['Hết hạn', 'Đã tắt', 'Đã xóa', 'Đã xoá'])
                ->whereBetween('expires_at', [$now, $in7Days]);
        } elseif ($status === 'deleted') {
            $query->where(function ($q) use ($now) {
                $q->whereIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt'])
                    ->orWhere(fn ($sub) => $sub->whereNotNull('expires_at')->where('expires_at', '<', $now));
            });
        }

        $catalogMap = [];
        foreach ($pricing->getCatalogProducts($user) as $prod) {
            $renewQuote = $pricing->quoteProxyOrder($prod, 1, 'monthly', $user, true);
            $prod['renew_price'] = $renewQuote['final_price'];
            $catalogMap[(int) $prod['id']] = $prod;
            if (!empty($prod['catalog_id'])) {
                $catalogMap[(int) $prod['catalog_id']] = $prod;
            }
        }

        $instances = $query->paginate(25)->withQueryString();
        $instances->getCollection()->transform(function (ProxyInstance $p) use ($catalogMap) {
            $prod = $catalogMap[(int) $p->product_id] ?? null;
            $protocol = strtoupper($p->type_proxy ?: 'HTTP');
            $state = $p->normalizedState();
            $isDeleted = $state === 'deleted';

            $port = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_port ?: $p->port) : $p->port);
            $username = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_username ?: $p->username) : $p->username);
            $password = $isDeleted ? null : ($protocol === 'SOCKS5' ? ($p->sock5_password ?: $p->password) : $p->password);

            return [
                'id' => $p->id,
                'product_id' => (int) $p->product_id,
                'product_name' => $prod['title'] ?? 'Proxy IPv4 Chuyên Dụng',
                'category' => $prod['category'] ?? 'datacenter',
                'protocol' => $protocol,
                'ip' => $p->ip,
                'port' => $port,
                'username' => $username,
                'password' => $password,
                'connection_string' => (!$isDeleted && $p->ip && $port) ? "{$p->ip}:{$port}:{$username}:{$password}" : null,
                'uri_string' => (!$isDeleted && $p->ip && $port) ? strtolower($protocol) . "://{$username}:{$password}@{$p->ip}:{$port}" : null,
                'status' => $p->displayStatus(),
                'state' => $state,
                'days_until_expiry' => $p->expires_at ? (int) ceil(now()->diffInDays($p->expires_at, false)) : null,
                'is_expired' => $p->expires_at ? $p->expires_at->isPast() : false,
                'is_auto_renew' => $isDeleted ? false : (bool) $p->is_auto_renew,
                'renew_price' => (int) ($prod['renew_price'] ?? $p->paid_amount ?? 20000),
                'expires_at' => $p->expires_at?->format('d/m/Y H:i'),
                'created_at' => $p->created_at?->format('d/m/Y H:i'),
            ];
        });

        return Inertia::render('Proxy/Index', [
            'instances' => $instances,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'all' => $countAll,
                'active' => $countActive,
                'expiring' => $countExpiring,
                'deleted' => $countDeleted,
            ],
        ]);
    }

    public function show(ProxyInstance $proxy, CloudSunnyApiService $api, CloudSunnyProxyPricingService $pricing)
    {
        $user = Auth::user();
        if ((int) $proxy->user_id !== (int) $user->id) {
            if ($user && $user->isAdmin()) {
                return redirect()->route('admin.proxies.show', $proxy);
            }
            abort(403, 'Bạn không có quyền truy cập Proxy này.');
        }

        if ($proxy->normalizedState() === 'deleted') {
            if (!in_array($proxy->status, ['Đã xóa', 'Đã xoá'], true)) {
                $proxy->update(['status' => 'Đã xóa', 'is_auto_renew' => false]);
            }
            return redirect()
                ->route('proxy.index', ['status' => 'deleted'])
                ->with('info', "Proxy #{$proxy->id} đã hết hạn và bị thu hồi IP, không thể quản lý hay gia hạn lại.");
        }

        // Chỉ đồng bộ đồng bộ (blocking) khi Proxy đang cấp phát chưa có IP; tránh gọi API ngoài mỗi lần mở trang chi tiết
        $needsImmediateSync = empty($proxy->ip) || $proxy->normalizedState() === 'provisioning';
        if ($needsImmediateSync && $proxy->provider_proxy_id && $proxy->cloudSunnyAccount) {
            try {
                $remote = $api->forAccount($proxy->cloudSunnyAccount)->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    $this->syncRemoteProxy($proxy, $remote);
                }
            } catch (\Throwable $e) {
            }
        }

        $prod = $pricing->findProductById((int) $proxy->product_id, Auth::user());
        $protocol = strtoupper($proxy->type_proxy ?: 'HTTP');

        $httpPort = $proxy->port;
        $httpUser = $proxy->username;
        $httpPass = $proxy->password;

        $sock5Port = $proxy->sock5_port;
        $sock5User = $proxy->sock5_username ?: $proxy->username;
        $sock5Pass = $proxy->sock5_password ?: $proxy->password;

        $primaryPort = $protocol === 'SOCKS5' ? ($sock5Port ?: $httpPort) : $httpPort;
        $primaryUser = $protocol === 'SOCKS5' ? ($sock5User ?: $httpUser) : $httpUser;
        $primaryPass = $protocol === 'SOCKS5' ? ($sock5Pass ?: $httpPass) : $httpPass;

        $daysUntilExpiry = $proxy->expires_at
            ? (int) ceil(now()->floatDiffInDays($proxy->expires_at, false))
            : null;

        $cycleKeys = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $billingCyclesConfig = config('cloudsunny.billing_cycles', []);
        $basePrice = (int) ($prod['list_price'] ?? $prod['price'] ?? $proxy->paid_amount ?? 39000);
        $renewQuotes = [];

        foreach ($cycleKeys as $cycleKey) {
            $meta = $billingCyclesConfig[$cycleKey] ?? ['label' => $cycleKey, 'months' => 1, 'discount_percent' => 0];
            $months = (int) ($meta['months'] ?? 1);
            $discPct = (int) ($meta['discount_percent'] ?? 0);
            $rawTotal = $basePrice * $months;
            $cyclePrice = $discPct > 0 ? (int) round($rawTotal * (1 - $discPct / 100)) : $rawTotal;

            $renewQuotes[$cycleKey] = [
                'cycle' => $cycleKey,
                'label' => $meta['label'] ?? "{$months} Tháng",
                'months' => $months,
                'discount_percent' => $discPct,
                'price' => $cyclePrice,
                'list_price' => $rawTotal,
                'discount_amount' => max(0, $rawTotal - $cyclePrice),
            ];

            if ($prod) {
                $q = $pricing->quoteProxyOrder($prod, 1, $cycleKey, Auth::user(), true);
                if (!empty($q['final_price'])) {
                    $renewQuotes[$cycleKey]['price'] = (int) $q['final_price'];
                    $renewQuotes[$cycleKey]['list_price'] = $rawTotal;
                    $renewQuotes[$cycleKey]['discount_amount'] = max(0, $rawTotal - (int) $q['final_price']);
                }
            }
        }

        return Inertia::render('Proxy/Show', [
            'proxy' => [
                'id' => $proxy->id,
                'product_id' => (int) $proxy->product_id,
                'product_name' => $prod['title'] ?? ('Proxy IPv4 #' . $proxy->product_id),
                'category' => $prod['category'] ?? 'datacenter',
                'protocol' => $protocol,
                'ip' => $proxy->ip,
                'port' => $primaryPort,
                'username' => $primaryUser,
                'password' => $primaryPass,
                'http_port' => $httpPort,
                'http_username' => $httpUser,
                'http_password' => $httpPass,
                'sock5_port' => $sock5Port,
                'sock5_username' => $sock5User,
                'sock5_password' => $sock5Pass,
                'connection_string' => $proxy->ip && $primaryPort ? "{$proxy->ip}:{$primaryPort}:{$primaryUser}:{$primaryPass}" : null,
                'connection_url' => $proxy->ip && $primaryPort ? (strtolower($protocol) . "://{$primaryUser}:{$primaryPass}@{$proxy->ip}:{$primaryPort}") : null,
                'status' => $proxy->displayStatus(),
                'state' => $proxy->normalizedState(),
                'days_until_expiry' => $daysUntilExpiry,
                'is_expired' => $proxy->expires_at ? $proxy->expires_at->isPast() : false,
                'is_auto_renew' => (bool) $proxy->is_auto_renew,
                'renew_price' => (int) ($renewQuotes['monthly']['price'] ?? $proxy->paid_amount ?? 20000),
                'renew_quotes' => $renewQuotes,
                'paid_amount' => (int) $proxy->paid_amount,
                'billing_cycle' => $proxy->billing_cycle,
                'billing_cycle_label' => $proxy->billing_cycle_label,
                'expires_at' => $proxy->expires_at?->format('d/m/Y H:i'),
                'created_at' => $proxy->created_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function statusJson(ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if ((int) $proxy->user_id !== (int) Auth::id()) {
            abort(403, 'Bạn không có quyền truy cập Proxy này.');
        }

        if ($proxy->normalizedState() !== 'deleted' && $proxy->provider_proxy_id && $proxy->cloudSunnyAccount) {
            try {
                $remote = $api->forAccount($proxy->cloudSunnyAccount)->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    $this->syncRemoteProxy($proxy, $remote);
                }
            } catch (\Throwable $e) {
            }
        }

        $protocol = strtoupper($proxy->type_proxy ?: 'HTTP');
        $port = $protocol === 'SOCKS5' ? ($proxy->sock5_port ?: $proxy->port) : $proxy->port;
        $username = $protocol === 'SOCKS5' ? ($proxy->sock5_username ?: $proxy->username) : $proxy->username;
        $password = $protocol === 'SOCKS5' ? ($proxy->sock5_password ?: $proxy->password) : $proxy->password;

        return response()->json([
            'id' => $proxy->id,
            'protocol' => $protocol,
            'ip' => $proxy->ip,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'connection_string' => $proxy->ip && $port ? "{$proxy->ip}:{$port}:{$username}:{$password}" : null,
            'status' => $proxy->displayStatus(),
            'state' => $proxy->normalizedState(),
        ]);
    }

    public function renew(
        Request $request,
        ProxyInstance $proxy,
        CloudSunnyApiService $api,
        CloudSunnyProxyPricingService $pricing,
        WalletService $wallet
    ) {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy này đã bị hủy, không thể gia hạn thêm.');
        }

        if (!$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
            return back()->with('error', 'Proxy chưa hoàn tất liên kết tới node cấp phát.');
        }

        $user = Auth::user();
        $product = $pricing->findProductById((int) $proxy->product_id, $user);
        if (!$product) {
            return back()->with('error', 'Không tìm thấy cấu hình giá gia hạn cho gói Proxy này.');
        }

        $allowedCycles = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $validated = $request->validate([
            'billing_cycle' => 'nullable|in:' . implode(',', $allowedCycles),
        ]);
        $cycle = $validated['billing_cycle'] ?? 'monthly';

        $quote = $pricing->quoteProxyOrder($product, 1, $cycle, $user, true);
        $pricePerCycle = $quote['final_price'];
        $providerCost = $quote['provider_cost'];
        $months = (int) (config("cloudsunny.billing_cycles.{$cycle}.months") ?? 1);

        if ($user->balance < $pricePerCycle) {
            return back()->with('error', 'Số dư ví không đủ để gia hạn (' . number_format($pricePerCycle, 0, ',', '.') . 'đ).');
        }

        $transaction = null;
        try {
            $transaction = $wallet->debit($user, $pricePerCycle, [
                'proxy_instance_id' => $proxy->id,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'type' => 'renew',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Gia hạn Proxy #{$proxy->id} ({$months} tháng)",
            ]);

            $apiClient = $api->forAccount($proxy->cloudSunnyAccount);
            $apiClient->actionProxy([(int) $proxy->provider_proxy_id], 'renew', ['billing_cycle' => $cycle]);

            $remote = $apiClient->getProxy((int) $proxy->provider_proxy_id);
            if ($remote) {
                $this->syncRemoteProxy($proxy, $remote);
            }

            $baseDate = $proxy->expires_at && $proxy->expires_at->isFuture()
                ? $proxy->expires_at->copy()
                : Carbon::now();

            $expiresAt = $this->remoteProxyExpiresAt($remote ?? []) ?: $baseDate->addMonths($months);

            $proxy->update([
                'expires_at' => $expiresAt,
                'paid_amount' => (int) $proxy->paid_amount + $pricePerCycle,
                'status' => 'Hoạt động',
            ]);

            app(AffiliateService::class)->processCommission($user, $pricePerCycle, 'renew_proxy', "txn_{$transaction->id}");

            if (!empty($user->email)) {
                try {
                    Mail::to($user->email)->queue(new ServiceRenewed(
                        userName: $user->name ?: 'bạn',
                        serviceType: 'proxy',
                        serviceName: "Proxy #{$proxy->id}",
                        ipAddress: $proxy->ip ? "{$proxy->ip}:{$proxy->port}" : null,
                        cycleLabel: "{$months} Tháng",
                        amountPaid: (int) $pricePerCycle,
                        newExpiresAt: $expiresAt->format('d/m/Y H:i'),
                        manageUrl: route('proxy.show', $proxy->id),
                        isAuto: false
                    ));
                } catch (\Throwable $mailErr) {
                    Log::error('Failed to queue Proxy renew receipt email', ['proxy_id' => $proxy->id, 'msg' => $mailErr->getMessage()]);
                }
            }

            return back()->with('success', "Đã gia hạn Proxy #{$proxy->id} thêm {$months} tháng.");
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn thất bại, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn thất bại, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Thao tác hàng loạt cho khách thuê sỉ Proxy: Bật/tắt tự gia hạn hoặc Gia hạn hàng loạt.
     */
    public function bulkAction(
        Request $request,
        CloudSunnyApiService $api,
        CloudSunnyProxyPricingService $pricing,
        WalletService $wallet
    ) {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
            'action' => 'required|in:enable_autorenew,disable_autorenew,renew',
        ]);

        $user = Auth::user();
        $proxies = ProxyInstance::where('user_id', $user->id)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->whereIn('id', $validated['ids'])
            ->get();

        if ($proxies->isEmpty()) {
            return back()->with('error', 'Không tìm thấy Proxy hợp lệ.');
        }

        if ($validated['action'] === 'enable_autorenew') {
            ProxyInstance::whereIn('id', $proxies->pluck('id'))->update(['is_auto_renew' => true]);
            return back()->with('success', 'Đã bật tự động gia hạn cho ' . $proxies->count() . ' Proxy.');
        }

        if ($validated['action'] === 'disable_autorenew') {
            ProxyInstance::whereIn('id', $proxies->pluck('id'))->update(['is_auto_renew' => false]);
            return back()->with('success', 'Đã tắt tự động gia hạn cho ' . $proxies->count() . ' Proxy.');
        }

        // Bulk renew
        $ok = 0;
        $failed = 0;
        foreach ($proxies as $proxy) {
            if (!$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
                $failed++;
                continue;
            }
            $product = $pricing->findProductById((int) $proxy->product_id, $user);
            if (!$product) {
                $failed++;
                continue;
            }

            $quote = $pricing->quoteProxyOrder($product, 1, 'monthly', $user, true);
            $price = $quote['final_price'];
            $cost = $quote['provider_cost'];

            $txn = null;
            try {
                $txn = $wallet->debit($user, $price, [
                    'proxy_instance_id' => $proxy->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'type' => 'renew',
                    'provider_cost' => $cost,
                    'discount_amount' => $quote['discount_amount'],
                    'description' => "Gia hạn hàng loạt Proxy #{$proxy->id} (1 tháng)",
                ]);

                $api->forAccount($proxy->cloudSunnyAccount)->actionProxy([(int) $proxy->provider_proxy_id], 'renew', ['billing_cycle' => 'monthly']);
                $baseDate = $proxy->expires_at && $proxy->expires_at->isFuture() ? $proxy->expires_at->copy() : Carbon::now();
                $proxy->update([
                    'expires_at' => $baseDate->addMonth(),
                    'paid_amount' => (int) $proxy->paid_amount + $price,
                    'status' => 'Hoạt động',
                ]);
                app(AffiliateService::class)->processCommission($user, $price, 'renew_proxy', "txn_{$txn->id}");
                $ok++;
            } catch (\Throwable $e) {
                if ($txn) {
                    $wallet->refundTransaction($txn, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
                }
                $failed++;
                if ($e instanceof RuntimeException && $e->getMessage() === 'INSUFFICIENT_BALANCE') {
                    break;
                }
            }
        }

        if ($ok === 0) {
            return back()->with('error', 'Không thể gia hạn hàng loạt (vui lòng kiểm tra số dư ví).');
        }

        return back()->with('success', "Đã gia hạn thành công {$ok} Proxy" . ($failed > 0 ? " ({$failed} IP bỏ qua)." : '.'));
    }

    public function toggleAutoRenew(Request $request, ProxyInstance $proxy)
    {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy này đã bị hủy, không thể bật tự động gia hạn.');
        }

        $validated = $request->validate([
            'is_auto_renew' => 'required|boolean',
        ]);

        $proxy->update([
            'is_auto_renew' => $validated['is_auto_renew'],
        ]);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json(['success' => true, 'is_auto_renew' => $proxy->is_auto_renew]);
        }

        return back()->with(
            'success',
            $proxy->is_auto_renew
                ? "Đã bật tự động gia hạn cho Proxy #{$proxy->id} (tự gia hạn trước 24h khi ví đủ số dư)."
                : "Đã tắt tự động gia hạn cho Proxy #{$proxy->id}."
        );
    }

    /**
     * Đổi tài khoản & mật khẩu xác thực Proxy trực tiếp qua hạ tầng (giữ nguyên IP & Port).
     */
    public function updateCredentials(Request $request, ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy đã ngừng hoạt động, không thể đổi thông tin đăng nhập.');
        }

        if (!$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
            return back()->with('error', 'Proxy chưa hoàn tất liên kết tới node cấp phát.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
        ], [
            'username.min' => 'Tên đăng nhập Proxy cần tối thiểu 5 ký tự.',
            'username.regex' => 'Tên đăng nhập chỉ gồm chữ cái không dấu, chữ số hoặc dấu gạch dưới (_).',
            'password.min' => 'Mật khẩu Proxy cần tối thiểu 5 ký tự.',
            'password.regex' => 'Mật khẩu Proxy chỉ gồm chữ cái không dấu, chữ số hoặc dấu gạch dưới (_).',
        ]);

        try {
            $apiClient = $api->forAccount($proxy->cloudSunnyAccount);
            $apiClient->editProxy(
                (int) $proxy->provider_proxy_id,
                $validated['username'],
                $validated['password']
            );

            $isSocks5 = strtoupper((string) $proxy->type_proxy) === 'SOCKS5';
            $update = [
                'username' => $validated['username'],
                'password' => $validated['password'],
            ];
            if ($isSocks5) {
                $update['sock5_username'] = $validated['username'];
                $update['sock5_password'] = $validated['password'];
            }
            $proxy->update($update);

            return back()->with('success', 'Đã cập nhật tài khoản & mật khẩu Proxy thành công.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đổi thông tin xác thực thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Chuyển đổi giao thức Proxy giữa HTTP và SOCKS5.
     */
    public function changeType(Request $request, ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy đã ngừng hoạt động, không thể chuyển đổi giao thức.');
        }

        if (!$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
            return back()->with('error', 'Proxy chưa hoàn tất liên kết tới node cấp phát.');
        }

        $validated = $request->validate([
            'type' => 'required|in:HTTP,SOCKS5',
        ]);

        $targetType = strtoupper($validated['type']);
        $currentType = strtoupper((string) ($proxy->type_proxy ?: 'HTTP'));

        if ($targetType === $currentType) {
            return back()->with('info', "Proxy hiện đã chạy ở giao thức {$currentType}.");
        }

        try {
            $apiClient = $api->forAccount($proxy->cloudSunnyAccount);
            $apiClient->changeProxyType((int) $proxy->provider_proxy_id, strtolower($targetType));

            $proxy->update(['type_proxy' => $targetType]);

            try {
                $remote = $apiClient->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    $this->syncRemoteProxy($proxy, $remote);
                }
            } catch (\Throwable $ignore) {
            }

            return back()->with('success', "Đã chuyển Proxy #{$proxy->id} sang giao thức {$targetType} thành công.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Chuyển giao thức thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Cấp địa chỉ IP mới cho Proxy Dân cư (Residential).
     */
    public function syncIp(ProxyInstance $proxy, CloudSunnyApiService $api, CloudSunnyProxyPricingService $pricing)
    {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy đã ngừng hoạt động, không thể đổi IP.');
        }

        if (!$proxy->provider_proxy_id || !$proxy->cloudSunnyAccount) {
            return back()->with('error', 'Proxy chưa hoàn tất liên kết tới node cấp phát.');
        }

        $prod = $pricing->findProductById((int) $proxy->product_id, Auth::user());
        if (($prod['category'] ?? 'datacenter') !== 'residential') {
            return back()->with('error', 'Chỉ dòng Proxy Dân cư (Residential) mới hỗ trợ cập nhật IP mới.');
        }

        try {
            $api->forAccount($proxy->cloudSunnyAccount)->syncProxyIp((int) $proxy->provider_proxy_id);

            return back()->with('success', 'Đã gửi yêu cầu cấp IP dân cư mới! Địa chỉ IP mới sẽ tự động cập nhật sau 5–10 giây.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Cập nhật IP mới thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function destroy(ProxyInstance $proxy)
    {
        if ($proxy->user_id !== Auth::id()) {
            abort(403);
        }

        $proxy->update([
            'status' => 'Đã xóa',
            'is_auto_renew' => false,
        ]);

        return redirect()
            ->route('proxy.index', ['status' => 'deleted'])
            ->with('success', "Đã hủy Proxy #{$proxy->id} và chuyển sang mục Đã xóa.");
    }

    private function syncRemoteProxy(ProxyInstance $proxy, array $remote): void
    {
        $expiresAt = $this->remoteProxyExpiresAt($remote);
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
            'expires_at' => $expiresAt,
        ], static fn ($value) => $value !== null));

        $proxy->refresh();
    }

    private function remoteProxyExpiresAt(array $remote): ?Carbon
    {
        foreach (['next_due_date', 'expires_at', 'expired_at', 'expire_at', 'expiration_at', 'end_at', 'end_date', 'ngay_het_han'] as $key) {
            if (!empty($remote[$key])) {
                try {
                    return Carbon::parse($remote[$key]);
                } catch (\Throwable $e) {
                }
            }
        }

        return null;
    }
}

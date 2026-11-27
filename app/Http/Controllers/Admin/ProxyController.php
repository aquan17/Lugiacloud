<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ServiceRenewed;
use App\Models\ProxyInstance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyProxyPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use RuntimeException;

class ProxyController extends Controller
{
    public function index(Request $request, CloudSunnyProxyPricingService $pricing)
    {
        ProxyInstance::syncExpiredToDeleted();

        $search = trim((string) $request->input('search', ''));
        $tab = $request->input('tab', 'active');
        if (!in_array($tab, ['active', 'expiring', 'provisioning', 'deleted', 'all'], true)) {
            $tab = 'active';
        }
        $category = $request->input('category', 'all');
        if (!in_array($category, ['all', 'datacenter', 'residential'], true)) {
            $category = 'all';
        }

        $now = now();
        $in7Days = $now->copy()->addDays(7);

        $catalogMap = [];
        $residentialIds = [];
        $datacenterIds = [];
        foreach ($pricing->getCatalogProducts() as $prod) {
            $catalogMap[(int) $prod['id']] = $prod;
            if (($prod['category'] ?? 'datacenter') === 'residential') {
                $residentialIds[] = (int) $prod['id'];
            } else {
                $datacenterIds[] = (int) $prod['id'];
            }
        }

        $query = ProxyInstance::query()->with(['user:id,name,email'])->latest();

        if ($tab === 'active') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt', 'Lỗi', 'Lỗi - đã hoàn tiền'])
                ->where('status', 'not like', '%khởi tạo%')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($tab === 'expiring') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt'])
                ->whereBetween('expires_at', [$now, $in7Days]);
        } elseif ($tab === 'provisioning') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt'])
                ->where(fn ($q) => $q->where('status', 'like', '%khởi tạo%')->orWhereNull('ip')->orWhere('ip', ''));
        } elseif ($tab === 'deleted') {
            $query->where(function ($q) use ($now) {
                $q->whereIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt', 'Lỗi', 'Lỗi - đã hoàn tiền'])
                    ->orWhere(fn ($sub) => $sub->whereNotNull('expires_at')->where('expires_at', '<', $now));
            });
        }

        if ($category === 'residential' && !empty($residentialIds)) {
            $query->whereIn('product_id', $residentialIds);
        } elseif ($category === 'datacenter' && !empty($datacenterIds)) {
            $query->whereIn('product_id', $datacenterIds);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('port', 'like', "%{$search}%")
                    ->orWhere('provider_proxy_id', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $proxies = $query
            ->paginate(25)
            ->withQueryString()
            ->through(function (ProxyInstance $p) use ($catalogMap) {
                $prod = $catalogMap[(int) $p->product_id] ?? null;
                $protocol = strtoupper($p->type_proxy ?: 'HTTP');
                $port = $protocol === 'SOCKS5' ? ($p->sock5_port ?: $p->port) : $p->port;

                return [
                    'id' => $p->id,
                    'provider_proxy_id' => $p->provider_proxy_id,
                    'product_name' => $prod['title'] ?? ('Proxy #' . $p->product_id),
                    'category' => $prod['category'] ?? 'datacenter',
                    'ip' => $p->ip,
                    'endpoint' => $p->ip ? "{$p->ip}:{$port}" : 'Đang cấp phát',
                    'protocol' => $protocol,
                    'status' => $p->displayStatus(),
                    'state' => $p->normalizedState(),
                    'paid_amount' => (int) $p->paid_amount,
                    'user_id' => $p->user_id,
                    'user_name' => $p->user?->name,
                    'user_email' => $p->user?->email,
                    'expires_at' => $p->expires_at?->format('d/m/Y H:i'),
                ];
            });

        $agg = ProxyInstance::query()
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN status IN ('Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt', 'Lỗi', 'Lỗi - đã hoàn tiền') OR (expires_at IS NOT NULL AND expires_at < ?) THEN 1 ELSE 0 END) as deleted_cnt", [$now])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt') AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) as expiring_cnt", [$now, $in7Days])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Hết hạn', 'Đã tắt') AND (status LIKE '%khởi tạo%' OR ip IS NULL OR ip = '') THEN 1 ELSE 0 END) as provisioning_cnt")
            ->first();

        $allCnt = (int) ($agg->all_cnt ?? 0);
        $deletedCnt = (int) ($agg->deleted_cnt ?? 0);
        $expiringCnt = (int) ($agg->expiring_cnt ?? 0);
        $provisioningCnt = (int) ($agg->provisioning_cnt ?? 0);
        $activeCnt = max(0, $allCnt - $deletedCnt - $provisioningCnt);

        return Inertia::render('Admin/Proxies/Index', [
            'proxies' => $proxies,
            'filters' => ['search' => $search, 'tab' => $tab, 'category' => $category],
            'counts' => [
                'all' => $allCnt,
                'active' => $activeCnt,
                'expiring' => $expiringCnt,
                'provisioning' => $provisioningCnt,
                'deleted' => $deletedCnt,
            ],
        ]);
    }

    /**
     * Trang xem chi tiết & quản trị riêng 1 Proxy IPv4 bên trong Admin Backoffice.
     */
    public function show(ProxyInstance $proxy, CloudSunnyProxyPricingService $pricing)
    {
        $proxy->load(['user:id,name,email,balance,proxy_discount_percent', 'cloudSunnyAccount:id,label,api_username']);
        $owner = $proxy->user;
        $prod = $pricing->findProductById((int) $proxy->product_id, $owner);
        $protocol = strtoupper($proxy->type_proxy ?: 'HTTP');
        $port = $protocol === 'SOCKS5' ? ($proxy->sock5_port ?: $proxy->port) : $proxy->port;
        $username = $protocol === 'SOCKS5' ? ($proxy->sock5_username ?: $proxy->username) : $proxy->username;
        $password = $protocol === 'SOCKS5' ? ($proxy->sock5_password ?: $proxy->password) : $proxy->password;

        $allowedCycles = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $renewQuotes = [];
        if ($prod) {
            foreach ($allowedCycles as $cycleKey) {
                $q = $pricing->quoteProxyOrder($prod, 1, $cycleKey, $owner, true);
                $meta = config("cloudsunny.billing_cycles.{$cycleKey}", []);
                $renewQuotes[] = [
                    'key' => $cycleKey,
                    'label' => $meta['label'] ?? (($meta['months'] ?? 1) . ' Tháng'),
                    'months' => (int) ($meta['months'] ?? 1),
                    'price' => (int) $q['final_price'],
                    'provider_cost' => (int) $q['provider_cost'],
                ];
            }
        }

        $transactions = Transaction::where('proxy_instance_id', $proxy->id)
            ->latest()
            ->take(15)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'code' => $t->code ?: ('#' . $t->id),
                'type' => $t->type,
                'status' => $t->status ?: 'completed',
                'amount' => (int) $t->amount,
                'provider_cost' => (int) $t->provider_cost,
                'description' => $t->description,
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Proxies/Show', [
            'proxy' => [
                'id' => $proxy->id,
                'provider_proxy_id' => $proxy->provider_proxy_id,
                'account_name' => $proxy->cloudSunnyAccount?->label ?: ($proxy->cloudSunnyAccount?->api_username ?: 'Chưa gán'),
                'product_id' => (int) $proxy->product_id,
                'product_name' => $prod['title'] ?? ('Proxy #' . $proxy->product_id),
                'category' => $prod['category'] ?? 'datacenter',
                'protocol' => $protocol,
                'ip' => $proxy->ip,
                'port' => $port,
                'http_port' => $proxy->port,
                'sock5_port' => $proxy->sock5_port,
                'username' => $username,
                'password' => $password,
                'connection_string' => $proxy->ip && $port ? "{$proxy->ip}:{$port}:{$username}:{$password}" : null,
                'status' => $proxy->displayStatus(),
                'state' => $proxy->normalizedState(),
                'is_auto_renew' => (bool) $proxy->is_auto_renew,
                'billing_cycle_label' => $proxy->billing_cycle_label,
                'paid_amount' => (int) $proxy->paid_amount,
                'created_at' => $proxy->created_at?->format('d/m/Y H:i'),
                'expires_at' => $proxy->expires_at?->format('d/m/Y H:i'),
                'is_linked' => (bool) ($proxy->cloudSunnyAccount && $proxy->provider_proxy_id),
                'user' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'balance' => (int) $owner->balance,
                    'proxy_discount_percent' => (int) $owner->proxy_discount_percent,
                ] : null,
            ],
            'renewQuotes' => $renewQuotes,
            'transactions' => $transactions,
        ]);
    }

    public function sync(ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if (!$proxy->cloudSunnyAccount || !$proxy->provider_proxy_id) {
            return back()->with('error', 'Proxy chưa liên kết tới hạ tầng gốc.');
        }

        try {
            $remote = $api->forAccount($proxy->cloudSunnyAccount)->getProxy((int) $proxy->provider_proxy_id);
            if ($remote) {
                $this->applyRemoteProxy($proxy, $remote);
            }

            return back()->with('success', "Đã đồng bộ thông số live mới nhất cho Proxy #{$proxy->id}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ Proxy thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function updateCredentials(Request $request, ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if (!$proxy->cloudSunnyAccount || !$proxy->provider_proxy_id) {
            return back()->with('error', 'Proxy chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', 'string', 'min:5', 'max:100', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        try {
            $api->forAccount($proxy->cloudSunnyAccount)->editProxy(
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

            return back()->with('success', 'Đã cập nhật tài khoản & mật khẩu Proxy.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đổi thông tin Proxy thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function changeType(Request $request, ProxyInstance $proxy, CloudSunnyApiService $api)
    {
        if (!$proxy->cloudSunnyAccount || !$proxy->provider_proxy_id) {
            return back()->with('error', 'Proxy chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate(['type' => 'required|in:HTTP,SOCKS5']);
        $targetType = strtoupper($validated['type']);

        try {
            $apiClient = $api->forAccount($proxy->cloudSunnyAccount);
            $apiClient->changeProxyType((int) $proxy->provider_proxy_id, strtolower($targetType));
            $proxy->update(['type_proxy' => $targetType]);

            try {
                $remote = $apiClient->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    $this->applyRemoteProxy($proxy, $remote);
                }
            } catch (\Throwable $ignore) {
            }

            return back()->with('success', "Đã chuyển Proxy #{$proxy->id} sang giao thức {$targetType}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Chuyển giao thức thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function syncIp(ProxyInstance $proxy, CloudSunnyApiService $api, CloudSunnyProxyPricingService $pricing)
    {
        if (!$proxy->cloudSunnyAccount || !$proxy->provider_proxy_id) {
            return back()->with('error', 'Proxy chưa liên kết tới hạ tầng gốc.');
        }

        $prod = $pricing->findProductById((int) $proxy->product_id);
        if (($prod['category'] ?? 'datacenter') !== 'residential') {
            return back()->with('error', 'Chỉ Proxy Dân cư (Residential) mới hỗ trợ đổi IP mới.');
        }

        try {
            $api->forAccount($proxy->cloudSunnyAccount)->syncProxyIp((int) $proxy->provider_proxy_id);

            return back()->with('success', 'Đã gửi lệnh cấp IP dân cư mới (cập nhật sau 5–10 giây).');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đổi IP Proxy thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Admin gia hạn hộ Proxy (tùy chọn trừ ví chủ sở hữu hoặc gia hạn trực tiếp không thu phí ví khách).
     */
    public function renew(
        Request $request,
        ProxyInstance $proxy,
        CloudSunnyApiService $api,
        CloudSunnyProxyPricingService $pricing,
        WalletService $wallet
    ) {
        if (!$proxy->cloudSunnyAccount || !$proxy->provider_proxy_id) {
            return back()->with('error', 'Proxy chưa liên kết tới hạ tầng gốc.');
        }
        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy này đã bị hủy/hết hạn thu hồi, không thể gia hạn thêm.');
        }

        $allowedCycles = config('cloudsunny.proxy_billing_cycles', ['monthly', 'twomonthly', 'quarterly', 'semi_annually', 'annually']);
        $validated = $request->validate([
            'billing_cycle' => 'required|in:' . implode(',', $allowedCycles),
            'charge_owner' => 'required|boolean',
            'admin_note' => 'nullable|string|max:200',
        ]);

        $owner = $proxy->user;
        $chargeOwner = (bool) $validated['charge_owner'];
        if ($chargeOwner && !$owner) {
            return back()->with('error', 'Proxy chưa gắn với chủ sở hữu để trừ ví.');
        }

        $product = $pricing->findProductById((int) $proxy->product_id, $owner);
        if (!$product) {
            return back()->with('error', 'Không tìm thấy cấu hình giá gia hạn cho gói Proxy này.');
        }

        $cycle = $validated['billing_cycle'];
        $quote = $pricing->quoteProxyOrder($product, 1, $cycle, $owner, true);
        $amount = $chargeOwner ? (int) $quote['final_price'] : 0;
        $providerCost = (int) $quote['provider_cost'];
        $months = (int) (config("cloudsunny.billing_cycles.{$cycle}.months") ?? 1);
        $cycleLabel = config("cloudsunny.billing_cycles.{$cycle}.label") ?? "{$months} Tháng";
        $noteSuffix = trim((string) ($validated['admin_note'] ?? '')) !== '' ? ' · ' . trim((string) $validated['admin_note']) : '';

        if ($chargeOwner && $owner->balance < $amount) {
            return back()->with('error', "Ví chủ sở hữu ({$owner->email}) không đủ số dư (cần " . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("proxy_admin_renew_{$proxy->id}", 60);
        if (!$lock->get()) {
            return back()->with('error', 'Proxy này đang xử lý yêu cầu gia hạn khác.');
        }

        $transaction = null;
        try {
            if ($chargeOwner) {
                $transaction = $wallet->debit($owner, $amount, [
                    'proxy_instance_id' => $proxy->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'type' => 'renew',
                    'provider_cost' => $providerCost,
                    'discount_amount' => (int) ($quote['discount_amount'] ?? 0),
                    'description' => "[Admin gia hạn hộ] Proxy #{$proxy->id} ({$cycleLabel}){$noteSuffix}",
                ]);
            }

            $apiClient = $api->forAccount($proxy->cloudSunnyAccount);
            $apiClient->actionProxy([(int) $proxy->provider_proxy_id], 'renew', [
                'billing_cycle' => $cycle,
            ]);

            $baseDate = $proxy->expires_at && $proxy->expires_at->isFuture() ? $proxy->expires_at->copy() : Carbon::now();
            $expiresAt = $baseDate->addMonths($months);

            try {
                $remote = $apiClient->getProxy((int) $proxy->provider_proxy_id);
                if ($remote) {
                    foreach (['next_due_date', 'expires_at', 'expired_at', 'expire_at', 'expiration_at', 'end_at', 'end_date', 'ngay_het_han'] as $key) {
                        if (!empty($remote[$key])) {
                            try {
                                $expiresAt = Carbon::parse($remote[$key]);
                                break;
                            } catch (\Throwable $ignore) {
                            }
                        }
                    }
                }
            } catch (\Throwable $ignore) {
            }

            $proxy->update([
                'expires_at' => $expiresAt,
                'paid_amount' => (int) $proxy->paid_amount + $amount,
                'billing_cycle' => $cycle,
                'status' => 'Hoạt động',
            ]);

            if (!$chargeOwner && $owner) {
                Transaction::create([
                    'user_id' => $owner->id,
                    'proxy_instance_id' => $proxy->id,
                    'type' => 'renew',
                    'status' => 'completed',
                    'amount' => 0,
                    'provider_cost' => $providerCost,
                    'discount_amount' => 0,
                    'balance_before' => (int) $owner->balance,
                    'balance_after' => (int) $owner->balance,
                    'description' => "[Admin gia hạn trực tiếp · 0đ ví khách] Proxy #{$proxy->id} ({$cycleLabel}){$noteSuffix}",
                ]);
            }

            if ($chargeOwner && $transaction) {
                app(AffiliateService::class)->processCommission($owner, $amount, 'renew_proxy', "txn_{$transaction->id}");
            }

            if ($owner && !empty($owner->email)) {
                try {
                    Mail::to($owner->email)->queue(new ServiceRenewed(
                        userName: $owner->name ?: 'bạn',
                        serviceType: 'proxy',
                        serviceName: "Proxy #{$proxy->id}",
                        ipAddress: $proxy->ip ? "{$proxy->ip}:{$proxy->port}" : null,
                        cycleLabel: $cycleLabel,
                        amountPaid: $amount,
                        newExpiresAt: $expiresAt->format('d/m/Y H:i'),
                        manageUrl: route('proxy.show', $proxy->id),
                        isAuto: false
                    ));
                } catch (\Throwable $ignore) {
                }
            }

            Log::info('Admin renewed Proxy', [
                'admin_id' => Auth::id(),
                'proxy_id' => $proxy->id,
                'cycle' => $cycle,
                'charge_owner' => $chargeOwner,
                'amount' => $amount,
                'provider_cost' => $providerCost,
            ]);

            return back()->with('success', "Đã gia hạn Proxy #{$proxy->id} thêm {$cycleLabel}" . ($chargeOwner ? ' (trừ ví khách ' . number_format($amount, 0, ',', '.') . 'đ).' : ' (không trừ ví khách).'));
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví chủ sở hữu không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn Proxy thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn Proxy thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function toggleAutoRenew(ProxyInstance $proxy)
    {
        if ($proxy->normalizedState() === 'deleted') {
            return back()->with('error', 'Proxy đã ngừng hoạt động, không thể bật tự động gia hạn.');
        }

        $next = !$proxy->is_auto_renew;
        $proxy->update(['is_auto_renew' => $next]);

        return back()->with('success', $next ? "Đã bật tự động gia hạn cho Proxy #{$proxy->id}." : "Đã tắt tự động gia hạn cho Proxy #{$proxy->id}.");
    }

    /**
     * Gán / chuyển chủ sở hữu 1 Proxy sang tài khoản User khác.
     */
    public function reassign(Request $request, ProxyInstance $proxy)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'disable_auto_renew' => 'sometimes|boolean',
        ]);

        $targetUser = User::findOrFail((int) $data['user_id']);
        $previousUserId = $proxy->user_id;

        if ((int) $previousUserId === (int) $targetUser->id) {
            return back()->with('info', "Proxy #{$proxy->id} hiện đã thuộc tài khoản {$targetUser->email}.");
        }

        $update = ['user_id' => $targetUser->id];
        if ($request->boolean('disable_auto_renew', true)) {
            $update['is_auto_renew'] = false;
        }
        $proxy->update($update);

        Log::info('Admin reassigned Proxy ownership', [
            'admin_id' => Auth::id(),
            'proxy_id' => $proxy->id,
            'from_user_id' => $previousUserId,
            'to_user_id' => $targetUser->id,
        ]);

        return back()->with('success', "Đã chuyển Proxy {$proxy->ip}:{$proxy->port} sang tài khoản {$targetUser->name} ({$targetUser->email}).");
    }

    public function bulkReassign(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:proxy_instances,id',
            'user_id' => 'required|integer|exists:users,id',
            'disable_auto_renew' => 'sometimes|boolean',
        ]);

        $targetUser = User::findOrFail((int) $data['user_id']);
        $update = ['user_id' => $targetUser->id];
        if ($request->boolean('disable_auto_renew', true)) {
            $update['is_auto_renew'] = false;
        }

        $count = ProxyInstance::whereIn('id', $data['ids'])->update($update);

        return back()->with('success', "Đã chuyển {$count} Proxy sang tài khoản {$targetUser->name} ({$targetUser->email}).");
    }

    public function destroy(ProxyInstance $proxy)
    {
        $proxyId = $proxy->id;
        DB::transaction(function () use ($proxy) {
            $proxy->update(['status' => 'Đã xóa', 'is_auto_renew' => false]);
            $proxy->delete();
        });

        $previous = (string) url()->previous();
        if (preg_match('#/admin/proxies/' . $proxyId . '(?:[/?#]|$)#', $previous)) {
            return redirect()->route('admin.proxies.index')->with('success', 'Đã xóa Proxy khỏi hệ thống.');
        }

        return back()->with('success', 'Đã xóa Proxy khỏi hệ thống.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids');
        if (!is_array($ids) || count($ids) === 0) {
            return back()->with('error', 'Chưa chọn Proxy nào để xóa.');
        }

        DB::transaction(function () use ($ids) {
            ProxyInstance::whereIn('id', $ids)->update(['status' => 'Đã xóa', 'is_auto_renew' => false]);
            ProxyInstance::whereIn('id', $ids)->delete();
        });

        return back()->with('success', 'Đã xóa ' . count($ids) . ' Proxy.');
    }

    private function applyRemoteProxy(ProxyInstance $proxy, array $remote): void
    {
        $type = strtoupper((string) ($remote['type'] ?? $proxy->type_proxy ?? 'HTTP'));
        $isSocks5 = $type === 'SOCKS5';
        $expiresAt = null;
        foreach (['next_due_date', 'expires_at', 'expired_at', 'ngay_het_han'] as $key) {
            if (!empty($remote[$key])) {
                try {
                    $expiresAt = Carbon::parse($remote[$key]);
                    break;
                } catch (\Throwable $e) {
                }
            }
        }

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
        ], static fn ($v) => $v !== null));
    }
}

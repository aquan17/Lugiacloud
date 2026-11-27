<?php

namespace App\Http\Controllers;

use App\Mail\ServiceRenewed;
use App\Models\ProxyInstance;
use App\Models\TopupRequest;
use App\Models\Transaction;
use App\Models\VpsInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use RuntimeException;

class DashboardController extends Controller
{
    /**
     * Trang Tổng quan tối ưu 100% bằng SQL Aggregate — không kéo hàng nghìn bản ghi lên RAM.
     */
    public function overview()
    {
        $user = Auth::user();
        $userId = $user->id;
        $now = now();
        $fiveDaysAgo = $now->copy()->startOfDay()->subDays(5);
        $in7Days = $now->copy()->addDays(7);

        VpsInstance::syncOverdueStatus($userId);
        ProxyInstance::syncExpiredToDeleted($userId);

        $vpsAgg = VpsInstance::where('user_id', $userId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'Đã xóa' OR (expires_at IS NOT NULL AND expires_at <= ?) THEN 1 ELSE 0 END) as deleted_cnt", [$fiveDaysAgo])
            ->selectRaw("SUM(CASE WHEN status != 'Đã xóa' AND (status = 'Hết hạn' OR (expires_at IS NOT NULL AND expires_at > ? AND expires_at < ?)) THEN 1 ELSE 0 END) as expired_cnt", [$fiveDaysAgo, $now])
            ->first();

        $statVpsTotal = (int) ($vpsAgg->total ?? 0);
        $statVpsDeleted = (int) ($vpsAgg->deleted_cnt ?? 0);
        $statVpsExpired = (int) ($vpsAgg->expired_cnt ?? 0);
        $statVpsActive = max(0, $statVpsTotal - $statVpsDeleted - $statVpsExpired);

        $proxyAgg = ProxyInstance::where('user_id', $userId)
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá') THEN 1 ELSE 0 END) as total")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá') AND (status IN ('Hết hạn', 'Đã tắt') OR (expires_at IS NOT NULL AND expires_at < ?)) THEN 1 ELSE 0 END) as expired_cnt", [$now])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá') AND expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? THEN 1 ELSE 0 END) as expiring_cnt", [$now, $in7Days])
            ->first();

        $statProxyTotal = (int) ($proxyAgg->total ?? 0);
        $statProxyExpired = (int) ($proxyAgg->expired_cnt ?? 0);
        $statProxyExpiring = (int) ($proxyAgg->expiring_cnt ?? 0);
        $statProxyActive = max(0, $statProxyTotal - $statProxyExpired);

        $expiringVps = VpsInstance::where('user_id', $userId)
            ->where('status', '!=', 'Đã xóa')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $fiveDaysAgo)
            ->where('expires_at', '<=', $in7Days)
            ->orderBy('expires_at')
            ->take(6)
            ->get()
            ->map(fn (VpsInstance $v) => [
                'id' => $v->id,
                'label' => $v->label,
                'public_ip' => $v->public_ip,
                'specs' => "{$v->cpu} vCPU · {$v->ram}GB · {$v->disk}GB",
                'state' => $v->normalizedState(),
                'status' => $v->displayStatus(),
                'expires_at' => $v->expires_at?->format('d/m/Y H:i'),
                'is_expired' => $v->isExpired(),
            ]);

        $expiringProxies = ProxyInstance::where('user_id', $userId)
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $in7Days)
            ->orderBy('expires_at')
            ->take(6)
            ->get()
            ->map(fn (ProxyInstance $p) => [
                'id' => $p->id,
                'endpoint' => $p->ip ? "{$p->ip}:{$p->port}" : "Proxy #{$p->id}",
                'type_proxy' => $p->type_proxy ?: 'HTTP',
                'state' => $p->normalizedState(),
                'expires_at' => $p->expires_at?->format('d/m/Y H:i'),
                'is_auto_renew' => (bool) $p->is_auto_renew,
            ]);

        $totalDeposited = (int) TopupRequest::where('user_id', $userId)
            ->whereIn('status', ['paid', 'approved'])
            ->selectRaw('COALESCE(SUM(COALESCE(approved_amount, amount) + COALESCE(bonus_amount, 0)), 0) as s')
            ->value('s');

        $totalSpent = (int) Transaction::where('user_id', $userId)
            ->whereIn('type', ['buy', 'buy_vps', 'buy_proxy', 'buy_server', 'renew', 'upgrade', 'change_ip'])
            ->where('status', '!=', 'refunded')
            ->sum('amount');

        $recentTransactions = Transaction::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->take(6)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'code' => $t->code ?: ('#' . $t->id),
                'type' => $t->type,
                'status' => $t->status,
                'amount' => (int) $t->amount,
                'is_credit' => in_array($t->type, ['refund', 'affiliate_bonus', 'topup_bonus', 'admin_add'], true),
                'description' => $t->description,
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Dashboard/Overview', [
            'stats' => [
                'vps_total' => $statVpsTotal,
                'vps_active' => $statVpsActive,
                'vps_expired' => $statVpsExpired,
                'vps_deleted' => $statVpsDeleted,
                'proxy_total' => $statProxyTotal,
                'proxy_active' => $statProxyActive,
                'proxy_expiring' => $statProxyExpiring,
                'proxy_expired' => $statProxyExpired,
                'total_deposited' => $totalDeposited,
                'total_spent' => $totalSpent,
            ],
            'expiringVps' => $expiringVps,
            'expiringProxies' => $expiringProxies,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function index(Request $request, CloudSunnyPricingService $pricing)
    {
        $userId = Auth::id();
        VpsInstance::syncOverdueStatus($userId);
        ProxyInstance::syncExpiredToDeleted($userId);

        $now = now();
        $filter = $request->input('status', 'active');
        $search = trim((string) $request->input('search', ''));

        $query = VpsInstance::where('user_id', $userId)->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('public_ip', 'like', "%{$search}%")
                    ->orWhere('plan_id', 'like', "%{$search}%");
            });
        }

        // Lọc trạng thái chuẩn xác 100%: Tab "Đang hoạt động" chỉ hiển thị máy chủ đang sẵn sàng/chạy và còn hạn
        if ($filter === 'active') {
            $query->whereNotIn('status', ['Đã tắt', 'Đã xóa', 'Hết hạn'])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($filter === 'stopped') {
            $query->where('status', 'Đã tắt')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($filter === 'expired') {
            $query->where(function ($q) use ($now) {
                $q->where('status', 'Hết hạn')
                    ->orWhere(fn ($sub) => $sub->whereNotNull('expires_at')->where('expires_at', '<', $now));
            })->where('status', '!=', 'Đã xóa');
        } elseif ($filter === 'deleted') {
            $query->where('status', 'Đã xóa');
        }

        $paginated = $query->paginate(15)->withQueryString();
        $images = $pricing->getImages(1);

        $paginated->getCollection()->transform(function (VpsInstance $v) use ($pricing, $images) {
            $osMeta = $images[$v->provider_os_id] ?? null;
            $osLabel = $osMeta['label'] ?? ($v->provider_payload['os'] ?? 'Linux / Windows');
            $isWindows = stripos((string) $osLabel, 'windows') !== false;
            $daysLeft = $v->expires_at
                ? (int) now()->startOfDay()->diffInDays($v->expires_at->copy()->startOfDay(), false)
                : null;

            return [
                'id' => $v->id,
                'label' => $v->label,
                'plan_id' => $v->plan_id,
                'region' => strtoupper($v->region ?: 'VN'),
                'public_ip' => $v->public_ip,
                'login_username' => $v->login_username ?: ($isWindows ? 'Administrator' : 'root'),
                'root_password' => $v->isOverdue5Days() ? null : $v->root_password,
                'cpu' => (int) $v->cpu,
                'ram' => (int) $v->ram,
                'disk' => (int) $v->disk,
                'os_label' => $osLabel,
                'os_distro' => $pricing->detectDistroKey((string) $osLabel),
                'status' => $v->displayStatus(),
                'state' => $v->normalizedState(),
                'is_auto_renew' => (bool) $v->is_auto_renew,
                'billing_cycle_label' => $v->billing_cycle_label,
                'paid_amount' => (int) $v->paid_amount,
                'expires_at' => $v->expires_at?->format('d/m/Y H:i'),
                'expires_date' => $v->expires_at?->format('d/m/Y'),
                'created_at' => $v->created_at?->format('d/m/Y H:i'),
                'created_date' => $v->created_at?->format('d/m/Y'),
                'days_left' => $daysLeft,
                'can_renew' => $v->canRenew(),
            ];
        });

        $nowStr = $now->toDateTimeString();
        $counts = VpsInstance::where('user_id', $userId)
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã tắt', 'Đã xóa', 'Hết hạn') AND (expires_at IS NULL OR expires_at >= '{$nowStr}') THEN 1 ELSE 0 END) as active_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'Đã tắt' AND (expires_at IS NULL OR expires_at >= '{$nowStr}') THEN 1 ELSE 0 END) as stopped_cnt")
            ->selectRaw("SUM(CASE WHEN (status = 'Hết hạn' OR (expires_at IS NOT NULL AND expires_at < '{$nowStr}')) AND status != 'Đã xóa' THEN 1 ELSE 0 END) as expired_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'Đã xóa' THEN 1 ELSE 0 END) as deleted_cnt")
            ->first();

        return Inertia::render('Dashboard/VpsIndex', [
            'instances' => $paginated,
            'filters' => [
                'status' => $filter,
                'search' => $search,
            ],
            'counts' => [
                'all' => (int) ($counts->all_cnt ?? 0),
                'active' => (int) ($counts->active_cnt ?? 0),
                'stopped' => (int) ($counts->stopped_cnt ?? 0),
                'expired' => (int) ($counts->expired_cnt ?? 0),
                'deleted' => (int) ($counts->deleted_cnt ?? 0),
            ],
        ]);
    }

    public function show(VpsInstance $vps, CloudSunnyPricingService $pricing)
    {
        $user = Auth::user();
        if ((int) $vps->user_id !== (int) $user->id) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.instances.show', $vps);
            }
            abort(403, 'Bạn không có quyền truy cập máy chủ này.');
        }
        $plan = $pricing->getPlan((string) $vps->plan_id, $user);
        $images = $pricing->getImages((int) ($vps->provider_product_id ?: 1));
        $osMeta = $images[$vps->provider_os_id] ?? null;
        $osLabel = $osMeta['label'] ?? ($vps->provider_payload['os'] ?? 'Ubuntu / Linux');

        $renewQuotes = [];
        if ($plan) {
            $addonCpu = max(0, (int) $vps->cpu - (int) $plan['cores']);
            $addonRam = max(0, (int) $vps->ram - (int) $plan['ram']);
            $addonDisk = max(0, (int) $vps->disk - (int) $plan['disk']);

            foreach (config('cloudsunny.billing_cycles', []) as $cycleKey => $cycleMeta) {
                $q = $pricing->quoteCycleForPlan($plan, $cycleKey, $user, $addonCpu, $addonRam, $addonDisk, true);
                $renewQuotes[$cycleKey] = [
                    'label' => $cycleMeta['label'] ?? $cycleKey,
                    'months' => (int) ($cycleMeta['months'] ?? 1),
                    'list_price' => $q['list_price'],
                    'final_price' => $q['final_price'],
                    'discount_amount' => $q['discount_amount'],
                    'campaign' => $q['campaign'],
                ];
            }
        }

        $addonRates = $pricing->getAddonRates();
        $daysLeft = $vps->expires_at ? max(1, (int) now()->diffInDays($vps->expires_at, false)) : 30;
        $daysUntilExpiry = $vps->expires_at
            ? (int) now()->startOfDay()->diffInDays($vps->expires_at->copy()->startOfDay(), false)
            : null;
        $baseDisk = (int) ($plan['disk'] ?? $vps->disk);
        $changeIpBaseRetail = (int) ($addonRates['change_ip'] ?? 30000);
        $changeIpCost = (int) ($addonRates['cost_change_ip'] ?? 20000);
        $changeIpUserPrice = max($changeIpCost, $pricing->applyUserDiscount($changeIpBaseRetail, $user));

        return Inertia::render('Dashboard/VpsShow', [
            'vps' => [
                'id' => $vps->id,
                'label' => $vps->label,
                'plan_id' => $vps->plan_id,
                'plan_name' => $plan['name'] ?? strtoupper((string) $vps->plan_id),
                'region' => strtoupper($vps->region ?: 'VN'),
                'public_ip' => $vps->public_ip,
                'login_username' => $vps->login_username ?: (stripos($osLabel, 'windows') !== false ? 'Administrator' : 'root'),
                'root_password' => $vps->root_password,
                'cpu' => (int) $vps->cpu,
                'ram' => (int) $vps->ram,
                'disk' => (int) $vps->disk,
                'base_cpu' => (int) ($plan['cores'] ?? $vps->cpu),
                'base_ram' => (int) ($plan['ram'] ?? $vps->ram),
                'base_disk' => $baseDisk,
                'can_extend_disk' => (int) $vps->disk > $baseDisk,
                'network_mbps' => (int) ($plan['network_out_mbps'] ?? 100),
                'transfer_tb' => $plan['transfer_tb'] ?? 'Unlimited',
                'os_id' => (int) $vps->provider_os_id,
                'os_label' => $osLabel,
                'os_distro' => $pricing->detectDistroKey((string) $osLabel),
                'status' => $vps->displayStatus(),
                'state' => $vps->normalizedState(),
                'is_auto_renew' => (bool) $vps->is_auto_renew,
                'billing_cycle' => $vps->billing_cycle,
                'billing_cycle_label' => $vps->billing_cycle_label,
                'paid_amount' => (int) $vps->paid_amount,
                'created_at' => $vps->created_at?->format('d/m/Y H:i'),
                'expires_at' => $vps->expires_at?->format('d/m/Y H:i'),
                'days_remaining' => $daysLeft,
                'days_until_expiry' => $daysUntilExpiry,
                'is_deleted' => $vps->isOverdue5Days(),
                'is_expired' => $vps->isExpired(),
                'can_renew' => $vps->canRenew(),
                'is_linked' => (bool) ($vps->cloudSunnyAccount && $vps->provider_vps_id),
            ],
            'images' => array_values($images),
            'renewQuotes' => $renewQuotes,
            'addonRates' => [
                'cpu_monthly' => $addonRates['cpu_monthly'],
                'ram_monthly' => $addonRates['ram_monthly'],
                'disk_10gb_monthly' => $addonRates['disk_10gb_monthly'],
            ],
            'changeIpConfig' => [
                'enabled' => (bool) ($addonRates['change_ip_active'] ?? true),
                'price' => $changeIpUserPrice,
                'list_price' => $changeIpBaseRetail,
                'limit_per_month' => 30,
            ],
        ]);
    }

    public function syncPending(Request $request, CloudSunnyApiService $api): JsonResponse
    {
        $userId = Auth::id();
        $force = $request->boolean('force');

        $pending = VpsInstance::with('cloudSunnyAccount')
            ->where('user_id', $userId)
            ->whereNotNull('provider_vps_id')
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn'])
            ->where(function ($q) {
                $q->whereNull('public_ip')
                    ->orWhere('public_ip', '')
                    ->orWhere('status', 'like', '%khởi tạo%')
                    ->orWhere('status', 'like', '%xử lý%')
                    ->orWhere('status', 'like', '%cài đặt%')
                    ->orWhere('status', 'like', '%cài lại%')
                    ->orWhere('status', 'like', '%đổi ip%')
                    ->orWhere('status', 'like', '%bảo trì%')
                    ->orWhere('status', 'like', '%mở rộng%')
                    ->orWhere('status', 'like', '%nâng cấp%')
                    ->orWhere('status', 'like', '%khởi động lại%')
                    ->orWhereIn('status', ['pending', 'progressing', 'creating', 'provisioning', 'waiting', 'renewing', 'rebuild', 'change_ip', 'maintenance', 'upgrade']);
            })
            ->take(20)
            ->get();

        if ($pending->isEmpty()) {
            return response()->json(['changed' => false, 'newly_ready' => [], 'pending_count' => 0]);
        }

        $throttleKey = "vps_sync_pending_u{$userId}";
        $changed = false;
        $newlyReady = [];

        if ($force || \Illuminate\Support\Facades\Cache::add($throttleKey, true, 5)) {
            $byAccount = $pending->groupBy('cloudsunny_account_id');
            foreach ($byAccount as $group) {
                $account = $group->first()?->cloudSunnyAccount;
                if (!$account) {
                    continue;
                }

                // 1 request listVps() duy nhất cho toàn bộ VPS đang khởi tạo thay vì N request lẻ
                $remoteMap = [];
                try {
                    $remoteList = $api->forAccount($account)->listVps();
                    foreach (['vps', 'servers', 'items'] as $k) {
                        if (isset($remoteList[$k]) && is_array($remoteList[$k])) {
                            $remoteList = $remoteList[$k];
                            break;
                        }
                    }
                    if (is_array($remoteList)) {
                        foreach ($remoteList as $rv) {
                            $vid = $rv['id'] ?? $rv['vps_id'] ?? null;
                            if ($vid) {
                                $remoteMap[(int) $vid] = $rv;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }

                foreach ($group as $vps) {
                    $prevIp = $vps->public_ip;
                    $prevState = $vps->normalizedState();
                    $prevPass = $vps->root_password;

                    $remote = $remoteMap[(int) $vps->provider_vps_id] ?? null;
                    if (!$remote) {
                        try {
                            $remote = $api->forAccount($account)->getVps((int) $vps->provider_vps_id);
                        } catch (\Throwable $e) {
                        }
                    }

                    if ($remote) {
                        $this->syncRemoteVps($vps, $remote);
                        $vps->refresh();
                        $newState = $vps->normalizedState();
                        if ($vps->public_ip !== $prevIp || $newState !== $prevState || $vps->root_password !== $prevPass) {
                            $changed = true;
                            if ($newState === 'running' && $vps->public_ip) {
                                $newlyReady[] = "{$vps->label} ({$vps->public_ip})";
                            }
                        }
                    }
                }
            }
        }

        $stillPending = $pending->filter(fn (VpsInstance $v) => $v->isProvisioning() || empty($v->public_ip))->count();

        return response()->json([
            'changed' => $changed,
            'newly_ready' => $newlyReady,
            'pending_count' => $stillPending,
        ]);
    }

    public function statusJson(VpsInstance $vps, CloudSunnyApiService $api): JsonResponse
    {
        $this->authorizeVps($vps);

        if ($vps->normalizedState() !== 'deleted' && $vps->cloudSunnyAccount && $vps->provider_vps_id) {
            $throttleKey = "vps_status_poll_{$vps->id}";
            if (\Illuminate\Support\Facades\Cache::add($throttleKey, true, 4)) {
                try {
                    $remote = $api->forAccount($vps->cloudSunnyAccount)->getVps((int) $vps->provider_vps_id);
                    if ($remote) {
                        $this->syncRemoteVps($vps, $remote);
                        $vps->refresh();
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return response()->json([
            'status' => $vps->displayStatus(),
            'state' => $vps->normalizedState(),
            'public_ip' => $vps->public_ip,
            'login_username' => $vps->login_username,
            'root_password' => $vps->root_password,
            'is_provisioning' => $vps->isProvisioning(),
        ]);
    }

    public function updateLabel(Request $request, VpsInstance $vps)
    {
        $this->authorizeVps($vps);

        $validated = $request->validate([
            'label' => 'required|string|min:2|max:64',
        ], [
            'label.required' => 'Tên máy chủ không được để trống.',
            'label.max' => 'Tên máy chủ tối đa 64 ký tự.',
        ]);

        $vps->update(['label' => trim($validated['label'])]);

        return back()->with('success', 'Đã cập nhật tên gợi nhớ máy chủ.');
    }

    public function sync(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        try {
            $remote = $api->forAccount($vps->cloudSunnyAccount)->getVps((int) $vps->provider_vps_id);
            if ($remote) {
                $this->syncRemoteVps($vps, $remote);
            }
            return back()->with('success', 'Đã đồng bộ trạng thái máy chủ mới nhất.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function boot(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->bootVps((int) $vps->provider_vps_id);
            $vps->update(['status' => 'Sẵn sàng']);
            return back()->with('success', 'Đã gửi lệnh bật nguồn máy chủ.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Bật nguồn thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function shutdown(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->shutdownVps((int) $vps->provider_vps_id);
            $vps->update(['status' => 'Đã tắt']);
            return back()->with('success', 'Đã tắt nguồn máy chủ.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Tắt nguồn thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function reboot(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->rebootVps((int) $vps->provider_vps_id);
            $vps->update(['status' => 'Sẵn sàng']);
            return back()->with('success', 'Đã gửi lệnh khởi động lại máy chủ.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Khởi động lại thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function renew(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet,
        PromotionEngine $promotions
    ) {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->isOverdue5Days()) {
            return back()->with('error', 'Máy chủ đã quá hạn trên 5 ngày và bị hủy, không thể gia hạn.');
        }

        $cycles = array_keys(config('cloudsunny.billing_cycles', []));
        $validated = $request->validate([
            'billing_cycle' => 'required|in:' . implode(',', $cycles),
            'coupon_code' => 'nullable|string|max:48',
            'expected_total' => 'nullable|integer|min:0',
        ]);

        $user = Auth::user();
        $plan = $pricing->getPlan((string) $vps->plan_id, $user);
        if (!$plan) {
            return back()->with('error', 'Không tìm thấy gói cấu hình gốc để tính phí gia hạn.');
        }

        $billingCycle = $validated['billing_cycle'];
        $months = $pricing->monthsForCycle($billingCycle);
        $addonCpu = max(0, (int) $vps->cpu - (int) $plan['cores']);
        $addonRam = max(0, (int) $vps->ram - (int) $plan['ram']);
        $addonDisk = max(0, (int) $vps->disk - (int) $plan['disk']);
        $couponCode = $validated['coupon_code'] ?? null;

        $quote = $pricing->quoteCycleForPlan($plan, $billingCycle, $user, $addonCpu, $addonRam, $addonDisk, true, $couponCode);

        if (!empty($couponCode) && isset($quote['coupon']) && !$quote['coupon']['valid']) {
            return back()->with('error', $quote['coupon']['message']);
        }

        $amount = $quote['final_price'];
        $providerCost = $quote['provider_cost'];

        if ($request->filled('expected_total') && (int) $request->input('expected_total') !== (int) $amount) {
            return back()->with('error', 'Phí gia hạn theo mã/chu kỳ mới là ' . number_format($amount, 0, ',', '.') . 'đ. Vui lòng kiểm tra lại và xác nhận.');
        }

        if ($user->balance < $amount) {
            return back()->with('error', 'Số dư ví không đủ để gia hạn (' . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $transaction = null;
        try {
            $appliedCoupon = ($quote['coupon']['valid'] ?? false) ? $quote['coupon']['code'] : null;

            $transaction = $wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'campaign_id' => $quote['campaign']['id'] ?? null,
                'coupon_code' => $appliedCoupon,
                'type' => 'renew',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Gia hạn VPS: {$vps->label} ({$billingCycle})",
            ]);

            if ($appliedCoupon && ($quote['coupon']['discount_amount'] ?? 0) > 0) {
                $promotions->recordCouponUsage($appliedCoupon, $user, $transaction, (int) $quote['coupon']['discount_amount']);
            }

            $api->forAccount($vps->cloudSunnyAccount)->renewVps((int) $vps->provider_vps_id, $billingCycle);

            $baseDate = $vps->expires_at && $vps->expires_at->isFuture()
                ? $vps->expires_at->copy()
                : Carbon::now();

            $newExpiresAt = $baseDate->addMonths($months);
            if ($vps->created_at) {
                $targetDay = min($vps->created_at->day, $newExpiresAt->daysInMonth);
                $newExpiresAt->day($targetDay);
            }

            $vps->update([
                'billing_cycle' => $billingCycle,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'expires_at' => $newExpiresAt,
                'status' => $vps->status === 'Hết hạn' ? 'Sẵn sàng' : $vps->status,
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'renew_vps', "txn_{$transaction->id}");

            if (!empty($user->email)) {
                try {
                    $cycleLabel = config("cloudsunny.billing_cycles.{$billingCycle}.label") ?? "{$months} Tháng";
                    Mail::to($user->email)->queue(new ServiceRenewed(
                        userName: $user->name ?: 'bạn',
                        serviceType: 'vps',
                        serviceName: $vps->label,
                        ipAddress: $vps->public_ip,
                        cycleLabel: $cycleLabel,
                        amountPaid: (int) $amount,
                        newExpiresAt: $newExpiresAt->format('d/m/Y H:i'),
                        manageUrl: route('vps.show', $vps->id),
                        isAuto: false
                    ));
                } catch (\Throwable $mailErr) {
                    Log::error('Failed to queue VPS renew receipt email', ['vps_id' => $vps->id, 'msg' => $mailErr->getMessage()]);
                }
            }

            return back()->with('success', "Đã gia hạn máy chủ {$vps->label} thêm {$months} tháng.");
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn không thành công, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn không thành công, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function toggleAutoRenew(Request $request, VpsInstance $vps)
    {
        $this->authorizeVps($vps);

        if ($vps->normalizedState() === 'deleted') {
            return back()->with('error', 'Máy chủ đã ngừng hoạt động, không thể bật tự động gia hạn.');
        }

        $next = $request->has('is_auto_renew')
            ? $request->boolean('is_auto_renew')
            : !$vps->is_auto_renew;

        $vps->update(['is_auto_renew' => $next]);

        return back()->with(
            'success',
            $next
                ? "Đã bật tự động gia hạn cho VPS {$vps->label} (hệ thống tự gia hạn trước 24h khi ví đủ số dư)."
                : "Đã tắt tự động gia hạn cho VPS {$vps->label}."
        );
    }

    public function upgrade(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet
    ) {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->isOverdue5Days() || $vps->isExpired()) {
            return back()->with('error', 'Máy chủ đã hết hạn hoặc bị hủy. Vui lòng gia hạn trước khi nâng cấp phần cứng.');
        }

        $validated = $request->validate([
            'addon_cpu' => 'nullable|integer|min:0|max:16',
            'addon_ram' => 'nullable|integer|min:0|max:64',
            'addon_disk' => 'nullable|integer|min:0|max:1000',
            'expected_total' => 'nullable|integer|min:0',
        ]);

        $addonCpu = (int) ($validated['addon_cpu'] ?? 0);
        $addonRam = (int) ($validated['addon_ram'] ?? 0);
        $addonDisk = (int) ($validated['addon_disk'] ?? 0);

        if ($addonCpu < 1 && $addonRam < 1 && $addonDisk < 1) {
            return back()->with('error', 'Vui lòng chọn ít nhất một thông số CPU, RAM hoặc Ổ cứng cần nâng cấp.');
        }

        if ($addonDisk > 0 && $addonDisk % 10 !== 0) {
            return back()->with('error', 'Dung lượng ổ cứng nâng cấp phải là bội số của 10 GB.');
        }

        $user = Auth::user();
        $quote = $pricing->quoteMidCycleUpgrade($vps, $addonCpu, $addonRam, $addonDisk, $user);
        $amount = $quote['final_price'];
        $providerCost = $quote['provider_cost'];

        if ($request->filled('expected_total') && (int) $request->input('expected_total') !== (int) $amount) {
            return back()->with('error', 'Phí nâng cấp phần cứng mới là ' . number_format($amount, 0, ',', '.') . 'đ. Vui lòng kiểm tra lại và xác nhận.');
        }

        if ($user->balance < $amount) {
            return back()->with('error', 'Số dư ví không đủ để nâng cấp (' . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $transaction = null;
        try {
            $transaction = $wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'type' => 'upgrade',
                'provider_cost' => $providerCost,
                'discount_amount' => $quote['discount_amount'],
                'description' => "Nâng cấp phần cứng VPS: {$vps->label} (+{$addonCpu}C/+{$addonRam}G/+{$addonDisk}GB)",
            ]);

            $api->forAccount($vps->cloudSunnyAccount)->upgradeVps((int) $vps->provider_vps_id, $addonCpu, $addonRam, $addonDisk);

            $vps->update([
                'cpu' => (int) $vps->cpu + $addonCpu,
                'ram' => (int) $vps->ram + $addonRam,
                'disk' => (int) $vps->disk + $addonDisk,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'status' => 'Đang khởi động lại',
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'upgrade_vps', "txn_{$transaction->id}");

            return back()->with('success', 'Đã nâng cấp tài nguyên thành công. Máy chủ đang tự khởi động lại để nhận phần cứng mới.');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Nâng cấp thất bại, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Nâng cấp thất bại, đã hoàn tiền: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function changePassword(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->normalizedState() !== 'running') {
            return back()->with('error', 'Máy chủ cần ở trạng thái Đang chạy trước khi đổi mật khẩu OS.');
        }

        $validated = $request->validate([
            'password_old' => 'required|string',
            'password_new' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z0-9]+$/',
            ],
        ], [
            'password_new.min' => 'Mật khẩu mới cần từ 12 ký tự trở lên.',
            'password_new.regex' => 'Mật khẩu mới gồm chữ hoa, chữ thường và chữ số (không chứa ký tự đặc biệt).',
            'password_new.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->changePasswordVps(
                (int) $vps->provider_vps_id,
                $validated['password_old'],
                $validated['password_new'],
                $request->input('password_new_confirmation')
            );

            $vps->update(['root_password' => $validated['password_new']]);

            return back()->with('success', 'Đã cập nhật mật khẩu truy cập máy chủ thành công.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đổi mật khẩu thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function rebuild(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        $validated = $request->validate([
            'os_id' => 'required|integer|min:1',
        ]);

        try {
            $response = $api->forAccount($vps->cloudSunnyAccount)->rebuildVps((int) $vps->provider_vps_id, (int) $validated['os_id']);
            $serverInfo = is_array($response) ? ($response[0] ?? $response) : [];

            $updateData = [
                'provider_os_id' => (int) $validated['os_id'],
                'status' => 'Đang khởi tạo...',
            ];

            if (!empty($serverInfo['password'])) {
                $updateData['root_password'] = $serverInfo['password'];
            }

            $vps->update($updateData);

            return back()->with('success', 'Đã gửi lệnh cài đặt lại Hệ điều hành. Quá trình hoàn tất trong 2–5 phút.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Cài đặt lại OS thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Đổi địa chỉ IPv4 cho VPS (tính phí theo đơn giá addon-change-ip trong bảng giá Admin).
     */
    public function changeIp(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet
    ) {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->normalizedState() !== 'running') {
            return back()->with('error', 'Máy chủ cần ở trạng thái Đang chạy (Bật) mới có thể đổi địa chỉ IPv4.');
        }

        $validated = $request->validate([
            'password_vps' => 'required|string|min:1|max:128',
        ], [
            'password_vps.required' => 'Vui lòng nhập mật khẩu hiện tại của máy chủ để xác nhận thao tác đổi IP.',
        ]);

        $addonRates = $pricing->getAddonRates();
        if (empty($addonRates['change_ip_active'])) {
            return back()->with('error', 'Chức năng đổi IPv4 hiện đang tạm bảo trì. Vui lòng thử lại sau.');
        }

        $user = Auth::user();
        $apiClient = $api->forAccount($vps->cloudSunnyAccount);
        $providerCost = (int) ($addonRates['cost_change_ip'] ?? 20000);
        $retailBase = (int) ($addonRates['change_ip'] ?? 30000);

        // Kiểm tra điều kiện đổi IP bên hạ tầng trước khi trừ ví khách hàng
        try {
            $remoteCfg = $apiClient->getChangeIpConfig((int) $vps->provider_vps_id);
            if (isset($remoteCfg['enable']) && $remoteCfg['enable'] === false) {
                return back()->with('error', 'Chức năng đổi IPv4 hiện đang tạm khóa bảo trì từ trung tâm dữ liệu.');
            }
            if (!empty($remoteCfg['price']) && (int) $remoteCfg['price'] > 0) {
                $providerCost = (int) $remoteCfg['price'];
            }
            $remoteVps = $remoteCfg['vps'] ?? null;
            if (is_array($remoteVps) && isset($remoteVps['can_change_ip']) && $remoteVps['can_change_ip'] === false) {
                $reason = CloudSunnyApiService::sanitizeErrorMessage((string) ($remoteVps['reason'] ?? 'Máy chủ hiện chưa đủ điều kiện đổi IP.'));
                return back()->with('error', $reason);
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể kiểm tra điều kiện đổi IP: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        $amount = max($providerCost, $pricing->applyUserDiscount($retailBase, $user));
        if ($user->balance < $amount) {
            return back()->with('error', 'Số dư ví không đủ để đổi IPv4 (' . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = \Illuminate\Support\Facades\Cache::lock("vps_change_ip_{$vps->id}", 135);
        if (!$lock->get()) {
            return back()->with('error', 'Máy chủ này đang xử lý một yêu cầu đổi IP khác. Vui lòng chờ hoàn tất.');
        }

        @set_time_limit(140);

        $oldIp = $vps->public_ip;
        $transaction = null;
        try {
            $transaction = $wallet->debit($user, $amount, [
                'vps_instance_id' => $vps->id,
                'type' => 'change_ip',
                'provider_cost' => $providerCost,
                'discount_amount' => max(0, $retailBase - $amount),
                'description' => "Đổi địa chỉ IPv4 VPS: {$vps->label}" . ($oldIp ? " (IP cũ: {$oldIp})" : ''),
            ]);

            $apiClient->changeIpVps((int) $vps->provider_vps_id, $validated['password_vps']);

            $vps->update([
                'status' => 'Đang đổi IP...',
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
            ]);

            app(AffiliateService::class)->processCommission($user, $amount, 'change_ip_vps', "txn_{$transaction->id}");

            return back()->with('success', 'Đã tiếp nhận yêu cầu đổi IPv4! Hệ thống đang cấp IP mới (2–5 phút) và sẽ tự cập nhật trên màn hình.');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví không đủ.');
            }
            // Kiểm tra lại trạng thái thực tế nếu request bị timeout/lỗi mạng để tránh hoàn tiền nhầm khi upstream đã nhận lệnh
            $acceptedUpstream = false;
            if ($transaction) {
                try {
                    $latest = $apiClient->getVps((int) $vps->provider_vps_id);
                    $latestStatus = strtolower((string) ($latest['status'] ?? ''));
                    $latestIp = (string) ($latest['ip'] ?? $latest['ip_address'] ?? '');
                    if ($latestStatus === 'change_ip' || ($oldIp && $latestIp !== '' && $latestIp !== $oldIp)) {
                        $acceptedUpstream = true;
                        $this->syncRemoteVps($vps, $latest);
                    }
                } catch (\Throwable $ignore) {
                }

                if ($acceptedUpstream) {
                    return back()->with('success', 'Yêu cầu đổi IPv4 đã được cụm máy chủ tiếp nhận! IP mới đang được cập nhật tự động.');
                }

                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }

            return back()->with('error', 'Đổi IPv4 chưa thành công (đã hoàn 100% tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Đổi IPv4 chưa thành công (đã hoàn 100% tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    /**
     * Mở rộng phân vùng ổ cứng (Extend Disk) sau khi mua thêm Addon SSD.
     */
    public function extendDisk(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->normalizedState() !== 'running') {
            return back()->with('error', 'Máy chủ cần ở trạng thái Đang chạy (Bật) mới có thể mở rộng phân vùng ổ cứng.');
        }

        $validated = $request->validate([
            'password_vps' => 'required|string|min:1|max:128',
        ], [
            'password_vps.required' => 'Vui lòng nhập mật khẩu hiện tại của máy chủ để hệ thống truy cập và nới phân vùng SSD.',
        ]);

        $lock = \Illuminate\Support\Facades\Cache::lock("vps_extend_disk_{$vps->id}", 135);
        if (!$lock->get()) {
            return back()->with('error', 'Máy chủ này đang xử lý yêu cầu mở rộng ổ cứng. Vui lòng chờ hoàn tất.');
        }

        @set_time_limit(140);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->extendDiskVps(
                (int) $vps->provider_vps_id,
                $validated['password_vps']
            );

            $vps->update(['status' => 'Đang mở rộng ổ cứng...']);

            return back()->with('success', 'Đã gửi yêu cầu mở rộng phân vùng SSD! Máy chủ sẽ tạm bảo trì dưới 5 phút và tự bật lại khi hoàn tất.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Mở rộng ổ cứng chưa thành công: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function console(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);
        $this->requireCloudSunnyLink($vps);

        if ($vps->normalizedState() !== 'running') {
            return redirect()->route('vps.show', $vps)->with('error', 'Máy chủ cần ở trạng thái Đang chạy để kết nối VNC Console.');
        }

        @set_time_limit(90);

        try {
            $response = $api->forAccount($vps->cloudSunnyAccount)->consoleVps((int) $vps->provider_vps_id);
            $consoleData = isset($response['ticket']) ? $response : ($response['data'] ?? null);

            if (!$consoleData || empty($consoleData['ticket']) || empty($consoleData['host'])) {
                throw new \Exception('Chưa lấy được phiên kết nối VNC từ máy chủ vật lý.');
            }

            return Inertia::render('Dashboard/VpsConsole', [
                'vps' => [
                    'id' => $vps->id,
                    'label' => $vps->label,
                    'public_ip' => $vps->public_ip,
                ],
                'ticket' => $consoleData['ticket'],
                'host' => $consoleData['host'],
                'backUrl' => route('vps.show', $vps),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Client VNC Console failed', [
                'vps_id' => $vps->id,
                'provider_vps_id' => $vps->provider_vps_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('vps.show', $vps)->with('error', 'Không thể mở Console: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function destroy(VpsInstance $vps, CloudSunnyApiService $api)
    {
        $this->authorizeVps($vps);

        try {
            if ($vps->normalizedState() !== 'deleted' && $vps->cloudSunnyAccount && $vps->provider_vps_id) {
                try {
                    $api->forAccount($vps->cloudSunnyAccount)->shutdownVps((int) $vps->provider_vps_id);
                } catch (\Throwable $e) {
                }
            }

            $vps->update([
                'status' => 'Đã xóa',
                'is_auto_renew' => false,
            ]);

            return redirect()
                ->route('vps.index', ['status' => 'deleted'])
                ->with('success', "Đã hủy máy chủ {$vps->label} và chuyển sang mục Đã xóa.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Xóa máy chủ thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    private function authorizeVps(VpsInstance $vps): void
    {
        $user = Auth::user();
        if (!$user || (int) $vps->user_id !== (int) $user->id) {
            abort(403, 'Bạn không có quyền thao tác trên máy chủ này.');
        }
    }

    private function requireCloudSunnyLink(VpsInstance $vps): void
    {
        if ($vps->isOverdue5Days()) {
            abort(400, 'Máy chủ này đã bị hủy và thu hồi tài nguyên.');
        }
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            abort(400, 'Máy chủ chưa hoàn tất liên kết tới node vật lý.');
        }
    }

    private function syncRemoteVps(VpsInstance $vps, array $remote): void
    {
        $updateData = [
            'public_ip' => $this->remoteValue($remote, ['ip', 'ip_address', 'ipv4', 'main_ip', 'public_ip'], $vps->public_ip),
            'login_username' => $this->remoteValue($remote, ['username', 'user', 'login_username', 'login_user'], $vps->login_username),
            'root_password' => $this->remoteValue($remote, ['password', 'root_password', 'login_password'], $vps->root_password),
            'status' => VpsInstance::mapUpstreamStatus((string) $this->remoteValue($remote, ['status', 'state', 'power_status', 'vm_status', 'trang_thai'], $vps->status)),
            'provider_payload' => $remote,
        ];

        $remoteDueDate = $this->remoteValue($remote, ['next_due_date', 'text_next_due_date', 'expires_at', 'ngay_het_han']);
        if (!empty($remoteDueDate)) {
            try {
                $parsedDue = Carbon::parse($remoteDueDate);
                $curExp = $vps->expires_at ? $vps->expires_at->copy() : Carbon::now();
                $updateData['expires_at'] = $curExp->setDate($parsedDue->year, $parsedDue->month, $parsedDue->day);
            } catch (\Throwable $e) {
            }
        }

        $vps->update($updateData);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DedicatedServer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyDedicatedPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;

class DedicatedServerController extends Controller
{
    public function index(Request $request)
    {
        $tab = (string) $request->input('tab', 'all');
        if (!in_array($tab, ['all', 'active', 'waiting', 'expired', 'deleted'], true)) {
            $tab = 'all';
        }
        $search = trim((string) $request->input('search', ''));
        $now = now();

        $query = DedicatedServer::with(['user:id,name,email'])->latest();

        if ($tab === 'active') {
            $query->whereNotIn('status', ['Chờ bàn giao', 'Hết hạn', 'Đã xóa', 'Đã xoá', 'Đã hủy'])
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($tab === 'waiting') {
            $query->where('status', 'Chờ bàn giao');
        } elseif ($tab === 'expired') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Đã hủy', 'Chờ bàn giao'])
                ->where(fn ($q) => $q->where('status', 'Hết hạn')->orWhere(fn ($sub) => $sub->whereNotNull('expires_at')->where('expires_at', '<', $now)));
        } elseif ($tab === 'deleted') {
            $query->whereIn('status', ['Đã xóa', 'Đã xoá', 'Đã hủy']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('plan_name', 'like', "%{$search}%")
                    ->orWhere('public_ip', 'like', "%{$search}%")
                    ->orWhere('provider_server_id', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $servers = $query->paginate(20)->withQueryString()->through(fn (DedicatedServer $s) => [
            'id' => $s->id,
            'provider_server_id' => $s->provider_server_id,
            'label' => $s->label,
            'plan_name' => $s->plan_name,
            'public_ip' => $s->public_ip,
            'config' => $s->hardware_config ?? [],
            'status' => $s->displayStatus(),
            'state' => $s->normalizedState(),
            'has_credentials' => $s->hasCredentials(),
            'paid_amount' => (int) $s->paid_amount,
            'provider_cost' => (int) $s->provider_cost,
            'user_id' => $s->user_id,
            'user_name' => $s->user?->name,
            'user_email' => $s->user?->email,
            'expires_at' => $s->expires_at?->format('d/m/Y H:i'),
            'created_at' => $s->created_at?->format('d/m/Y H:i'),
        ]);

        $agg = DedicatedServer::query()
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN status = 'Chờ bàn giao' THEN 1 ELSE 0 END) as waiting_cnt")
            ->selectRaw("SUM(CASE WHEN status IN ('Đã xóa', 'Đã xoá', 'Đã hủy') THEN 1 ELSE 0 END) as deleted_cnt")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Đã hủy', 'Chờ bàn giao') AND (status = 'Hết hạn' OR (expires_at IS NOT NULL AND expires_at < ?)) THEN 1 ELSE 0 END) as expired_cnt", [$now])
            ->first();

        $allCnt = (int) ($agg->all_cnt ?? 0);
        $waitingCnt = (int) ($agg->waiting_cnt ?? 0);
        $deletedCnt = (int) ($agg->deleted_cnt ?? 0);
        $expiredCnt = (int) ($agg->expired_cnt ?? 0);
        $activeCnt = max(0, $allCnt - $waitingCnt - $deletedCnt - $expiredCnt);

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
            'filters' => ['tab' => $tab, 'search' => $search],
            'counts' => [
                'all' => $allCnt,
                'active' => $activeCnt,
                'waiting' => $waitingCnt,
                'expired' => $expiredCnt,
                'deleted' => $deletedCnt,
            ],
        ]);
    }

    public function show(DedicatedServer $server, CloudSunnyDedicatedPricingService $pricing)
    {
        $server->load(['user:id,name,email,balance,discount_percent', 'cloudSunnyAccount:id,label,api_username']);
        $owner = $server->user;

        $product = $pricing->findByProductId((int) $server->provider_product_id, $owner)
            ?? $pricing->findBySlug((string) $server->plan_slug, $owner);
        $renewCycles = [];
        if ($product) {
            foreach ($product['cycles'] ?? [] as $cycleKey => $cyc) {
                $q = $pricing->computeCycleQuote($product, $cycleKey, 1, true);
                if ($q) {
                    $renewCycles[] = [
                        'key' => $cycleKey,
                        'label' => $q['cycle_label'],
                        'months' => (int) $q['months'],
                        'price' => (int) $q['total_price'],
                        'provider_cost' => (int) $q['total_provider_cost'],
                    ];
                }
            }
        }

        $transactions = Transaction::where('dedicated_server_id', $server->id)
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

        return Inertia::render('Admin/Servers/Show', [
            'server' => [
                'id' => $server->id,
                'provider_server_id' => $server->provider_server_id,
                'provider_order_id' => $server->provider_order_id,
                'account_name' => $server->cloudSunnyAccount?->label ?: ($server->cloudSunnyAccount?->api_username ?: 'Chưa gán'),
                'plan_slug' => $server->plan_slug,
                'plan_name' => $server->plan_name,
                'label' => $server->label,
                'config' => $server->hardware_config ?? [],
                'status' => $server->displayStatus(),
                'raw_status' => $server->status,
                'state' => $server->normalizedState(),
                'can_renew' => $server->canRenew(),
                'billing_cycle_label' => $server->billing_cycle_label,
                'is_auto_renew' => (bool) $server->is_auto_renew,
                'public_ip' => $server->public_ip,
                'os_installed' => $server->os_installed,
                'login_username' => $server->login_username,
                'root_password' => $server->root_password,
                'ipmi_url' => $server->ipmi_url,
                'ipmi_username' => $server->ipmi_username,
                'ipmi_password' => $server->ipmi_password,
                'handover_note' => $server->handover_note,
                'paid_amount' => (int) $server->paid_amount,
                'provider_cost' => (int) $server->provider_cost,
                'gross_profit' => (int) $server->paid_amount - (int) $server->provider_cost,
                'is_linked' => (bool) ($server->cloudSunnyAccount && $server->provider_server_id),
                'created_at' => $server->created_at?->format('d/m/Y H:i'),
                'expires_at' => $server->expires_at?->format('d/m/Y H:i'),
                'handed_over_at' => $server->handed_over_at?->format('d/m/Y H:i'),
                'user' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'balance' => (int) $owner->balance,
                ] : null,
            ],
            'renewCycles' => $renewCycles,
            'transactions' => $transactions,
        ]);
    }

    public function sync(DedicatedServer $server, CloudSunnyApiService $api)
    {
        if (!$server->cloudSunnyAccount || !$server->provider_server_id) {
            return back()->with('error', 'Máy chủ chưa liên kết tới hạ tầng gốc.');
        }

        try {
            $remote = $api->forAccount($server->cloudSunnyAccount)->getServer((int) $server->provider_server_id);
            if ($remote) {
                $update = [
                    'status' => !empty($remote['status']) ? DedicatedServer::mapUpstreamStatus((string) $remote['status']) : $server->status,
                    'provider_payload' => $remote,
                ];
                if (!empty($remote['config']) && is_array($remote['config'])) {
                    $update['hardware_config'] = $remote['config'];
                }
                if (!empty($remote['next_due_date'])) {
                    try {
                        $update['expires_at'] = Carbon::parse($remote['next_due_date']);
                    } catch (\Throwable $e) {
                    }
                }
                if ($update['status'] === 'Hoạt động' && !$server->handed_over_at) {
                    $update['handed_over_at'] = now();
                }
                $server->update($update);
            }

            return back()->with('success', "Đã đồng bộ dữ liệu live cho Máy chủ vật lý {$server->label}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Admin gia hạn hộ Máy chủ vật lý (tùy chọn trừ ví chủ sở hữu hoặc gia hạn trực tiếp không thu phí ví khách).
     */
    public function renew(
        Request $request,
        DedicatedServer $server,
        CloudSunnyDedicatedPricingService $pricing,
        CloudSunnyApiService $api,
        WalletService $wallet
    ) {
        if (!$server->canRenew()) {
            return back()->with('error', 'Chỉ có thể gia hạn Máy chủ vật lý đang Hoạt động hoặc đã Hết hạn (chưa bị xóa).');
        }

        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,twomonthly,quarterly,semi_annually,annually,biennially,triennially',
            'charge_owner' => 'required|boolean',
            'admin_note' => 'nullable|string|max:200',
        ]);

        $owner = $server->user;
        $chargeOwner = (bool) $validated['charge_owner'];
        if ($chargeOwner && !$owner) {
            return back()->with('error', 'Máy chủ chưa gắn tài khoản chủ sở hữu để trừ ví.');
        }

        $product = $pricing->findByProductId((int) $server->provider_product_id, $owner)
            ?? $pricing->findBySlug((string) $server->plan_slug, $owner);
        if (!$product) {
            return back()->with('error', 'Không tìm thấy cấu hình giá gia hạn cho máy chủ này.');
        }

        $quote = $pricing->computeCycleQuote($product, $validated['billing_cycle'], 1, true);
        if (!$quote) {
            return back()->with('error', 'Chu kỳ gia hạn này không khả dụng.');
        }

        $amount = $chargeOwner ? (int) $quote['total_price'] : 0;
        $providerCost = (int) $quote['total_provider_cost'];
        $noteSuffix = trim((string) ($validated['admin_note'] ?? '')) !== '' ? ' · ' . trim((string) $validated['admin_note']) : '';

        if ($chargeOwner && $owner->balance < $amount) {
            return back()->with('error', "Ví chủ sở hữu ({$owner->email}) không đủ số dư (" . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("dedicated_renew_{$server->id}", 45);
        if (!$lock->get()) {
            return back()->with('error', 'Máy chủ này đang xử lý yêu cầu gia hạn khác.');
        }

        $apiClient = $api->forAccount($server->cloudSunnyAccount);
        try {
            $priceCheck = $apiClient->checkPrice('renew_server', [
                'ids' => [(int) $server->provider_server_id],
                'billing_cycle' => $validated['billing_cycle'],
            ]);
            if (!empty($priceCheck['total']) && (int) $priceCheck['total'] > 0) {
                $providerCost = (int) $priceCheck['total'];
            }
        } catch (\Throwable $e) {
            $lock->release();
            return back()->with('error', 'Không thể gia hạn lúc này: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        $transaction = null;
        try {
            if ($chargeOwner) {
                $transaction = $wallet->debit($owner, $amount, [
                    'dedicated_server_id' => $server->id,
                    'type' => 'renew',
                    'provider_cost' => $providerCost,
                    'discount_amount' => (int) $quote['discount_amount'],
                    'description' => "[Admin gia hạn hộ] Máy chủ vật lý {$server->label} ({$quote['cycle_label']}){$noteSuffix}",
                ]);
            }

            $apiClient->renewServer((int) $server->provider_server_id, $validated['billing_cycle']);

            $newExpiry = ($server->expires_at && $server->expires_at->isFuture() ? $server->expires_at->copy() : now())
                ->addMonths((int) $quote['months']);

            $server->update([
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'Hoạt động',
                'expires_at' => $newExpiry,
                'paid_amount' => (int) $server->paid_amount + $amount,
                'provider_cost' => (int) $server->provider_cost + $providerCost,
            ]);

            try {
                $remote = $apiClient->getServer((int) $server->provider_server_id);
                if (!empty($remote['next_due_date'])) {
                    $server->update(['expires_at' => Carbon::parse($remote['next_due_date']), 'provider_payload' => $remote]);
                }
            } catch (\Throwable $ignore) {
            }

            if (!$chargeOwner && $owner) {
                Transaction::create([
                    'user_id' => $owner->id,
                    'dedicated_server_id' => $server->id,
                    'type' => 'renew',
                    'status' => 'completed',
                    'amount' => 0,
                    'provider_cost' => $providerCost,
                    'discount_amount' => 0,
                    'balance_before' => (int) $owner->balance,
                    'balance_after' => (int) $owner->balance,
                    'description' => "[Admin gia hạn trực tiếp · 0đ ví khách] Máy chủ vật lý {$server->label} ({$quote['cycle_label']}){$noteSuffix}",
                ]);
            }

            if ($chargeOwner && $transaction) {
                app(AffiliateService::class)->processCommission($owner, $amount, 'renew_server', "txn_{$transaction->id}");
            }

            Log::info('Admin renewed DedicatedServer', [
                'admin_id' => Auth::id(),
                'server_id' => $server->id,
                'cycle' => $validated['billing_cycle'],
                'charge_owner' => $chargeOwner,
                'amount' => $amount,
                'provider_cost' => $providerCost,
            ]);

            return back()->with('success', "Đã gia hạn Máy chủ vật lý {$server->label} thêm {$quote['cycle_label']}" . ($chargeOwner ? ' (trừ ví khách ' . number_format($amount, 0, ',', '.') . 'đ).' : ' (không trừ ví khách).'));
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví chủ sở hữu không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function toggleAutoRenew(DedicatedServer $server)
    {
        if ($server->normalizedState() === 'deleted') {
            return back()->with('error', 'Máy chủ đã ngừng hoạt động, không thể bật tự động gia hạn.');
        }

        $next = !$server->is_auto_renew;
        $server->update(['is_auto_renew' => $next]);

        return back()->with('success', $next ? "Đã bật tự động gia hạn cho Máy chủ {$server->label}." : "Đã tắt tự động gia hạn cho Máy chủ {$server->label}.");
    }

    /**
     * Admin nhập & bàn giao thông tin IP, OS, mật khẩu và IPMI/iDRAC cho khách hàng (vì CloudSunny bàn giao thủ công).
     */
    public function handover(Request $request, DedicatedServer $server)
    {
        $validated = $request->validate([
            'public_ip' => 'nullable|string|max:64',
            'os_installed' => 'nullable|string|max:120',
            'login_username' => 'nullable|string|max:80',
            'root_password' => 'nullable|string|max:200',
            'ipmi_url' => 'nullable|string|max:255',
            'ipmi_username' => 'nullable|string|max:80',
            'ipmi_password' => 'nullable|string|max:200',
            'handover_note' => 'nullable|string|max:2000',
            'status' => 'required|in:Chờ bàn giao,Hoạt động,Hết hạn,Đã xóa',
        ]);

        $update = [
            'public_ip' => trim((string) ($validated['public_ip'] ?? '')) ?: null,
            'os_installed' => trim((string) ($validated['os_installed'] ?? '')) ?: null,
            'login_username' => trim((string) ($validated['login_username'] ?? '')) ?: null,
            'ipmi_url' => trim((string) ($validated['ipmi_url'] ?? '')) ?: null,
            'ipmi_username' => trim((string) ($validated['ipmi_username'] ?? '')) ?: null,
            'handover_note' => trim((string) ($validated['handover_note'] ?? '')) ?: null,
            'status' => $validated['status'],
        ];

        // Chỉ cập nhật mật khẩu nếu Admin nhập mới (hoặc giữ nguyên mật khẩu đã lưu nếu để trống)
        if (array_key_exists('root_password', $validated) && $validated['root_password'] !== null && $validated['root_password'] !== '') {
            $update['root_password'] = $validated['root_password'];
        }
        if (array_key_exists('ipmi_password', $validated) && $validated['ipmi_password'] !== null && $validated['ipmi_password'] !== '') {
            $update['ipmi_password'] = $validated['ipmi_password'];
        }

        if ($validated['status'] === 'Hoạt động' && !$server->handed_over_at) {
            $update['handed_over_at'] = now();
        }

        $server->update($update);

        Log::info('Admin updated dedicated server handover credentials', [
            'admin_id' => Auth::id(),
            'server_id' => $server->id,
            'status' => $validated['status'],
        ]);

        return back()->with('success', "Đã lưu & bàn giao thông tin truy cập cho Máy chủ vật lý {$server->label}.");
    }

    public function reassign(Request $request, DedicatedServer $server)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'disable_auto_renew' => 'sometimes|boolean',
        ]);

        $targetUser = User::findOrFail((int) $data['user_id']);
        $previousUserId = $server->user_id;

        if ((int) $previousUserId === (int) $targetUser->id) {
            return back()->with('info', "Máy chủ {$server->label} hiện đã thuộc tài khoản {$targetUser->email}.");
        }

        $update = ['user_id' => $targetUser->id];
        if ($request->boolean('disable_auto_renew', true)) {
            $update['is_auto_renew'] = false;
        }
        $server->update($update);

        Log::info('Admin reassigned DedicatedServer ownership', [
            'admin_id' => Auth::id(),
            'server_id' => $server->id,
            'from_user_id' => $previousUserId,
            'to_user_id' => $targetUser->id,
        ]);

        return back()->with('success', "Đã chuyển Máy chủ vật lý {$server->label} sang tài khoản {$targetUser->name} ({$targetUser->email}).");
    }
}

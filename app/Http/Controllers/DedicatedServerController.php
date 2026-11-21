<?php

namespace App\Http\Controllers;

use App\Models\DedicatedServer;
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
        $userId = Auth::id();
        $tab = (string) $request->input('tab', 'active');
        if (!in_array($tab, ['active', 'waiting', 'expired', 'deleted', 'all'], true)) {
            $tab = 'active';
        }
        $search = trim((string) $request->input('search', ''));
        $now = now();

        $query = DedicatedServer::where('user_id', $userId)->latest();

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
                    ->orWhere('public_ip', 'like', "%{$search}%");
            });
        }

        $servers = $query->paginate(15)->withQueryString()->through(fn (DedicatedServer $s) => [
            'id' => $s->id,
            'label' => $s->label,
            'plan_name' => $s->plan_name,
            'public_ip' => $s->public_ip,
            'config' => $s->hardware_config ?? [],
            'status' => $s->displayStatus(),
            'state' => $s->normalizedState(),
            'billing_cycle_label' => $s->billing_cycle_label,
            'is_auto_renew' => (bool) $s->is_auto_renew,
            'has_credentials' => $s->hasCredentials(),
            'can_renew' => $s->canRenew(),
            'expires_at' => $s->expires_at?->format('d/m/Y H:i'),
            'created_at' => $s->created_at?->format('d/m/Y H:i'),
        ]);

        $agg = DedicatedServer::where('user_id', $userId)
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

        return Inertia::render('Servers/Index', [
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
        $user = Auth::user();
        if ((int) $server->user_id !== (int) $user->id) {
            if ($user->isAdmin()) {
                return redirect()->route('admin.servers.show', $server);
            }
            abort(403, 'Bạn không có quyền truy cập máy chủ vật lý này.');
        }

        $catalogItem = $pricing->findByProductId((int) $server->provider_product_id, $user)
            ?? $pricing->findBySlug((string) $server->plan_slug, $user);

        $renewCycles = [];
        if ($catalogItem) {
            foreach ($catalogItem['cycles'] as $key => $cyc) {
                $renewCycles[] = [
                    'key' => $key,
                    'label' => $cyc['label'],
                    'months' => (int) $cyc['months'],
                    'price' => (int) ($cyc['final_price'] ?? $cyc['retail']),
                    'list_price' => (int) $cyc['retail'],
                    'save_percent' => (int) ($cyc['save_percent'] ?? 0),
                ];
            }
        }

        return Inertia::render('Servers/Show', [
            'server' => [
                'id' => $server->id,
                'label' => $server->label,
                'plan_slug' => $server->plan_slug,
                'plan_name' => $server->plan_name,
                'config' => $server->hardware_config ?? [],
                'status' => $server->displayStatus(),
                'state' => $server->normalizedState(),
                'billing_cycle' => $server->billing_cycle,
                'billing_cycle_label' => $server->billing_cycle_label,
                'is_auto_renew' => (bool) $server->is_auto_renew,
                'public_ip' => $server->public_ip,
                'os_installed' => $server->os_installed,
                'login_username' => $server->login_username,
                'root_password' => $server->normalizedState() === 'deleted' ? null : $server->root_password,
                'ipmi_url' => $server->ipmi_url,
                'ipmi_username' => $server->ipmi_username,
                'ipmi_password' => $server->normalizedState() === 'deleted' ? null : $server->ipmi_password,
                'handover_note' => $server->handover_note,
                'has_credentials' => $server->hasCredentials(),
                'can_renew' => $server->canRenew(),
                'paid_amount' => (int) $server->paid_amount,
                'expires_at' => $server->expires_at?->format('d/m/Y H:i'),
                'handed_over_at' => $server->handed_over_at?->format('d/m/Y H:i'),
                'created_at' => $server->created_at?->format('d/m/Y H:i'),
            ],
            'renewCycles' => $renewCycles,
            'balance' => (int) $user->balance,
        ]);
    }

    public function sync(DedicatedServer $server, CloudSunnyApiService $api)
    {
        $this->authorizeOwner($server);
        if (!$server->cloudSunnyAccount || !$server->provider_server_id) {
            return back()->with('info', 'Đơn máy chủ đang chờ hệ thống đồng bộ.');
        }

        try {
            $remote = $api->forAccount($server->cloudSunnyAccount)->getServer((int) $server->provider_server_id);
            $this->applyRemote($server, $remote);

            return back()->with('success', 'Đã đồng bộ trạng thái & thời hạn mới nhất từ hạ tầng.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function renew(
        Request $request,
        DedicatedServer $server,
        CloudSunnyDedicatedPricingService $pricing,
        CloudSunnyApiService $api,
        WalletService $wallet
    ) {
        $this->authorizeOwner($server);
        if (!$server->canRenew()) {
            return back()->with('error', 'Chỉ có thể gia hạn Máy chủ vật lý đang Hoạt động hoặc đã Hết hạn (chưa bị xóa).');
        }

        $validated = $request->validate([
            'billing_cycle' => 'required|in:monthly,twomonthly,quarterly,semi_annually,annually,biennially,triennially',
        ]);

        $user = Auth::user();
        $product = $pricing->findByProductId((int) $server->provider_product_id, $user)
            ?? $pricing->findBySlug((string) $server->plan_slug, $user);

        if (!$product) {
            return back()->with('error', 'Không tìm thấy cấu hình giá gia hạn cho máy chủ này. Vui lòng liên hệ hỗ trợ.');
        }

        $quote = $pricing->computeCycleQuote($product, $validated['billing_cycle'], 1, true);
        if (!$quote) {
            return back()->with('error', 'Chu kỳ gia hạn này không khả dụng cho máy chủ của bạn.');
        }

        $amount = (int) $quote['total_price'];
        if ($user->balance < $amount) {
            return back()->with('error', 'Số dư ví không đủ để gia hạn (' . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("dedicated_renew_{$server->id}", 45);
        if (!$lock->get()) {
            return back()->with('error', 'Yêu cầu gia hạn máy chủ này đang được xử lý.');
        }

        $apiClient = $api->forAccount($server->cloudSunnyAccount);
        try {
            $priceCheck = $apiClient->checkPrice('renew_server', [
                'ids' => [(int) $server->provider_server_id],
                'billing_cycle' => $validated['billing_cycle'],
            ]);
            if (!empty($priceCheck['total']) && (int) $priceCheck['total'] > 0) {
                $quote['total_provider_cost'] = (int) $priceCheck['total'];
            }
        } catch (\Throwable $e) {
            $lock->release();
            return back()->with('error', 'Không thể gia hạn lúc này: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        $transaction = null;
        try {
            $transaction = $wallet->debit($user, $amount, [
                'dedicated_server_id' => $server->id,
                'type' => 'renew',
                'provider_cost' => (int) $quote['total_provider_cost'],
                'discount_amount' => (int) $quote['discount_amount'],
                'description' => "Gia hạn Máy chủ vật lý {$server->label} ({$quote['cycle_label']})",
            ]);

            $apiClient->renewServer((int) $server->provider_server_id, $validated['billing_cycle']);

            $newExpiry = ($server->expires_at && $server->expires_at->isFuture() ? $server->expires_at->copy() : now())
                ->addMonths((int) $quote['months']);

            $server->update([
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'Hoạt động',
                'expires_at' => $newExpiry,
                'paid_amount' => (int) $server->paid_amount + $amount,
                'provider_cost' => (int) $server->provider_cost + (int) $quote['total_provider_cost'],
            ]);

            try {
                $remote = $apiClient->getServer((int) $server->provider_server_id);
                $this->applyRemote($server, $remote);
            } catch (\Throwable $ignore) {
            }

            app(AffiliateService::class)->processCommission($user, $amount, 'renew_server', "txn_{$transaction->id}");

            return back()->with('success', "Gia hạn Máy chủ vật lý {$server->label} thêm {$quote['cycle_label']} thành công!");
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Gia hạn thất bại (đã hoàn tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            Log::error('Dedicated server renew failed', ['server_id' => $server->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Gia hạn thất bại (đã hoàn tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function toggleAutoRenew(DedicatedServer $server)
    {
        $this->authorizeOwner($server);
        $server->update(['is_auto_renew' => !$server->is_auto_renew]);

        return back()->with('success', $server->is_auto_renew ? 'Đã bật tự động gia hạn máy chủ vật lý.' : 'Đã tắt tự động gia hạn.');
    }

    public function updateLabel(Request $request, DedicatedServer $server)
    {
        $this->authorizeOwner($server);
        $validated = $request->validate(['label' => 'required|string|min:2|max:120']);
        $server->update(['label' => trim($validated['label'])]);

        return back()->with('success', 'Đã đổi tên gợi nhớ cho máy chủ.');
    }

    private function authorizeOwner(DedicatedServer $server): void
    {
        if ((int) $server->user_id !== (int) Auth::id()) {
            abort(403, 'Bạn không có quyền thao tác trên máy chủ vật lý này.');
        }
    }

    private function applyRemote(DedicatedServer $server, array $remote): void
    {
        if (empty($remote)) {
            return;
        }
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
}

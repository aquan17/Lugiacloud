<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InstanceController extends Controller
{
    public function index(Request $request)
    {
        VpsInstance::syncOverdueStatus();

        $tab = $request->input('tab', 'running');
        if (!in_array($tab, ['running', 'provisioning', 'stopped', 'expired', 'deleted', 'all'], true)) {
            $tab = 'running';
        }
        $search = trim((string) $request->input('search', ''));
        $now = now();

        $query = VpsInstance::with(['user:id,name,email'])->latest();

        if ($tab === 'running') {
            $query->whereNotIn('status', ['Đã tắt', 'Đã xóa', 'Đã xoá', 'Hết hạn', 'Lỗi', 'Lỗi - đã hoàn tiền'])
                ->where('status', 'not like', '%khởi tạo%')
                ->where('status', 'not like', '%xử lý%')
                ->where('status', 'not like', '%cài đặt%')
                ->where('status', 'not like', '%đổi ip%')
                ->where('status', 'not like', '%bảo trì%')
                ->where('status', 'not like', '%mở rộng%')
                ->where('status', 'not like', '%nâng cấp%')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($tab === 'provisioning') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn'])
                ->where(function ($q) {
                    $q->where('status', 'like', '%khởi tạo%')
                        ->orWhere('status', 'like', '%xử lý%')
                        ->orWhere('status', 'like', '%cài đặt%')
                        ->orWhere('status', 'like', '%đổi ip%')
                        ->orWhere('status', 'like', '%bảo trì%')
                        ->orWhere('status', 'like', '%mở rộng%')
                        ->orWhere('status', 'like', '%nâng cấp%')
                        ->orWhereIn('status', ['pending', 'progressing', 'creating', 'waiting', 'renewing', 'rebuild', 'change_ip', 'maintenance', 'upgrade']);
                });
        } elseif ($tab === 'stopped') {
            $query->where('status', 'Đã tắt')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
        } elseif ($tab === 'expired') {
            $query->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
                ->where(function ($q) use ($now) {
                    $q->where('status', 'Hết hạn')
                        ->orWhere(fn ($sub) => $sub->whereNotNull('expires_at')->where('expires_at', '<', $now));
                });
        } elseif ($tab === 'deleted') {
            $query->whereIn('status', ['Đã xóa', 'Đã xoá', 'Lỗi', 'Lỗi - đã hoàn tiền']);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('public_ip', 'like', "%{$search}%")
                    ->orWhere('provider_vps_id', 'like', "%{$search}%")
                    ->orWhere('plan_id', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $instances = $query->paginate(20)->withQueryString()->through(fn (VpsInstance $v) => [
            'id' => $v->id,
            'provider_vps_id' => $v->provider_vps_id,
            'label' => $v->label,
            'plan_id' => $v->plan_id,
            'public_ip' => $v->public_ip,
            'specs' => "{$v->cpu}C · {$v->ram}GB · {$v->disk}GB",
            'status' => $v->displayStatus(),
            'state' => $v->normalizedState(),
            'paid_amount' => (int) $v->paid_amount,
            'provider_cost' => (int) $v->provider_cost,
            'user_id' => $v->user_id,
            'user_name' => $v->user?->name,
            'user_email' => $v->user?->email,
            'expires_at' => $v->expires_at?->format('d/m/Y H:i'),
        ]);

        // 1 truy vấn SQL Aggregate duy nhất cho toàn bộ 6 tab trạng thái
        $nowStr = $now->toDateTimeString();
        $agg = VpsInstance::query()
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN status IN ('Đã xóa', 'Đã xoá', 'Lỗi', 'Lỗi - đã hoàn tiền') THEN 1 ELSE 0 END) as deleted_cnt")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Lỗi', 'Lỗi - đã hoàn tiền') AND (status = 'Hết hạn' OR (expires_at IS NOT NULL AND expires_at < '{$nowStr}')) THEN 1 ELSE 0 END) as expired_cnt")
            ->selectRaw("SUM(CASE WHEN status = 'Đã tắt' AND (expires_at IS NULL OR expires_at >= '{$nowStr}') THEN 1 ELSE 0 END) as stopped_cnt")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('Đã xóa', 'Đã xoá', 'Hết hạn') AND (status LIKE '%khởi tạo%' OR status LIKE '%xử lý%' OR status LIKE '%cài đặt%' OR status LIKE '%đổi ip%' OR status LIKE '%bảo trì%' OR status LIKE '%mở rộng%' OR status LIKE '%nâng cấp%' OR status IN ('pending','progressing','creating','waiting','renewing','rebuild','change_ip','maintenance','upgrade')) THEN 1 ELSE 0 END) as provisioning_cnt")
            ->first();

        $allCnt = (int) ($agg->all_cnt ?? 0);
        $deletedCnt = (int) ($agg->deleted_cnt ?? 0);
        $expiredCnt = (int) ($agg->expired_cnt ?? 0);
        $stoppedCnt = (int) ($agg->stopped_cnt ?? 0);
        $provisioningCnt = (int) ($agg->provisioning_cnt ?? 0);
        $runningCnt = max(0, $allCnt - $deletedCnt - $expiredCnt - $stoppedCnt - $provisioningCnt);

        return Inertia::render('Admin/Instances/Index', [
            'instances' => $instances,
            'filters' => ['tab' => $tab, 'search' => $search],
            'counts' => [
                'all' => $allCnt,
                'running' => $runningCnt,
                'provisioning' => $provisioningCnt,
                'stopped' => $stoppedCnt,
                'expired' => $expiredCnt,
                'deleted' => $deletedCnt,
            ],
        ]);
    }

    /**
     * Trang xem chi tiết & quản trị riêng 1 Cloud VPS hoàn toàn bên trong Admin Backoffice.
     */
    public function show(VpsInstance $vps, CloudSunnyPricingService $pricing)
    {
        $vps->load(['user:id,name,email,balance', 'cloudSunnyAccount:id,label,api_username']);

        $plan = $pricing->getPlan((string) $vps->plan_id);
        $images = $pricing->getImages((int) ($vps->provider_product_id ?: 1));
        $osMeta = $images[$vps->provider_os_id] ?? null;
        $osLabel = $osMeta['label'] ?? ($vps->provider_payload['os'] ?? 'Linux / Windows');
        $baseDisk = (int) ($plan['disk'] ?? $vps->disk);
        $daysUntilExpiry = $vps->expires_at
            ? (int) now()->startOfDay()->diffInDays($vps->expires_at->copy()->startOfDay(), false)
            : null;

        $transactions = Transaction::where('vps_instance_id', $vps->id)
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

        return Inertia::render('Admin/Instances/Show', [
            'vps' => [
                'id' => $vps->id,
                'provider_vps_id' => $vps->provider_vps_id,
                'provider_order_id' => $vps->provider_order_id,
                'account_name' => $vps->cloudSunnyAccount?->label ?: ($vps->cloudSunnyAccount?->api_username ?: 'Chưa gán'),
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
                'base_disk' => $baseDisk,
                'can_extend_disk' => (int) $vps->disk > $baseDisk,
                'os_id' => (int) $vps->provider_os_id,
                'os_label' => $osLabel,
                'os_distro' => $pricing->detectDistroKey((string) $osLabel),
                'status' => $vps->displayStatus(),
                'raw_status' => $vps->status,
                'state' => $vps->normalizedState(),
                'is_auto_renew' => (bool) $vps->is_auto_renew,
                'billing_cycle' => $vps->billing_cycle,
                'billing_cycle_label' => $vps->billing_cycle_label,
                'paid_amount' => (int) $vps->paid_amount,
                'provider_cost' => (int) $vps->provider_cost,
                'gross_profit' => (int) $vps->paid_amount - (int) $vps->provider_cost,
                'created_at' => $vps->created_at?->format('d/m/Y H:i'),
                'expires_at' => $vps->expires_at?->format('d/m/Y H:i'),
                'days_until_expiry' => $daysUntilExpiry,
                'is_deleted' => $vps->isOverdue5Days(),
                'is_linked' => (bool) ($vps->cloudSunnyAccount && $vps->provider_vps_id),
                'user' => $vps->user ? [
                    'id' => $vps->user->id,
                    'name' => $vps->user->name,
                    'email' => $vps->user->email,
                    'balance' => (int) $vps->user->balance,
                ] : null,
            ],
            'images' => array_values($images),
            'transactions' => $transactions,
        ]);
    }

    public function sync(VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới tài khoản hạ tầng gốc.');
        }

        try {
            $remote = $api->forAccount($vps->cloudSunnyAccount)->getVps((int) $vps->provider_vps_id);
            if ($remote) {
                $updateData = [
                    'public_ip' => $remote['ip'] ?? $remote['ip_address'] ?? $vps->public_ip,
                    'login_username' => $remote['username'] ?? $vps->login_username,
                    'status' => VpsInstance::mapUpstreamStatus((string) ($remote['status'] ?? $vps->status)),
                    'provider_payload' => $remote,
                ];
                if (!empty($remote['password'])) {
                    $updateData['root_password'] = $remote['password'];
                }
                if (!empty($remote['next_due_date'])) {
                    try {
                        $parsed = Carbon::parse($remote['next_due_date']);
                        $cur = $vps->expires_at ? $vps->expires_at->copy() : Carbon::now();
                        $updateData['expires_at'] = $cur->setDate($parsed->year, $parsed->month, $parsed->day);
                    } catch (\Throwable $e) {
                    }
                }
                $vps->update($updateData);
            }

            return back()->with('success', "Đã đồng bộ dữ liệu live mới nhất cho VPS {$vps->label}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Đồng bộ thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function power(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate(['action' => 'required|in:boot,shutdown,reboot']);
        $apiClient = $api->forAccount($vps->cloudSunnyAccount);
        $pid = (int) $vps->provider_vps_id;

        try {
            match ($validated['action']) {
                'boot' => [$apiClient->bootVps($pid), $vps->update(['status' => 'Sẵn sàng'])],
                'shutdown' => [$apiClient->shutdownVps($pid), $vps->update(['status' => 'Đã tắt'])],
                'reboot' => [$apiClient->rebootVps($pid), $vps->update(['status' => 'Đang khởi động lại'])],
            };

            return back()->with('success', 'Đã thực thi lệnh nguồn cho máy chủ.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Thao tác nguồn thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function changePassword(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate([
            'password_old' => 'required|string',
            'password_new' => [
                'required',
                'string',
                'min:12',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z0-9]+$/',
            ],
        ], [
            'password_new.min' => 'Mật khẩu mới cần tối thiểu 12 ký tự.',
            'password_new.regex' => 'Mật khẩu mới cần có chữ hoa, chữ thường, chữ số (không ký tự đặc biệt).',
        ]);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->changePasswordVps(
                (int) $vps->provider_vps_id,
                $validated['password_old'],
                $validated['password_new'],
                $validated['password_new']
            );
            $vps->update(['root_password' => $validated['password_new']]);

            return back()->with('success', 'Đã đổi mật khẩu OS thành công.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Đổi mật khẩu thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function rebuild(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate(['os_id' => 'required|integer|min:1']);

        try {
            $res = $api->forAccount($vps->cloudSunnyAccount)->rebuildVps((int) $vps->provider_vps_id, (int) $validated['os_id']);
            $info = is_array($res) ? ($res[0] ?? $res) : [];
            $update = [
                'provider_os_id' => (int) $validated['os_id'],
                'status' => 'Đang cài đặt lại...',
            ];
            if (!empty($info['password'])) {
                $update['root_password'] = $info['password'];
            }
            $vps->update($update);

            return back()->with('success', 'Đã gửi lệnh cài đặt lại hệ điều hành.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Cài lại OS thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function extendDisk(Request $request, VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }

        $validated = $request->validate(['password_vps' => 'required|string|min:1|max:128']);

        try {
            $api->forAccount($vps->cloudSunnyAccount)->extendDiskVps((int) $vps->provider_vps_id, $validated['password_vps']);
            $vps->update(['status' => 'Đang mở rộng ổ cứng...']);

            return back()->with('success', 'Đã gửi yêu cầu mở rộng phân vùng SSD.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Mở rộng ổ cứng thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    public function console(VpsInstance $vps, CloudSunnyApiService $api)
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return redirect()->route('admin.instances.show', $vps)->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }

        if ($vps->normalizedState() !== 'running') {
            return redirect()->route('admin.instances.show', $vps)->with('error', 'Máy chủ cần ở trạng thái Đang chạy mới có thể mở VNC Console.');
        }

        @set_time_limit(90);

        try {
            $response = $api->forAccount($vps->cloudSunnyAccount)->consoleVps((int) $vps->provider_vps_id);
            $consoleData = isset($response['ticket']) ? $response : ($response['data'] ?? null);

            if (!$consoleData || empty($consoleData['ticket']) || empty($consoleData['host'])) {
                throw new \RuntimeException('Không lấy được phiên kết nối VNC.');
            }

            return Inertia::render('Dashboard/VpsConsole', [
                'vps' => [
                    'id' => $vps->id,
                    'label' => $vps->label,
                    'public_ip' => $vps->public_ip,
                ],
                'ticket' => $consoleData['ticket'],
                'host' => $consoleData['host'],
                'backUrl' => route('admin.instances.show', $vps),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin VNC Console failed', [
                'vps_id' => $vps->id,
                'provider_vps_id' => $vps->provider_vps_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.instances.show', $vps)->with('error', 'Không mở được VNC Console: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }
    }

    /**
     * Xem trước số tiền CloudSunny sẽ hoàn về tài khoản đại lý khi hủy VPS (chỉ đọc, chưa hủy).
     */
    public function checkRefund(VpsInstance $vps, CloudSunnyApiService $api): JsonResponse
    {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return response()->json(['ok' => false, 'message' => 'VPS chưa liên kết tới tài khoản hạ tầng gốc.'], 422);
        }

        try {
            $data = $api->forAccount($vps->cloudSunnyAccount)->checkRefundVps((int) $vps->provider_vps_id);

            return response()->json([
                'ok' => true,
                'data' => [
                    'refund_amount' => (int) ($data['refund_amount'] ?? 0),
                    'refund_coin' => (int) ($data['refund_coin'] ?? 0),
                    'formula' => (string) ($data['formula'] ?? ''),
                    'in_full_refund_window' => (bool) ($data['in_full_refund_window'] ?? false),
                    'full_refund_hours' => (int) ($data['full_refund_hours'] ?? 48),
                    'daily_limit' => $data['daily_limit'] ?? null,
                    'used_today' => (int) ($data['used_today'] ?? 0),
                    'created_at' => $data['created_at'] ?? null,
                    'next_due_date' => $data['next_due_date'] ?? null,
                    'customer_paid_amount' => (int) $vps->paid_amount,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()),
            ], 422);
        }
    }

    /**
     * Admin xác nhận Hủy & Hoàn tiền VPS trên hạ tầng gốc (tùy chọn hoàn thêm tiền vào ví khách hàng).
     */
    public function cancelRefund(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        WalletService $wallet
    ) {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới tài khoản hạ tầng gốc.');
        }

        $validated = $request->validate([
            'customer_refund_amount' => 'nullable|integer|min:0|max:50000000',
            'refund_note' => 'nullable|string|max:200',
        ]);

        // CloudSunny cấm gọi song song nhiều cancel-refund trên cùng 1 tài khoản đại lý
        $lock = Cache::lock("admin_cancel_refund_acc_{$vps->cloudsunny_account_id}", 60);
        if (!$lock->get()) {
            return back()->with('error', 'Hệ thống đang xử lý một yêu cầu hủy & hoàn tiền khác. Vui lòng thử lại sau ít giây.');
        }

        try {
            $res = $api->forAccount($vps->cloudSunnyAccount)->cancelRefundVps((int) $vps->provider_vps_id);
            $providerRefund = (int) ($res['refund_amount'] ?? 0);

            $vps->update([
                'status' => 'Đã xóa',
                'is_auto_renew' => false,
            ]);

            if (isset($res['credit_after'])) {
                $vps->cloudSunnyAccount->update(['credit_vnd' => (int) $res['credit_after']]);
            }

            $customerRefund = (int) ($validated['customer_refund_amount'] ?? 0);
            if ($customerRefund > 0 && $vps->user) {
                $note = trim((string) ($validated['refund_note'] ?? ''));
                $wallet->credit($vps->user, $customerRefund, [
                    'idempotency_key' => "admin_cancel_refund_vps_{$vps->id}",
                    'vps_instance_id' => $vps->id,
                    'type' => 'refund',
                    'description' => "Hoàn tiền hủy VPS {$vps->label} ({$vps->public_ip})" . ($note !== '' ? " — {$note}" : ''),
                ]);
            }

            Log::info('Admin executed cancel-refund VPS', [
                'admin_id' => Auth::id(),
                'vps_id' => $vps->id,
                'provider_vps_id' => $vps->provider_vps_id,
                'provider_refund' => $providerRefund,
                'customer_refund' => $customerRefund,
            ]);

            $msg = "Đã hủy VPS {$vps->label} trên hạ tầng (hoàn " . number_format($providerRefund, 0, ',', '.') . 'đ về ví đại lý).';
            if ($customerRefund > 0) {
                $msg .= ' Đã cộng hoàn ' . number_format($customerRefund, 0, ',', '.') . 'đ vào ví khách hàng.';
            }

            return back()->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Hủy & hoàn tiền thất bại: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    /**
     * Gán / chuyển chủ sở hữu Cloud VPS sang tài khoản User khác.
     */
    public function reassign(Request $request, VpsInstance $vps)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'disable_auto_renew' => 'sometimes|boolean',
        ]);

        $targetUser = User::findOrFail((int) $data['user_id']);
        $previousUserId = $vps->user_id;

        if ((int) $previousUserId === (int) $targetUser->id) {
            return back()->with('info', "VPS {$vps->label} hiện đã thuộc tài khoản {$targetUser->email}.");
        }

        $update = ['user_id' => $targetUser->id];
        if ($request->boolean('disable_auto_renew', true)) {
            $update['is_auto_renew'] = false;
        }
        $vps->update($update);

        Log::info('Admin reassigned VPS ownership', [
            'admin_id' => Auth::id(),
            'vps_id' => $vps->id,
            'from_user_id' => $previousUserId,
            'to_user_id' => $targetUser->id,
        ]);

        return back()->with('success', "Đã chuyển VPS {$vps->label} ({$vps->public_ip}) sang tài khoản {$targetUser->name} ({$targetUser->email}).");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ServiceRenewed;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyPricingService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use RuntimeException;

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
        $vps->load(['user:id,name,email,balance,discount_percent', 'cloudSunnyAccount:id,label,api_username']);

        $owner = $vps->user;
        $plan = $pricing->getPlan((string) $vps->plan_id, $owner);
        $images = $pricing->getImages((int) ($vps->provider_product_id ?: 1));
        $osMeta = $images[$vps->provider_os_id] ?? null;
        $osLabel = $osMeta['label'] ?? ($vps->provider_payload['os'] ?? 'Linux / Windows');
        $baseDisk = (int) ($plan['disk'] ?? $vps->disk);
        $daysUntilExpiry = $vps->expires_at
            ? (int) now()->startOfDay()->diffInDays($vps->expires_at->copy()->startOfDay(), false)
            : null;

        // Tính bảng báo giá gia hạn 7 chu kỳ cho chủ sở hữu (kèm giá vốn để Admin đối chiếu)
        $addonCpuCur = max(0, (int) $vps->cpu - (int) ($plan['cores'] ?? $vps->cpu));
        $addonRamCur = max(0, (int) $vps->ram - (int) ($plan['ram'] ?? $vps->ram));
        $addonDiskCur = max(0, (int) $vps->disk - (int) ($plan['disk'] ?? $vps->disk));
        $renewQuotes = [];
        foreach (config('cloudsunny.billing_cycles', []) as $cycleKey => $meta) {
            if ($plan) {
                $q = $pricing->quoteCycleForPlan($plan, $cycleKey, $owner, $addonCpuCur, $addonRamCur, $addonDiskCur, true);
                $renewQuotes[$cycleKey] = [
                    'key' => $cycleKey,
                    'label' => $meta['label'] ?? ($meta['months'] . ' Tháng'),
                    'months' => (int) ($meta['months'] ?? 1),
                    'price' => (int) $q['final_price'],
                    'list_price' => (int) $q['list_price'],
                    'provider_cost' => (int) $q['provider_cost'],
                ];
            }
        }

        // Hệ số tính phí nâng cấp phần cứng theo thời gian còn lại (khớp quoteMidCycleUpgrade)
        $daysRemaining = $vps->expires_at ? max(1, (int) now()->diffInDays($vps->expires_at, false)) : 30;
        $fullMonths = floor($daysRemaining / 30);
        $remDays = $daysRemaining % 30;
        $chargeFactor = $fullMonths + ($remDays > 15 ? 1 : ($remDays > 0 ? 0.5 : 0));
        $chargeFactor = max(0.5, $chargeFactor);

        $addonRates = $pricing->getAddonRates();
        $changeIpCost = (int) ($addonRates['cost_change_ip'] ?? 20000);
        $changeIpBase = (int) ($addonRates['change_ip'] ?? 30000);
        $changeIpPrice = $owner ? max($changeIpCost, $pricing->applyUserDiscount($changeIpBase, $owner)) : $changeIpBase;

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
                'is_expired' => $vps->isExpired(),
                'is_deleted' => $vps->isOverdue5Days(),
                'is_linked' => (bool) ($vps->cloudSunnyAccount && $vps->provider_vps_id),
                'user' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'balance' => (int) $owner->balance,
                    'discount_percent' => (int) $owner->discount_percent,
                ] : null,
            ],
            'images' => array_values($images),
            'renewQuotes' => array_values($renewQuotes),
            'upgradeMeta' => [
                'days_remaining' => $daysRemaining,
                'charge_factor' => $chargeFactor,
                'cpu_monthly' => (int) ($addonRates['cpu_monthly'] ?? 25000),
                'ram_monthly' => (int) ($addonRates['ram_monthly'] ?? 25000),
                'disk_10gb_monthly' => (int) ($addonRates['disk_10gb_monthly'] ?? 15000),
                'cost_cpu_monthly' => (int) ($addonRates['cost_cpu_monthly'] ?? 16200),
                'cost_ram_monthly' => (int) ($addonRates['cost_ram_monthly'] ?? 16200),
                'cost_disk_10gb_monthly' => (int) ($addonRates['cost_disk_10gb_monthly'] ?? 5400),
                'owner_discount_percent' => (int) ($owner?->discount_percent ?? 0),
            ],
            'changeIpMeta' => [
                'active' => !empty($addonRates['change_ip_active']),
                'price' => $changeIpPrice,
                'provider_cost' => $changeIpCost,
            ],
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

    /**
     * Admin gia hạn hộ VPS (tùy chọn trừ ví chủ sở hữu hoặc gia hạn trực tiếp không thu phí ví khách).
     */
    public function renew(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet
    ) {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }
        if ($vps->isOverdue5Days()) {
            return back()->with('error', 'VPS đã quá hạn hơn 5 ngày và bị hủy, không thể gia hạn.');
        }

        $allowedCycles = array_keys(config('cloudsunny.billing_cycles', ['monthly' => []]));
        $validated = $request->validate([
            'billing_cycle' => 'required|in:' . implode(',', $allowedCycles),
            'charge_owner' => 'required|boolean',
            'admin_note' => 'nullable|string|max:200',
        ]);

        $owner = $vps->user;
        $chargeOwner = (bool) $validated['charge_owner'];
        if ($chargeOwner && !$owner) {
            return back()->with('error', 'VPS chưa gắn với tài khoản chủ sở hữu để trừ ví. Hãy chọn chế độ không trừ ví hoặc chuyển chủ sở hữu trước.');
        }

        $billingCycle = $validated['billing_cycle'];
        $plan = $pricing->getPlan((string) $vps->plan_id, $owner);
        if (!$plan) {
            return back()->with('error', 'Không tìm thấy cấu hình giá cho gói VPS này.');
        }

        $months = $pricing->monthsForCycle($billingCycle);
        $cycleLabel = config("cloudsunny.billing_cycles.{$billingCycle}.label") ?? "{$months} Tháng";
        $addonCpu = max(0, (int) $vps->cpu - (int) ($plan['cores'] ?? $vps->cpu));
        $addonRam = max(0, (int) $vps->ram - (int) ($plan['ram'] ?? $vps->ram));
        $addonDisk = max(0, (int) $vps->disk - (int) ($plan['disk'] ?? $vps->disk));

        $quote = $pricing->quoteCycleForPlan($plan, $billingCycle, $owner, $addonCpu, $addonRam, $addonDisk, true);
        $amount = $chargeOwner ? (int) $quote['final_price'] : 0;
        $providerCost = (int) $quote['provider_cost'];
        $noteSuffix = trim((string) ($validated['admin_note'] ?? '')) !== '' ? ' · ' . trim((string) $validated['admin_note']) : '';

        if ($chargeOwner && $owner->balance < $amount) {
            return back()->with('error', "Ví chủ sở hữu ({$owner->email}) không đủ số dư (cần " . number_format($amount, 0, ',', '.') . 'đ, hiện có ' . number_format((int) $owner->balance, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("vps_admin_renew_{$vps->id}", 60);
        if (!$lock->get()) {
            return back()->with('error', 'VPS này đang có thao tác gia hạn khác đang xử lý.');
        }

        $transaction = null;
        try {
            if ($chargeOwner) {
                $transaction = $wallet->debit($owner, $amount, [
                    'vps_instance_id' => $vps->id,
                    'campaign_id' => $quote['campaign']['id'] ?? null,
                    'type' => 'renew',
                    'provider_cost' => $providerCost,
                    'discount_amount' => (int) ($quote['discount_amount'] ?? 0),
                    'description' => "[Admin gia hạn hộ] VPS {$vps->label} ({$cycleLabel}){$noteSuffix}",
                ]);
            }

            $api->forAccount($vps->cloudSunnyAccount)->renewVps((int) $vps->provider_vps_id, $billingCycle);

            $baseDate = $vps->expires_at && $vps->expires_at->isFuture() ? $vps->expires_at->copy() : Carbon::now();
            $newExpiresAt = $baseDate->addMonths($months);
            if ($vps->created_at) {
                $newExpiresAt->day(min($vps->created_at->day, $newExpiresAt->daysInMonth));
            }

            $vps->update([
                'billing_cycle' => $billingCycle,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'expires_at' => $newExpiresAt,
                'status' => $vps->status === 'Hết hạn' ? 'Sẵn sàng' : $vps->status,
            ]);

            if (!$chargeOwner && $owner) {
                Transaction::create([
                    'user_id' => $owner->id,
                    'vps_instance_id' => $vps->id,
                    'type' => 'renew',
                    'status' => 'completed',
                    'amount' => 0,
                    'provider_cost' => $providerCost,
                    'discount_amount' => 0,
                    'balance_before' => (int) $owner->balance,
                    'balance_after' => (int) $owner->balance,
                    'description' => "[Admin gia hạn trực tiếp · 0đ ví khách] VPS {$vps->label} ({$cycleLabel}){$noteSuffix}",
                ]);
            }

            if ($chargeOwner && $transaction) {
                app(AffiliateService::class)->processCommission($owner, $amount, 'renew_vps', "txn_{$transaction->id}");
            }

            if ($owner && !empty($owner->email)) {
                try {
                    Mail::to($owner->email)->queue(new ServiceRenewed(
                        userName: $owner->name ?: 'bạn',
                        serviceType: 'vps',
                        serviceName: $vps->label,
                        ipAddress: $vps->public_ip,
                        cycleLabel: $cycleLabel,
                        amountPaid: $amount,
                        newExpiresAt: $newExpiresAt->format('d/m/Y H:i'),
                        manageUrl: route('vps.show', $vps->id),
                        isAuto: false
                    ));
                } catch (\Throwable $ignore) {
                }
            }

            Log::info('Admin renewed VPS', [
                'admin_id' => Auth::id(),
                'vps_id' => $vps->id,
                'cycle' => $billingCycle,
                'charge_owner' => $chargeOwner,
                'amount' => $amount,
                'provider_cost' => $providerCost,
            ]);

            return back()->with('success', "Đã gia hạn VPS {$vps->label} thêm {$cycleLabel}" . ($chargeOwner ? ' (trừ ví chủ sở hữu ' . number_format($amount, 0, ',', '.') . 'đ).' : ' (không trừ ví khách).'));
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

    /**
     * Admin nâng cấp CPU/RAM/SSD hộ VPS (tùy chọn trừ ví khách hoặc không thu phí ví khách).
     */
    public function upgrade(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet
    ) {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }
        if ($vps->isOverdue5Days() || $vps->isExpired()) {
            return back()->with('error', 'VPS đã hết hạn hoặc bị hủy. Vui lòng gia hạn trước khi nâng cấp phần cứng.');
        }

        $validated = $request->validate([
            'addon_cpu' => 'nullable|integer|min:0|max:16',
            'addon_ram' => 'nullable|integer|min:0|max:64',
            'addon_disk' => 'nullable|integer|min:0|max:1000',
            'charge_owner' => 'required|boolean',
            'admin_note' => 'nullable|string|max:200',
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

        $owner = $vps->user;
        $chargeOwner = (bool) $validated['charge_owner'];
        if ($chargeOwner && !$owner) {
            return back()->with('error', 'VPS chưa gắn với tài khoản chủ sở hữu để trừ ví.');
        }

        $quote = $pricing->quoteMidCycleUpgrade($vps, $addonCpu, $addonRam, $addonDisk, $owner);
        $amount = $chargeOwner ? (int) $quote['final_price'] : 0;
        $providerCost = (int) $quote['provider_cost'];
        $noteSuffix = trim((string) ($validated['admin_note'] ?? '')) !== '' ? ' · ' . trim((string) $validated['admin_note']) : '';

        if ($chargeOwner && $owner->balance < $amount) {
            return back()->with('error', "Ví chủ sở hữu ({$owner->email}) không đủ số dư để nâng cấp (" . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("vps_admin_upgrade_{$vps->id}", 60);
        if (!$lock->get()) {
            return back()->with('error', 'VPS này đang xử lý yêu cầu nâng cấp khác.');
        }

        $transaction = null;
        try {
            if ($chargeOwner) {
                $transaction = $wallet->debit($owner, $amount, [
                    'vps_instance_id' => $vps->id,
                    'type' => 'upgrade',
                    'provider_cost' => $providerCost,
                    'discount_amount' => (int) ($quote['discount_amount'] ?? 0),
                    'description' => "[Admin nâng cấp hộ] VPS {$vps->label} (+{$addonCpu}C/+{$addonRam}G/+{$addonDisk}GB){$noteSuffix}",
                ]);
            }

            $api->forAccount($vps->cloudSunnyAccount)->upgradeVps((int) $vps->provider_vps_id, $addonCpu, $addonRam, $addonDisk);

            $vps->update([
                'cpu' => (int) $vps->cpu + $addonCpu,
                'ram' => (int) $vps->ram + $addonRam,
                'disk' => (int) $vps->disk + $addonDisk,
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
                'status' => 'Đang khởi động lại',
            ]);

            if (!$chargeOwner && $owner) {
                Transaction::create([
                    'user_id' => $owner->id,
                    'vps_instance_id' => $vps->id,
                    'type' => 'upgrade',
                    'status' => 'completed',
                    'amount' => 0,
                    'provider_cost' => $providerCost,
                    'discount_amount' => 0,
                    'balance_before' => (int) $owner->balance,
                    'balance_after' => (int) $owner->balance,
                    'description' => "[Admin nâng cấp trực tiếp · 0đ ví khách] VPS {$vps->label} (+{$addonCpu}C/+{$addonRam}G/+{$addonDisk}GB){$noteSuffix}",
                ]);
            }

            if ($chargeOwner && $transaction) {
                app(AffiliateService::class)->processCommission($owner, $amount, 'upgrade_vps', "txn_{$transaction->id}");
            }

            Log::info('Admin upgraded VPS', [
                'admin_id' => Auth::id(),
                'vps_id' => $vps->id,
                'addons' => [$addonCpu, $addonRam, $addonDisk],
                'charge_owner' => $chargeOwner,
                'amount' => $amount,
                'provider_cost' => $providerCost,
            ]);

            return back()->with('success', "Đã nâng cấp VPS {$vps->label} (+{$addonCpu} vCPU, +{$addonRam}GB RAM, +{$addonDisk}GB SSD) thành công!");
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                return back()->with('error', 'Số dư ví chủ sở hữu không đủ.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Nâng cấp thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            return back()->with('error', 'Nâng cấp thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    /**
     * Admin đổi IPv4 hộ VPS (tùy chọn trừ ví khách hoặc không thu phí ví khách).
     */
    public function changeIp(
        Request $request,
        VpsInstance $vps,
        CloudSunnyApiService $api,
        CloudSunnyPricingService $pricing,
        WalletService $wallet
    ) {
        if (!$vps->cloudSunnyAccount || !$vps->provider_vps_id) {
            return back()->with('error', 'VPS chưa liên kết tới hạ tầng gốc.');
        }
        if ($vps->normalizedState() !== 'running') {
            return back()->with('error', 'VPS cần ở trạng thái Đang chạy (Bật) mới có thể đổi IPv4.');
        }

        $validated = $request->validate([
            'password_vps' => 'required|string|min:1|max:128',
            'charge_owner' => 'required|boolean',
            'admin_note' => 'nullable|string|max:200',
        ]);

        $owner = $vps->user;
        $chargeOwner = (bool) $validated['charge_owner'];
        if ($chargeOwner && !$owner) {
            return back()->with('error', 'VPS chưa gắn với tài khoản chủ sở hữu để trừ ví.');
        }

        @set_time_limit(140);

        $addonRates = $pricing->getAddonRates();
        $apiClient = $api->forAccount($vps->cloudSunnyAccount);
        $providerCost = (int) ($addonRates['cost_change_ip'] ?? 20000);
        $retailBase = (int) ($addonRates['change_ip'] ?? 30000);

        try {
            $remoteCfg = $apiClient->getChangeIpConfig((int) $vps->provider_vps_id);
            if (isset($remoteCfg['enable']) && $remoteCfg['enable'] === false) {
                return back()->with('error', 'Chức năng đổi IPv4 hiện đang tạm khóa bảo trì từ nhà cung cấp.');
            }
            if (!empty($remoteCfg['price']) && (int) $remoteCfg['price'] > 0) {
                $providerCost = (int) $remoteCfg['price'];
            }
            $remoteVps = $remoteCfg['vps'] ?? null;
            if (is_array($remoteVps) && isset($remoteVps['can_change_ip']) && $remoteVps['can_change_ip'] === false) {
                return back()->with('error', CloudSunnyApiService::sanitizeErrorMessage((string) ($remoteVps['reason'] ?? 'VPS chưa đủ điều kiện đổi IP.')));
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể kiểm tra điều kiện đổi IP: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        $amount = $chargeOwner
            ? ($owner ? max($providerCost, $pricing->applyUserDiscount($retailBase, $owner)) : $retailBase)
            : 0;
        $noteSuffix = trim((string) ($validated['admin_note'] ?? '')) !== '' ? ' · ' . trim((string) $validated['admin_note']) : '';

        if ($chargeOwner && $owner->balance < $amount) {
            return back()->with('error', "Ví chủ sở hữu ({$owner->email}) không đủ số dư để đổi IP (" . number_format($amount, 0, ',', '.') . 'đ).');
        }

        $lock = Cache::lock("vps_change_ip_{$vps->id}", 135);
        if (!$lock->get()) {
            return back()->with('error', 'VPS này đang xử lý yêu cầu đổi IP khác.');
        }

        $oldIp = $vps->public_ip;
        $transaction = null;
        try {
            if ($chargeOwner) {
                $transaction = $wallet->debit($owner, $amount, [
                    'vps_instance_id' => $vps->id,
                    'type' => 'change_ip',
                    'provider_cost' => $providerCost,
                    'discount_amount' => max(0, $retailBase - $amount),
                    'description' => "[Admin đổi IP hộ] VPS {$vps->label}" . ($oldIp ? " (IP cũ: {$oldIp})" : '') . $noteSuffix,
                ]);
            }

            $apiClient->changeIpVps((int) $vps->provider_vps_id, $validated['password_vps']);

            $vps->update([
                'status' => 'Đang đổi IP...',
                'paid_amount' => (int) $vps->paid_amount + $amount,
                'provider_cost' => (int) $vps->provider_cost + $providerCost,
            ]);

            if (!$chargeOwner && $owner) {
                Transaction::create([
                    'user_id' => $owner->id,
                    'vps_instance_id' => $vps->id,
                    'type' => 'change_ip',
                    'status' => 'completed',
                    'amount' => 0,
                    'provider_cost' => $providerCost,
                    'discount_amount' => 0,
                    'balance_before' => (int) $owner->balance,
                    'balance_after' => (int) $owner->balance,
                    'description' => "[Admin đổi IP trực tiếp · 0đ ví khách] VPS {$vps->label}" . ($oldIp ? " (IP cũ: {$oldIp})" : '') . $noteSuffix,
                ]);
            }

            if ($chargeOwner && $transaction) {
                app(AffiliateService::class)->processCommission($owner, $amount, 'change_ip_vps', "txn_{$transaction->id}");
            }

            return back()->with('success', 'Đã gửi lệnh đổi IPv4 thành công! IP mới sẽ tự cập nhật sau 2–5 phút.');
        } catch (\Throwable $e) {
            $acceptedUpstream = false;
            try {
                $latest = $apiClient->getVps((int) $vps->provider_vps_id);
                $latestStatus = strtolower((string) ($latest['status'] ?? ''));
                $latestIp = (string) ($latest['ip'] ?? $latest['ip_address'] ?? '');
                if ($latestStatus === 'change_ip' || ($oldIp && $latestIp !== '' && $latestIp !== $oldIp)) {
                    $acceptedUpstream = true;
                    $vps->update([
                        'public_ip' => $latestIp ?: $vps->public_ip,
                        'status' => VpsInstance::mapUpstreamStatus($latestStatus ?: 'change_ip'),
                        'paid_amount' => (int) $vps->paid_amount + $amount,
                        'provider_cost' => (int) $vps->provider_cost + $providerCost,
                    ]);
                }
            } catch (\Throwable $ignore) {
            }

            if ($acceptedUpstream) {
                return back()->with('success', 'Yêu cầu đổi IPv4 đã được hạ tầng tiếp nhận và đang xử lý!');
            }

            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }

            return back()->with('error', 'Đổi IPv4 thất bại' . ($transaction ? ' (đã hoàn tiền ví khách)' : '') . ': ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }

    public function toggleAutoRenew(VpsInstance $vps)
    {
        if ($vps->normalizedState() === 'deleted') {
            return back()->with('error', 'VPS đã ngừng hoạt động, không thể bật tự động gia hạn.');
        }

        $next = !$vps->is_auto_renew;
        $vps->update(['is_auto_renew' => $next]);

        return back()->with('success', $next ? "Đã bật tự động gia hạn cho VPS {$vps->label}." : "Đã tắt tự động gia hạn cho VPS {$vps->label}.");
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

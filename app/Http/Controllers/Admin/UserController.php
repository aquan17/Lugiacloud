<?php

namespace App\Http\Controllers\Admin;

use App\Constants\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TopupRequest;
use App\Models\User;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $users = User::withCount(['vpsInstances', 'proxyInstances'])
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'balance' => (int) $u->balance,
                'role' => (int) $u->is_admin,
                'role_label' => $u->roleLabel(),
                'discount_percent' => (int) $u->discount_percent,
                'proxy_discount_percent' => (int) $u->proxy_discount_percent,
                'vps_count' => (int) $u->vps_instances_count,
                'proxy_count' => (int) $u->proxy_instances_count,
                'created_at' => $u->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * API tìm nhanh tối đa 15 User theo ID, Email, Tên, SĐT hoặc IP VPS/Proxy (không tải toàn bảng user).
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '' || mb_strlen($q) < 1) {
            return response()->json(['users' => []]);
        }

        $isIpLike = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){1,3}$/', $q);

        $query = User::query()->select('id', 'name', 'email', 'phone');

        $query->where(function ($sub) use ($q, $isIpLike) {
            if (ctype_digit($q)) {
                $sub->orWhere('id', (int) $q);
            }
            $sub->orWhere('email', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%");

            if ($isIpLike) {
                $sub->orWhereHas('vpsInstances', fn ($v) => $v->where('public_ip', 'like', "{$q}%"))
                    ->orWhereHas('proxyInstances', fn ($p) => $p->where('ip', 'like', "{$q}%"))
                    ->orWhereHas('dedicatedServers', fn ($s) => $s->where('public_ip', 'like', "{$q}%"));
            }
        });

        $users = $query->orderBy('id')->limit(15)->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
        ])->values();

        return response()->json(['users' => $users]);
    }

    public function show(User $user)
    {
        $vpsInstances = $user->vpsInstances()->latest()->take(25)->get()->map(fn ($v) => [
            'id' => $v->id,
            'label' => $v->label,
            'public_ip' => $v->public_ip,
            'specs' => "{$v->cpu}C / {$v->ram}GB / {$v->disk}GB",
            'status' => $v->status,
            'expires_at' => $v->expires_at?->format('d/m/Y H:i'),
        ]);

        $proxies = $user->proxyInstances()->latest()->take(25)->get()->map(fn ($p) => [
            'id' => $p->id,
            'endpoint' => $p->ip ? "{$p->ip}:{$p->port}" : 'Đang cấp phát',
            'type_proxy' => $p->type_proxy,
            'status' => $p->status,
            'expires_at' => $p->expires_at?->format('d/m/Y H:i'),
        ]);

        $transactions = $user->transactions()->latest()->take(20)->get()->map(fn ($t) => [
            'id' => $t->id,
            'code' => $t->code,
            'type' => $t->type,
            'status' => $t->status,
            'amount' => (int) $t->amount,
            'coupon_code' => $t->coupon_code,
            'discount_amount' => (int) $t->discount_amount,
            'description' => $t->description,
            'created_at' => $t->created_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Users/Show', [
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'balance' => (int) $user->balance,
                'role' => (int) $user->is_admin,
                'role_label' => $user->roleLabel(),
                'discount_percent' => (int) $user->discount_percent,
                'proxy_discount_percent' => (int) $user->proxy_discount_percent,
                'ref_code' => $user->ref_code,
                'created_at' => $user->created_at?->format('d/m/Y H:i'),
            ],
            'vpsInstances' => $vpsInstances,
            'proxies' => $proxies,
            'transactions' => $transactions,
        ]);
    }

    public function adjustBalance(Request $request, User $user, WalletService $wallet)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|not_in:0|min:-100000000|max:100000000',
            'note' => 'nullable|string|max:200',
        ]);

        $amount = (int) $validated['amount'];
        $note = trim((string) ($validated['note'] ?? 'Admin điều chỉnh số dư'));

        try {
            if ($amount > 0) {
                $wallet->credit($user, $amount, [
                    'type' => 'admin_add',
                    'description' => "Admin cộng tiền: {$note}",
                ]);
            } else {
                $wallet->debit($user, abs($amount), [
                    'type' => 'admin_deduct',
                    'description' => "Admin trừ tiền: {$note}",
                ]);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', 'Số dư người dùng không đủ để trừ.');
        }

        return back()->with('success', ($amount > 0 ? 'Đã cộng ' : 'Đã trừ ') . number_format(abs($amount), 0, ',', '.') . "đ cho {$user->email}.");
    }

    public function updateDiscount(Request $request, User $user)
    {
        $validated = $request->validate([
            'discount_percent' => 'required|integer|min:0|max:100',
            'proxy_discount_percent' => 'required|integer|min:0|max:100',
            'role' => 'nullable|integer|in:0,1,2',
        ]);

        $user->discount_percent = (int) $validated['discount_percent'];
        $user->proxy_discount_percent = (int) $validated['proxy_discount_percent'];

        if (isset($validated['role']) && $user->id !== Auth::id()) {
            $user->is_admin = (int) $validated['role'];
        }

        $user->save();

        return back()->with('success', "Đã lưu chiết khấu & phân quyền cho {$user->email}.");
    }

    public function toggleAdmin(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Không thể tự hạ quyền tài khoản của chính mình.');
        }

        $user->is_admin = $user->isAdmin() ? UserRole::USER : UserRole::ADMIN;
        $user->save();

        return back()->with('success', "Đã cập nhật quyền cho {$user->email}.");
    }

    public function topups(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $query = TopupRequest::with('user:id,name,email');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $topups = $query->orderByDesc('created_at')->paginate(20)->withQueryString()->through(fn (TopupRequest $t) => [
            'id' => $t->id,
            'code' => $t->code,
            'user_id' => $t->user?->id,
            'user_name' => $t->user?->name,
            'user_email' => $t->user?->email,
            'amount' => (int) $t->amount,
            'approved_amount' => $t->approved_amount ? (int) $t->approved_amount : null,
            'bonus_amount' => (int) $t->bonus_amount,
            'status' => $t->status,
            'status_label' => $t->statusLabel(),
            'provider' => $t->provider,
            'admin_note' => $t->admin_note,
            'created_at' => $t->created_at?->format('d/m/Y H:i'),
            'paid_at' => ($t->paid_at ?? $t->processed_at)?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Topups/Index', [
            'topups' => $topups,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function approveTopup(Request $request, TopupRequest $topup, PromotionEngine $promotions, WalletService $wallet)
    {
        if (!$topup->isPending()) {
            return back()->with('error', 'Yêu cầu nạp này đã được xử lý trước đó.');
        }

        $validated = $request->validate([
            'approved_amount' => 'required|integer|min:1000',
            'admin_note' => 'nullable|string|max:200',
        ]);

        DB::transaction(function () use ($validated, $topup, $promotions, $wallet) {
            $lockedTopup = TopupRequest::whereKey($topup->id)->lockForUpdate()->first();
            if (!$lockedTopup || !$lockedTopup->isPending()) {
                return;
            }

            $amount = (int) $validated['approved_amount'];
            $lockedUser = User::whereKey($lockedTopup->user_id)->lockForUpdate()->firstOrFail();
            $lockedUser->balance = (int) $lockedUser->balance + $amount;
            $lockedUser->save();

            $bonusInfo = $promotions->calculateTopupBonus($amount);
            $bonusAmount = (int) ($bonusInfo['bonus_amount'] ?? 0);
            $campaignId = $bonusInfo['campaign']['id'] ?? null;

            $lockedTopup->update([
                'status' => 'approved',
                'approved_amount' => $amount,
                'bonus_amount' => $bonusAmount,
                'campaign_id' => $campaignId,
                'admin_note' => $validated['admin_note'] ?? null,
                'approved_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            if ($bonusAmount > 0) {
                $wallet->credit($lockedUser, $bonusAmount, [
                    'idempotency_key' => "topup_bonus_{$lockedTopup->id}",
                    'campaign_id' => $campaignId,
                    'type' => 'topup_bonus',
                    'description' => "Thưởng sự kiện +{$bonusInfo['bonus_percent']}% cho đơn nạp {$lockedTopup->code}",
                ]);
            }
        });

        return back()->with('success', 'Đã duyệt lệnh nạp ' . number_format($validated['approved_amount'], 0, ',', '.') . 'đ thành công.');
    }

    public function rejectTopup(Request $request, TopupRequest $topup)
    {
        if (!$topup->isPending()) {
            return back()->with('error', 'Yêu cầu nạp này đã được xử lý trước đó.');
        }

        $topup->update([
            'status' => 'rejected',
            'admin_note' => $request->input('admin_note') ?: 'Không tìm thấy giao dịch khớp.',
            'approved_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Đã từ chối yêu cầu nạp tiền.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Không thể xóa tài khoản quản trị đang đăng nhập.');
        }
        if ($user->vpsInstances()->exists() || $user->proxyInstances()->exists() || $user->dedicatedServers()->exists()) {
            return back()->with('error', 'Không thể xóa người dùng đang còn VPS, Proxy hoặc Máy chủ vật lý.');
        }

        $email = $user->email;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Đã xóa người dùng {$email} khỏi hệ thống.");
    }
}

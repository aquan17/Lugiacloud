<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CloudSunnyAccount;
use App\Models\ProxyInstance;
use App\Models\VpsInstance;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyAccountSyncService;
use App\Services\CloudSunnyApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CloudSunnyAccountController extends Controller
{
    private function resolvePrimaryAccount(): ?CloudSunnyAccount
    {
        return CloudSunnyAccount::query()
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    public function index()
    {
        $primary = $this->resolvePrimaryAccount();
        $hasEnvCredentials = filled(config('cloudsunny.api_username'))
            && filled(config('cloudsunny.api_app'))
            && filled(config('cloudsunny.api_secret'));

        $activeVpsCount = VpsInstance::whereNotIn('status', ['Lỗi', 'Đã xóa', 'Hết hạn'])->count();
        $activeProxyCount = ProxyInstance::whereNotIn('status', ['Lỗi', 'Đã xóa', 'Hết hạn', 'Đã hủy'])->count();

        return Inertia::render('Admin/Accounts/Index', [
            'account' => $primary ? [
                'id' => $primary->id,
                'label' => $primary->label,
                'api_username' => $primary->api_username,
                'api_app' => $primary->api_app,
                'has_secret' => filled($primary->getRawOriginal('api_secret')),
                'credit_vnd' => (int) $primary->credit_vnd,
                'total_vnd' => (int) $primary->total_vnd,
                'is_active' => (bool) $primary->is_active,
                'is_full' => (bool) $primary->is_full,
                'last_synced_at' => $primary->last_synced_at?->format('d/m/Y H:i'),
                'sync_error' => $primary->sync_error,
            ] : null,
            'stats' => [
                'active_vps' => $activeVpsCount,
                'active_proxies' => $activeProxyCount,
                'api_base' => (string) config('cloudsunny.api_base', 'https://api.cloudsunny.net/api'),
                'has_env_credentials' => $hasEnvCredentials,
                'extra_accounts_count' => max(0, CloudSunnyAccount::count() - ($primary ? 1 : 0)),
            ],
        ]);
    }

    public function savePrimary(Request $request, CloudSunnyApiService $api, CloudSunnyAccountSyncService $sync)
    {
        $primary = $this->resolvePrimaryAccount();

        $data = $request->validate([
            'api_username' => 'required|email|max:190',
            'api_app' => 'required|string|max:255',
            'api_secret' => ($primary ? 'nullable' : 'required') . '|string|min:16',
            'is_active' => 'sometimes|boolean',
        ], [
            'api_username.required' => 'Vui lòng nhập email tài khoản CloudSunny.',
            'api_username.email' => 'Email tài khoản CloudSunny không đúng định dạng.',
            'api_app.required' => 'Vui lòng nhập API App Key.',
            'api_secret.required' => 'Vui lòng nhập API Secret Key.',
            'api_secret.min' => 'API Secret Key cần tối thiểu 16 ký tự.',
        ]);

        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        if ($primary) {
            $primary->fill([
                'label' => 'Tài khoản CloudSunny Chính',
                'api_username' => trim($data['api_username']),
                'api_app' => trim($data['api_app']),
                'priority' => 0,
                'is_active' => $isActive,
                'is_full' => false,
            ]);

            if (!empty($data['api_secret'])) {
                $primary->api_secret = trim($data['api_secret']);
                $primary->access_token = null;
                $primary->refresh_token = null;
                $primary->token_expires_at = null;
            }

            $primary->save();
            $account = $primary;
        } else {
            $account = CloudSunnyAccount::create([
                'label' => 'Tài khoản CloudSunny Chính',
                'api_username' => trim($data['api_username']),
                'api_app' => trim($data['api_app']),
                'api_secret' => trim($data['api_secret']),
                'priority' => 0,
                'is_active' => $isActive,
                'is_full' => false,
            ]);
        }

        // Chỉ giữ duy nhất 1 tài khoản cố định đang hoạt động để tránh định tuyến nhầm sang dữ liệu mẫu cũ
        CloudSunnyAccount::where('id', '!=', $account->id)->update(['is_active' => false]);
        Cache::forget('lugiacloud.has_active_provider');

        try {
            $sync->sync($account, $api);
        } catch (\Throwable $e) {
            $account->update(['sync_error' => $e->getMessage()]);
            return back()->with('error', 'Đã lưu khóa API nhưng đồng bộ số dư CloudSunny thất bại: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã lưu khóa API và đồng bộ số dư gốc CloudSunny thành công.');
    }

    public function syncPrimary(CloudSunnyAccountRouter $router, CloudSunnyApiService $api, CloudSunnyAccountSyncService $sync)
    {
        $account = $this->resolvePrimaryAccount() ?: $router->firstActive();
        if (!$account) {
            return back()->with('error', 'Chưa cấu hình tài khoản API CloudSunny. Vui lòng nhập khóa API bên dưới hoặc điền vào file .env.');
        }

        try {
            $sync->sync($account, $api);
            return back()->with('success', 'Đã đồng bộ số dư ví gốc CloudSunny mới nhất.');
        } catch (\Throwable $e) {
            $account->update(['sync_error' => $e->getMessage()]);
            return back()->with('error', 'Đồng bộ số dư thất bại: ' . $e->getMessage());
        }
    }

    public function store(Request $request, CloudSunnyApiService $api, CloudSunnyAccountSyncService $sync)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'api_username' => 'required|email|max:190',
            'api_app' => 'required|string|max:255',
            'api_secret' => 'required|string|min:16',
            'priority' => 'nullable|integer|min:0|max:999',
        ]);

        $account = CloudSunnyAccount::create([
            'label' => $data['label'],
            'api_username' => $data['api_username'],
            'api_app' => $data['api_app'],
            'api_secret' => $data['api_secret'],
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => true,
        ]);

        Cache::forget('lugiacloud.has_active_provider');

        try {
            $sync->sync($account, $api);
        } catch (\Throwable $e) {
            $account->update(['sync_error' => $e->getMessage()]);
            return back()->with('error', 'Đã lưu tài khoản nhưng đồng bộ số dư thất bại: ' . $e->getMessage());
        }

        return back()->with('success', 'Đã kết nối và đồng bộ tài khoản đối tác thành công.');
    }

    public function edit(CloudSunnyAccount $account)
    {
        return redirect()->route('admin.accounts.index');
    }

    public function update(Request $request, CloudSunnyAccount $account)
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'api_username' => 'required|email|max:190',
            'api_app' => 'required|string|max:255',
            'api_secret' => 'nullable|string|min:16',
            'priority' => 'nullable|integer|min:0|max:999',
            'is_active' => 'required|boolean',
            'is_full' => 'required|boolean',
        ]);

        $account->fill([
            'label' => $data['label'],
            'api_username' => $data['api_username'],
            'api_app' => $data['api_app'],
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) $data['is_active'],
            'is_full' => (bool) $data['is_full'],
        ]);

        if (!empty($data['api_secret'])) {
            $account->api_secret = $data['api_secret'];
            $account->access_token = null;
            $account->refresh_token = null;
            $account->token_expires_at = null;
        }

        $account->save();
        Cache::forget('lugiacloud.has_active_provider');

        return back()->with('success', "Đã cập nhật cấu hình tài khoản {$account->label}.");
    }

    public function sync(CloudSunnyAccount $account, CloudSunnyApiService $api, CloudSunnyAccountSyncService $sync)
    {
        try {
            $sync->sync($account, $api);
            return back()->with('success', "Đã đồng bộ số dư gốc của {$account->label}.");
        } catch (\Throwable $e) {
            $account->update(['sync_error' => $e->getMessage()]);
            return back()->with('error', 'Đồng bộ thất bại: ' . $e->getMessage());
        }
    }

    public function syncAll(CloudSunnyApiService $api, CloudSunnyAccountSyncService $sync)
    {
        $ok = 0;
        $fail = 0;
        foreach (CloudSunnyAccount::all() as $account) {
            try {
                $sync->sync($account, $api);
                $ok++;
            } catch (\Throwable $e) {
                $account->update(['sync_error' => $e->getMessage()]);
                $fail++;
            }
        }

        return back()->with('success', "Đồng bộ hoàn tất: {$ok} thành công, {$fail} lỗi.");
    }

    public function toggle(Request $request, CloudSunnyAccount $account)
    {
        $field = $request->input('field');
        if (!in_array($field, ['is_active', 'is_full'], true)) {
            return back()->with('error', 'Trường không hợp lệ.');
        }

        $account->$field = !$account->$field;
        $account->save();
        Cache::forget('lugiacloud.has_active_provider');

        return back()->with('success', 'Đã cập nhật trạng thái tài khoản đối tác.');
    }

    public function destroy(CloudSunnyAccount $account)
    {
        if ($account->activeInstances()->exists()) {
            return back()->with('error', 'Không thể xóa tài khoản đang còn VPS khách hàng hoạt động.');
        }

        $accountId = $account->id;
        $account->delete();
        Cache::forget('lugiacloud.has_active_provider');

        if (preg_match('#/admin/accounts/' . $accountId . '(?:[/?#]|$)#', (string) url()->previous())) {
            return redirect()->route('admin.accounts.index')->with('success', 'Đã xóa tài khoản đối tác.');
        }

        return back()->with('success', 'Đã xóa tài khoản đối tác.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TopupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $activeVpsCount = $user->vpsInstances()->whereNotIn('status', ['Đã xóa', 'Hết hạn'])->count();
        $proxyCount = $user->proxyInstances()->count();
        $totalDeposited = (int) TopupRequest::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'approved'])
            ->selectRaw('COALESCE(SUM(COALESCE(approved_amount, amount)), 0) as s')
            ->value('s');

        return Inertia::render('Profile/Index', [
            'metrics' => [
                'active_vps' => $activeVpsCount,
                'total_proxies' => $proxyCount,
                'total_deposited' => $totalDeposited,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(0|\+84)[0-9]{8,11}$/'],
        ], [
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam (VD: 0912345678).',
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return back()->with('success', 'Đã lưu thông tin hồ sơ tài khoản.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Mật khẩu hiện tại không chính xác.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Đã thay đổi mật khẩu đăng nhập thành công.');
    }
}

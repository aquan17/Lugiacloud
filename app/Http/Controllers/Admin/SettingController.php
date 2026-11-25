<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $isVpsOutOfStock = Setting::where('key', 'vps_out_of_stock')->value('value') == '1';
        $requireEmailVerification = User::isEmailVerificationRequired();

        return Inertia::render('Admin/Settings/Index', [
            'isVpsOutOfStock' => $isVpsOutOfStock,
            'requireEmailVerification' => $requireEmailVerification,
            'depositConfig' => [
                'provider' => config('deposit.provider'),
                'bank_id' => config('deposit.bank_id'),
                'account_no' => config('deposit.account_no'),
                'account_name' => config('deposit.account_name'),
                'payos_configured' => !empty(config('deposit.payos.client_id')),
            ],
        ]);
    }

    public function toggleVpsStock(Request $request)
    {
        $isOutOfStock = $request->boolean('is_out_of_stock') ? '1' : '0';

        Setting::updateOrCreate(
            ['key' => 'vps_out_of_stock'],
            ['value' => $isOutOfStock, 'type' => 'boolean']
        );

        Cache::forget('setting_vps_out_of_stock');
        Cache::forget('lugiacloud.has_active_provider');

        $message = $isOutOfStock === '1'
            ? 'Đã bật chế độ Tạm ngưng nhận đơn khởi tạo VPS mới.'
            : 'Đã mở lại chức năng khởi tạo VPS tự động.';

        return back()->with('success', $message);
    }

    public function toggleEmailVerification(Request $request)
    {
        $require = $request->boolean('require_email_verification') ? '1' : '0';

        Setting::updateOrCreate(
            ['key' => User::SETTING_REQUIRE_EMAIL_VERIFICATION],
            ['value' => $require, 'type' => 'boolean']
        );

        Cache::forget(User::CACHE_REQUIRE_EMAIL_VERIFICATION);

        $message = $require === '1'
            ? 'Đã bật yêu cầu xác thực Email khi đăng ký tài khoản mới.'
            : 'Đã tạm tắt xác thực Email — khách đăng ký mới sẽ tự kích hoạt và vào thẳng Dashboard.';

        return back()->with('success', $message);
    }
}

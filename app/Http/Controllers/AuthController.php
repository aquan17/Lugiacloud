<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WelcomeOfferService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->storeIntendedRedirect($request);

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (!Auth::user()->hasVerifiedEmail()) {
                return redirect()->route('verification.notice')
                    ->with('info', 'Tài khoản của bạn chưa xác thực email. Vui lòng kiểm tra hộp thư hoặc bấm gửi lại liên kết xác nhận.');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'Thông tin đăng nhập không chính xác.'])->onlyInput('email');
    }

    public function showRegister(Request $request)
    {
        $this->storeIntendedRedirect($request);

        $ref = $request->query('ref') ?: Cookie::get('referral_code');
        if ($request->filled('ref')) {
            Cookie::queue('referral_code', $request->query('ref'), 43200);
        }

        return Inertia::render('Auth/Register', [
            'referralCode' => $ref,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(0|\+84)[0-9]{8,11}$/'],
            'password' => 'required|min:8|confirmed',
            'referral_code' => 'nullable|string|max:32',
        ], [
            'name.required' => 'Vui lòng nhập họ tên hoặc tên hiển thị.',
            'email.unique' => 'Email này đã được đăng ký trên hệ thống.',
            'phone.regex' => 'Số điện thoại không đúng định dạng Việt Nam.',
            'password.min' => 'Mật khẩu cần tối thiểu 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $referrerId = null;
        $refCode = $data['referral_code'] ?? Cookie::get('referral_code');
        if (!empty($refCode)) {
            $referrer = User::where('ref_code', strtoupper(trim((string) $refCode)))->first();
            if ($referrer) {
                $referrerId = $referrer->id;
            }
        }

        do {
            $myRefCode = strtoupper(Str::random(8));
        } while (User::where('ref_code', $myRefCode)->exists());

        $requireEmailVerification = User::isEmailVerificationRequired();

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'balance' => 0,
            'ref_code' => $myRefCode,
            'referred_by' => $referrerId,
            'email_verified_at' => $requireEmailVerification ? null : now(),
        ]);

        Auth::login($user);

        if (!$requireEmailVerification) {
            $welcomeOffer = app(WelcomeOfferService::class);
            $granted = $welcomeOffer->grantToNewUser($user);
            $msg = $granted
                ? 'Đăng ký thành công! Quà thành viên mới: Giảm ' . $welcomeOffer->getConfig()['percent'] . '% đơn đầu tiên đã được lưu sẵn trong Ví Voucher của bạn.'
                : 'Đăng ký thành công! Chào mừng bạn đến với LugiaCloud.';

            return redirect()->intended(route('dashboard'))->with('success', $msg);
        }

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send registration verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('verification.notice')
                ->with('info', 'Tạo tài khoản thành công! Hiện chưa gửi được email tự động, vui lòng bấm "Gửi lại email xác nhận" bên dưới.');
        }

        return redirect()->route('verification.notice')
            ->with('success', "Đăng ký thành công! Chúng tôi đã gửi liên kết xác thực tới {$user->email}. Vui lòng kiểm tra hộp thư đến (hoặc Spam) để kích hoạt tài khoản.");
    }

    protected function storeIntendedRedirect(Request $request): void
    {
        $redirect = trim((string) $request->query('redirect', ''));
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            session(['url.intended' => $redirect]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}

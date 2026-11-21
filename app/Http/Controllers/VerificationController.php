<?php

namespace App\Http\Controllers;

use App\Services\WelcomeOfferService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function show(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/VerifyEmail');
    }

    public function verify(EmailVerificationRequest $request, WelcomeOfferService $welcomeOffer)
    {
        $request->fulfill();

        $user = $request->user();
        $granted = $user ? $welcomeOffer->grantToNewUser($user) : null;
        $msg = $granted
            ? 'Xác thực email thành công! Quà thành viên mới: Giảm ' . $welcomeOffer->getConfig()['percent'] . '% đơn đầu tiên đã được lưu sẵn trong Ví Voucher của bạn.'
            : 'Xác thực email thành công. Chào mừng bạn đến với LugiaCloud.';

        return redirect()->route('dashboard')->with('success', $msg);
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('Failed to resend verification email', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Không thể gửi email xác nhận lúc này. Vui lòng thử lại sau ít phút.');
        }

        return back()->with('success', 'Đã gửi lại liên kết xác nhận vào hộp thư ' . $request->user()->email . '.');
    }
}

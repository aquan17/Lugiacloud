<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLog;
use App\Services\AffiliateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (empty($user->ref_code)) {
            $user->ref_code = strtoupper(Str::random(8));
            $user->save();
        }

        $totalCommission = (int) AffiliateLog::where('user_id', $user->id)->sum('commission');
        $referralCount = $user->referrals()->count();

        $logs = AffiliateLog::where('user_id', $user->id)
            ->with('buyer:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->through(fn (AffiliateLog $l) => [
                'id' => $l->id,
                'buyer_email' => $l->buyer ? $this->maskEmail($l->buyer->email) : 'Thành viên',
                'transaction_type' => $l->transaction_type,
                'amount' => (int) $l->amount,
                'commission' => (int) $l->commission,
                'created_at' => $l->created_at?->format('d/m/Y H:i'),
            ]);

        $referrals = $user->referrals()
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $this->maskEmail($r->email),
                'joined_at' => $r->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Affiliate/Index', [
            'referralUrl' => url('/register?ref=' . $user->ref_code),
            'refCode' => $user->ref_code,
            'commissionRatePercent' => AffiliateService::COMMISSION_RATE * 100,
            'totalCommission' => $totalCommission,
            'referralCount' => $referralCount,
            'logs' => $logs,
            'referrals' => $referrals,
        ]);
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $local = $parts[0];
        $masked = substr($local, 0, min(3, strlen($local))) . '***';

        return $masked . '@' . $parts[1];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLog;
use App\Models\User;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function index()
    {
        $referrals = User::whereNotNull('referred_by')
            ->with('referrer:id,name,email,ref_code')
            ->addSelect([
                'total_commission' => AffiliateLog::selectRaw('COALESCE(SUM(commission), 0)')
                    ->whereColumn('buyer_id', 'users.id')
                    ->whereColumn('user_id', 'users.referred_by'),
            ])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'buyer_name' => $u->name,
                'buyer_email' => $u->email,
                'referrer_id' => $u->referrer?->id,
                'referrer_name' => $u->referrer?->name,
                'referrer_email' => $u->referrer?->email,
                'referrer_code' => $u->referrer?->ref_code,
                'total_commission' => (int) ($u->total_commission ?? 0),
                'joined_at' => $u->created_at?->format('d/m/Y H:i'),
            ]);

        $totalPaidOut = (int) AffiliateLog::sum('commission');

        return Inertia::render('Admin/Affiliates/Index', [
            'referrals' => $referrals,
            'totalPaidOut' => $totalPaidOut,
        ]);
    }
}

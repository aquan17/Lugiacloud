<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $validTypes = ['buy', 'buy_vps', 'buy_proxy', 'buy_server', 'renew', 'upgrade', 'change_ip'];
        $year = (int) $request->input('year', date('Y'));
        $search = trim((string) $request->input('search', ''));

        // Chỉ tính các đơn hoàn tất (loại trừ đơn lỗi đã hoàn tiền 'refunded')
        $completedBase = Transaction::whereIn('type', $validTypes)->where('status', '!=', 'refunded');

        $startOfMonth = now()->startOfMonth()->toDateTimeString();
        $endOfMonth = now()->endOfMonth()->toDateTimeString();

        // Gộp 4 truy vấn tổng/tháng thành 1 truy vấn SQL Aggregate duy nhất
        $txnAgg = (clone $completedBase)
            ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(provider_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN ABS(amount) ELSE 0 END), 0) as month_revenue', [$startOfMonth, $endOfMonth])
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN provider_cost ELSE 0 END), 0) as month_cost', [$startOfMonth, $endOfMonth])
            ->first();

        $affAgg = AffiliateLog::query()
            ->selectRaw('COALESCE(SUM(commission), 0) as total_affiliate')
            ->selectRaw('COALESCE(SUM(CASE WHEN created_at BETWEEN ? AND ? THEN commission ELSE 0 END), 0) as month_affiliate', [$startOfMonth, $endOfMonth])
            ->first();

        $totalRevenue = (int) ($txnAgg->total_revenue ?? 0);
        $totalCost = (int) ($txnAgg->total_cost ?? 0);
        $totalAffiliate = (int) ($affAgg->total_affiliate ?? 0);
        $totalProfit = $totalRevenue - $totalCost - $totalAffiliate;

        $monthRevenue = (int) ($txnAgg->month_revenue ?? 0);
        $monthCost = (int) ($txnAgg->month_cost ?? 0);
        $monthAffiliate = (int) ($affAgg->month_affiliate ?? 0);
        $monthProfit = $monthRevenue - $monthCost - $monthAffiliate;

        $isSqlite = \Illuminate\Support\Facades\DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', created_at) AS INTEGER)" : 'MONTH(created_at)';
        $yearExpr = $isSqlite ? "CAST(strftime('%Y', created_at) AS INTEGER)" : 'YEAR(created_at)';

        $monthlyRows = (clone $completedBase)
            ->whereYear('created_at', $year)
            ->selectRaw("{$monthExpr} as month, COALESCE(SUM(ABS(amount)), 0) as revenue, COALESCE(SUM(provider_cost), 0) as cost")
            ->groupBy('month')
            ->get();

        $monthlyChart = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyChart[$m] = ['month' => $m, 'label' => 'T' . $m, 'revenue' => 0, 'cost' => 0, 'profit' => 0];
        }
        foreach ($monthlyRows as $r) {
            $m = (int) $r->month;
            $rev = (int) $r->revenue;
            $cost = (int) $r->cost;
            $monthlyChart[$m] = [
                'month' => $m,
                'label' => 'T' . $m,
                'revenue' => $rev,
                'cost' => $cost,
                'profit' => $rev - $cost,
            ];
        }

        $query = Transaction::with(['user:id,name,email'])
            ->whereIn('type', $validTypes)
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"))
                    ->orWhereHas('vps', fn ($vq) => $vq->where('label', 'like', "%{$search}%")->orWhere('public_ip', 'like', "%{$search}%"))
                    ->orWhereHas('proxy', fn ($pq) => $pq->where('ip', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(20)->withQueryString()->through(function (Transaction $t) {
            $rev = abs((int) $t->amount);
            $cost = (int) $t->provider_cost;
            return [
                'id' => $t->id,
                'code' => $t->code ?: ('#' . $t->id),
                'user_id' => $t->user?->id,
                'user_name' => $t->user?->name,
                'user_email' => $t->user?->email ?? 'Đã xóa',
                'type' => $t->type,
                'status' => $t->status ?: 'completed',
                'description' => $t->description,
                'coupon_code' => $t->coupon_code,
                'discount_amount' => (int) $t->discount_amount,
                'amount' => $rev,
                'provider_cost' => $cost,
                'profit' => $t->status === 'refunded' ? 0 : ($rev - $cost),
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
            ];
        });

        $availableYears = Transaction::selectRaw("{$yearExpr} as year")
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->filter()
            ->values()
            ->all();

        if (!in_array((int) date('Y'), $availableYears, true)) {
            array_unshift($availableYears, (int) date('Y'));
        }

        return Inertia::render('Admin/Revenue/Index', [
            'metrics' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'total_affiliate' => $totalAffiliate,
                'total_profit' => $totalProfit,
                'month_revenue' => $monthRevenue,
                'month_cost' => $monthCost,
                'month_affiliate' => $monthAffiliate,
                'month_profit' => $monthProfit,
            ],
            'year' => $year,
            'availableYears' => $availableYears,
            'monthlyChart' => array_values($monthlyChart),
            'transactions' => $transactions,
            'filters' => ['search' => $search],
        ]);
    }

    public function yearly(Request $request)
    {
        return $this->index($request);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return back()->with('success', 'Đã xóa bản ghi giao dịch.');
    }
}

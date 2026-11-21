<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index(Request $request)
    {
        $tab = (string) $request->input('tab', 'all');
        if (!in_array($tab, ['all', 'spending', 'refund', 'admin', 'bonus'], true)) {
            $tab = 'all';
        }
        $search = trim((string) $request->input('search', ''));

        $spendingTypes = ['buy', 'buy_vps', 'buy_proxy', 'buy_server', 'renew', 'upgrade', 'change_ip'];
        $adminTypes = ['admin_add', 'admin_deduct'];
        $bonusTypes = ['topup_bonus', 'affiliate_bonus'];
        $creditTypes = ['refund', 'affiliate_bonus', 'topup_bonus', 'admin_add'];

        // 1 truy vấn SQL Aggregate duy nhất cho toàn bộ 5 tab + 4 thẻ KPI
        $agg = Transaction::query()
            ->selectRaw('COUNT(*) as all_cnt')
            ->selectRaw("SUM(CASE WHEN type IN ('buy','buy_vps','buy_proxy','buy_server','renew','upgrade','change_ip') THEN 1 ELSE 0 END) as spending_cnt")
            ->selectRaw("SUM(CASE WHEN type = 'refund' OR status = 'refunded' THEN 1 ELSE 0 END) as refund_cnt")
            ->selectRaw("SUM(CASE WHEN type IN ('admin_add','admin_deduct') THEN 1 ELSE 0 END) as admin_cnt")
            ->selectRaw("SUM(CASE WHEN type IN ('topup_bonus','affiliate_bonus') THEN 1 ELSE 0 END) as bonus_cnt")
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('buy','buy_vps','buy_proxy','buy_server','renew','upgrade','change_ip') AND status != 'refunded' THEN ABS(amount) ELSE 0 END), 0) as total_spending")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN ABS(amount) ELSE 0 END), 0) as total_refund")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'admin_add' THEN ABS(amount) ELSE 0 END), 0) as total_admin_add")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'admin_deduct' THEN ABS(amount) ELSE 0 END), 0) as total_admin_deduct")
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('topup_bonus','affiliate_bonus') THEN ABS(amount) ELSE 0 END), 0) as total_bonus")
            ->first();

        $query = Transaction::with([
            'user:id,name,email',
            'vps:id,label,public_ip',
            'proxy:id,ip,port',
            'dedicatedServer:id,label,public_ip,plan_name',
        ])->orderByDesc('created_at')->orderByDesc('id');

        if ($tab === 'spending') {
            $query->whereIn('type', $spendingTypes);
        } elseif ($tab === 'refund') {
            $query->where(fn ($q) => $q->where('type', 'refund')->orWhere('status', 'refunded'));
        } elseif ($tab === 'admin') {
            $query->whereIn('type', $adminTypes);
        } elseif ($tab === 'bonus') {
            $query->whereIn('type', $bonusTypes);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('coupon_code', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                    ->orWhereHas('vps', fn ($vq) => $vq->where('label', 'like', "%{$search}%")->orWhere('public_ip', 'like', "%{$search}%"))
                    ->orWhereHas('proxy', fn ($pq) => $pq->where('ip', 'like', "%{$search}%"));
            });
        }

        $entries = $query->paginate(25)->withQueryString()->through(function (Transaction $t) use ($creditTypes) {
            $rawAmount = abs((int) $t->amount);
            $isCredit = in_array(strtolower((string) $t->type), $creditTypes, true);

            return [
                'id' => $t->id,
                'code' => $t->code ?: ('#' . $t->id),
                'user_id' => $t->user?->id,
                'user_name' => $t->user?->name,
                'user_email' => $t->user?->email ?? 'Đã xóa',
                'type' => $t->type,
                'type_label' => $this->typeLabel($t),
                'is_credit' => $isCredit,
                'status' => $t->status ?: 'completed',
                'description' => $t->description ?: $this->typeLabel($t),
                'coupon_code' => $t->coupon_code,
                'discount_amount' => (int) $t->discount_amount,
                'amount' => $isCredit ? $rawAmount : -$rawAmount,
                'balance_before' => $t->balance_before !== null ? (int) $t->balance_before : null,
                'balance_after' => $t->balance_after !== null ? (int) $t->balance_after : null,
                'vps_id' => $t->vps?->id,
                'vps_label' => $t->vps ? ($t->vps->public_ip ?: $t->vps->label) : null,
                'proxy_id' => $t->proxy?->id,
                'proxy_label' => $t->proxy ? ($t->proxy->ip ? "{$t->proxy->ip}:{$t->proxy->port}" : "Proxy #{$t->proxy->id}") : null,
                'server_id' => $t->dedicatedServer?->id,
                'server_label' => $t->dedicatedServer ? ($t->dedicatedServer->public_ip ?: $t->dedicatedServer->label) : null,
                'created_at' => $t->created_at?->format('d/m/Y H:i'),
            ];
        });

        return Inertia::render('Admin/Ledger/Index', [
            'entries' => $entries,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
            ],
            'counts' => [
                'all' => (int) ($agg->all_cnt ?? 0),
                'spending' => (int) ($agg->spending_cnt ?? 0),
                'refund' => (int) ($agg->refund_cnt ?? 0),
                'admin' => (int) ($agg->admin_cnt ?? 0),
                'bonus' => (int) ($agg->bonus_cnt ?? 0),
            ],
            'summary' => [
                'total_spending' => (int) ($agg->total_spending ?? 0),
                'total_refund' => (int) ($agg->total_refund ?? 0),
                'total_admin_add' => (int) ($agg->total_admin_add ?? 0),
                'total_admin_deduct' => (int) ($agg->total_admin_deduct ?? 0),
                'total_bonus' => (int) ($agg->total_bonus ?? 0),
            ],
        ]);
    }

    private function typeLabel(Transaction $t): string
    {
        return match (strtolower((string) $t->type)) {
            'buy', 'buy_vps' => $t->dedicated_server_id ? 'Mua Server vật lý' : ($t->proxy_instance_id ? 'Mua Proxy' : 'Mua VPS'),
            'buy_proxy' => 'Mua Proxy',
            'buy_server' => 'Mua Server vật lý',
            'renew' => $t->dedicated_server_id ? 'Gia hạn Server vật lý' : ($t->proxy_instance_id ? 'Gia hạn Proxy' : 'Gia hạn VPS'),
            'upgrade' => 'Nâng cấp VPS',
            'change_ip' => 'Đổi IP VPS',
            'refund' => 'Hoàn tiền',
            'admin_add' => 'Admin cộng ví',
            'admin_deduct' => 'Admin trừ ví',
            'topup_bonus' => 'Thưởng nạp tiền',
            'affiliate_bonus' => 'Hoa hồng F1',
            default => strtoupper((string) $t->type),
        };
    }
}

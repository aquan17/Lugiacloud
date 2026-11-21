<?php

namespace App\Http\Controllers;

use App\Models\TopupRequest;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $txInvoices = Transaction::where('user_id', $user->id)
            ->whereNotIn('type', ['refund'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Transaction $t) {
                $code = $t->code ?: ('#' . $t->id);
                return [
                    'id' => 'txn_' . $t->id,
                    'code' => $code,
                    'type' => 'service',
                    'title' => $this->transactionTitle($t),
                    'description' => $t->description,
                    'amount' => abs((int) $t->amount),
                    'discount_amount' => (int) $t->discount_amount,
                    'payment_method' => 'Ví điện tử LugiaCloud',
                    'status' => $t->status === 'refunded' ? 'Đã hoàn tiền' : 'Đã thanh toán',
                    'created_at' => $t->created_at?->format('d/m/Y H:i'),
                    'timestamp' => $t->created_at?->timestamp ?? 0,
                    'detail_url' => route('invoices.show', $code),
                ];
            })
            ->toBase();

        $topupInvoices = TopupRequest::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'approved'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (TopupRequest $r) {
                $amount = (int) ($r->approved_amount ?? $r->amount);
                return [
                    'id' => 'topup_' . $r->id,
                    'code' => $r->code,
                    'type' => 'topup',
                    'title' => 'Nạp số dư tài khoản',
                    'description' => 'Cổng thanh toán ' . $this->providerLabel($r->provider),
                    'amount' => $amount,
                    'discount_amount' => 0,
                    'payment_method' => $this->providerLabel($r->provider),
                    'status' => 'Đã thanh toán',
                    'created_at' => ($r->paid_at ?? $r->created_at)?->format('d/m/Y H:i'),
                    'timestamp' => ($r->paid_at ?? $r->created_at)?->timestamp ?? 0,
                    'detail_url' => route('invoices.show', $r->code),
                ];
            })
            ->toBase();

        $invoices = $txInvoices->merge($topupInvoices)->sortByDesc('timestamp')->values();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'stats' => [
                'total_count' => $invoices->count(),
                'total_service_amount' => (int) $txInvoices->where('status', 'Đã thanh toán')->sum('amount'),
                'total_topup_amount' => (int) $topupInvoices->sum('amount'),
            ],
        ]);
    }

    public function show(string $code)
    {
        $user = Auth::user();

        $txn = Transaction::where('user_id', $user->id)
            ->where(function ($q) use ($code) {
                $q->where('code', $code);
                if (str_starts_with($code, '#')) {
                    $q->orWhere('id', (int) substr($code, 1));
                }
            })
            ->with(['vps', 'proxy', 'dedicatedServer'])
            ->first();

        if ($txn) {
            $invCode = $txn->code ?: ('#' . $txn->id);
            $items = [];

            if ($txn->vps) {
                $vps = $txn->vps;
                $items[] = [
                    'name' => "Cloud VPS: {$vps->label}",
                    'description' => "{$vps->cpu} vCPU · {$vps->ram}GB RAM · {$vps->disk}GB SSD · Khu vực " . strtoupper($vps->region ?: 'VN') . ($vps->public_ip ? " · IP {$vps->public_ip}" : ''),
                    'cycle' => $vps->billing_cycle_label,
                    'quantity' => 1,
                    'price' => abs((int) $txn->amount) + (int) $txn->discount_amount,
                    'discount' => (int) $txn->discount_amount,
                    'total' => abs((int) $txn->amount),
                ];
            } elseif ($txn->proxy) {
                $prx = $txn->proxy;
                $items[] = [
                    'name' => 'Proxy IPv4 Việt Nam (' . ($prx->type_proxy ?: 'HTTP') . ')',
                    'description' => $prx->ip ? "Endpoint: {$prx->ip}:{$prx->port}" : 'Cấp phát tự động',
                    'cycle' => $prx->billing_cycle_label,
                    'quantity' => 1,
                    'price' => abs((int) $txn->amount) + (int) $txn->discount_amount,
                    'discount' => (int) $txn->discount_amount,
                    'total' => abs((int) $txn->amount),
                ];
            } elseif ($txn->dedicatedServer) {
                $srv = $txn->dedicatedServer;
                $items[] = [
                    'name' => "Máy chủ vật lý: {$srv->label}",
                    'description' => ($srv->plan_name ?: 'Dedicated Server') . ($srv->public_ip ? " · IP {$srv->public_ip}" : ' · Đang lắp đặt & bàn giao'),
                    'cycle' => $srv->billing_cycle_label,
                    'quantity' => 1,
                    'price' => abs((int) $txn->amount) + (int) $txn->discount_amount,
                    'discount' => (int) $txn->discount_amount,
                    'total' => abs((int) $txn->amount),
                ];
            } else {
                $items[] = [
                    'name' => $this->transactionTitle($txn),
                    'description' => $txn->description ?: 'Giao dịch trên hệ thống LugiaCloud',
                    'cycle' => '1 lần',
                    'quantity' => 1,
                    'price' => abs((int) $txn->amount) + (int) $txn->discount_amount,
                    'discount' => (int) $txn->discount_amount,
                    'total' => abs((int) $txn->amount),
                ];
            }

            return Inertia::render('Invoices/Show', [
                'invoice' => [
                    'type' => 'service',
                    'number' => $invCode,
                    'created_at' => $txn->created_at?->format('d/m/Y H:i'),
                    'payment_method' => 'Số dư ví LugiaCloud',
                    'status' => $txn->status === 'refunded' ? 'Đã hoàn tiền' : 'Đã thanh toán',
                    'title' => $this->transactionTitle($txn),
                    'coupon_code' => $txn->coupon_code,
                    'subtotal' => abs((int) $txn->amount) + (int) $txn->discount_amount,
                    'discount_amount' => (int) $txn->discount_amount,
                    'total_amount' => abs((int) $txn->amount),
                    'items' => $items,
                ],
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ]);
        }

        $topup = TopupRequest::where('user_id', $user->id)->where('code', $code)->first();
        if ($topup) {
            $amount = (int) ($topup->approved_amount ?? $topup->amount);
            return Inertia::render('Invoices/Show', [
                'invoice' => [
                    'type' => 'topup',
                    'number' => $topup->code,
                    'created_at' => ($topup->paid_at ?? $topup->created_at)?->format('d/m/Y H:i'),
                    'payment_method' => $this->providerLabel($topup->provider),
                    'status' => in_array($topup->status, ['paid', 'approved'], true) ? 'Đã thanh toán' : 'Chờ xử lý',
                    'title' => 'Biên nhận nạp số dư tài khoản',
                    'coupon_code' => null,
                    'subtotal' => $amount,
                    'discount_amount' => 0,
                    'total_amount' => $amount,
                    'items' => [[
                        'name' => 'Nạp số dư ví LugiaCloud',
                        'description' => "Mã giao dịch: {$topup->code} · Cổng {$this->providerLabel($topup->provider)}" . ($topup->bonus_amount > 0 ? ' (Tặng thêm ' . number_format($topup->bonus_amount, 0, ',', '.') . 'đ sự kiện)' : ''),
                        'cycle' => 'Trực tiếp',
                        'quantity' => 1,
                        'price' => $amount,
                        'discount' => 0,
                        'total' => $amount,
                    ]],
                ],
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ]);
        }

        abort(404);
    }

    private function providerLabel(?string $provider): string
    {
        return [
            'payos' => 'PayOS (VietQR)',
            'manual' => 'Chuyển khoản ngân hàng',
        ][strtolower((string) $provider)] ?? ucfirst((string) ($provider ?: 'VietQR'));
    }

    private function transactionTitle(Transaction $t): string
    {
        return match (strtolower((string) $t->type)) {
            'renew' => $t->proxy_instance_id ? 'Gia hạn Proxy' : 'Gia hạn VPS',
            'upgrade' => 'Nâng cấp phần cứng VPS',
            'topup_bonus' => 'Thưởng sự kiện nạp tiền',
            'affiliate_bonus' => 'Hoa hồng giới thiệu',
            default => $t->proxy_instance_id ? 'Đăng ký dịch vụ Proxy' : 'Đăng ký dịch vụ Cloud VPS',
        };
    }
}

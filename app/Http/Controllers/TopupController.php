<?php

namespace App\Http\Controllers;

use App\Models\TopupRequest;
use App\Models\User;
use App\Services\PayosService;
use App\Services\PromotionEngine;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TopupController extends Controller
{
    public function index(PromotionEngine $promotions)
    {
        $requests = TopupRequest::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(12)
            ->through(fn (TopupRequest $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'amount' => (int) $r->amount,
                'approved_amount' => $r->approved_amount ? (int) $r->approved_amount : null,
                'bonus_amount' => (int) $r->bonus_amount,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'provider' => $r->provider,
                'created_at' => $r->created_at?->format('d/m/Y H:i'),
                'paid_at' => $r->paid_at?->format('d/m/Y H:i'),
            ]);

        $bonusInfo = $promotions->calculateTopupBonus(100000);

        return Inertia::render('Topup/Index', [
            'requests' => $requests,
            'bonusCampaign' => $bonusInfo['bonus_percent'] > 0 ? $bonusInfo : null,
            'bankInfo' => [
                'bank_id' => config('deposit.bank_id'),
                'account_no' => config('deposit.account_no'),
                'account_name' => config('deposit.account_name'),
                'provider' => config('deposit.provider', 'payos'),
            ],
        ]);
    }

    public function store(Request $request, PayosService $payos)
    {
        $request->merge([
            'amount' => (int) preg_replace('/\D/', '', (string) $request->input('amount')),
        ]);

        $request->validate([
            'amount' => 'required|integer|min:10000|max:50000000',
        ], [
            'amount.min' => 'Số tiền nạp tối thiểu là 10.000đ.',
            'amount.max' => 'Số tiền nạp tối đa mỗi giao dịch là 50.000.000đ.',
        ]);

        $order = TopupRequest::create([
            'user_id' => Auth::id(),
            'code' => $this->makeCode(),
            'amount' => (int) $request->amount,
            'status' => 'pending',
            'provider' => config('deposit.provider', 'payos'),
        ]);

        $description = 'LUGIA' . $order->id;
        $order->update(['code' => $description]);

        if (config('deposit.provider') === 'payos' && $payos->isConfigured()) {
            try {
                $payosOrderCode = $this->makePayosOrderCode($order);
                $order->update(['provider_order_code' => $payosOrderCode]);

                $payment = $payos->createPaymentLink([
                    'orderCode' => $payosOrderCode,
                    'amount' => $order->amount,
                    'description' => $description,
                    'cancelUrl' => route('topup.show', $order->id),
                    'returnUrl' => route('topup.show', $order->id),
                    'buyerName' => Auth::user()->name,
                    'buyerEmail' => Auth::user()->email,
                    'expiredAt' => now()->addMinutes(30)->timestamp,
                ]);

                $order->update([
                    'transaction_ref' => (string) data_get($payment, 'data.paymentLinkId'),
                    'raw_payload' => $payment,
                ]);
            } catch (\Throwable $e) {
                Log::warning('PayOS link fallback to direct VietQR', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('topup.show', $order->id);
    }

    public function show($id, PayosService $payos, PromotionEngine $promotions)
    {
        $order = TopupRequest::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->status === 'pending' && $order->provider === 'payos' && $payos->isConfigured()) {
            $this->syncPayosOrder($order, $payos);
            $order->refresh();
        }

        $payosQr = data_get($order->raw_payload, 'data.qrCode');
        $checkoutUrl = data_get($order->raw_payload, 'data.checkoutUrl');
        $expectedBonus = $promotions->calculateTopupBonus((int) $order->amount);

        return Inertia::render('Topup/Show', [
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'amount' => (int) $order->amount,
                'bonus_amount' => $order->isApproved() ? (int) $order->bonus_amount : (int) $expectedBonus['bonus_amount'],
                'bonus_percent' => (float) $expectedBonus['bonus_percent'],
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'vietqr_url' => $order->viet_qr_url,
                'payos_qr' => $payosQr,
                'checkout_url' => $checkoutUrl,
                'bank_id' => data_get($order->raw_payload, 'data.bin') ?: config('deposit.bank_id'),
                'account_no' => data_get($order->raw_payload, 'data.accountNumber') ?: config('deposit.account_no'),
                'account_name' => data_get($order->raw_payload, 'data.accountName') ?: config('deposit.account_name'),
                'created_at' => $order->created_at?->format('d/m/Y H:i'),
                'paid_at' => $order->paid_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function status($id, PayosService $payos)
    {
        $order = TopupRequest::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($order->status === 'pending' && $order->provider === 'payos' && $payos->isConfigured()) {
            $this->syncPayosOrder($order, $payos);
            $order->refresh();
        }

        $freshUser = User::query()->find(Auth::id());
        $balance = (int) ($freshUser->balance ?? 0);

        return response()->json([
            'status' => $order->status,
            'paid' => in_array($order->status, ['paid', 'approved'], true),
            'bonus_amount' => (int) $order->bonus_amount,
            'paid_at' => $order->paid_at ? $order->paid_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : null,
            'balance_raw' => $balance,
            'balance' => number_format($balance, 0, ',', '.') . 'đ',
        ]);
    }

    public function webhook(Request $request, PayosService $payos)
    {
        $payload = $request->all();
        $verified = false;

        if (($payload['signature'] ?? null) && isset($payload['data']) && $payos->isConfigured()) {
            if (!$payos->verifyWebhook((array) $payload['data'], (string) $payload['signature'])) {
                Log::warning('Topup webhook rejected: invalid PayOS signature', ['ip' => $request->ip()]);
                return response()->json(['message' => 'Invalid signature'], 401);
            }
            $verified = true;
        } elseif (config('deposit.webhook_secret')) {
            $secret = (string) ($request->header('X-Webhook-Secret') ?? $request->input('secret') ?? '');
            if ($secret === '' || !hash_equals((string) config('deposit.webhook_secret'), $secret)) {
                Log::warning('Topup webhook rejected: invalid webhook secret', ['ip' => $request->ip()]);
                return response()->json(['message' => 'Invalid secret'], 401);
            }
            $verified = true;
        }

        // Fail-closed: từ chối mọi request không đi kèm chữ ký PayOS hoặc webhook secret hợp lệ
        if (!$verified) {
            Log::warning('Topup webhook rejected: missing authentication', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Unauthorized webhook'], 401);
        }

        $content = $this->extractContent($payload);
        $amount = $this->extractAmount($payload);
        $reference = $this->extractReference($payload);
        $orderCode = data_get($payload, 'data.orderCode');

        if ((!$content && !$orderCode) || !$amount) {
            return response()->json(['message' => 'Ignored'], 202);
        }

        $order = TopupRequest::where('status', 'pending')
            ->where('amount', (int) $amount)
            ->where(function ($query) use ($content, $orderCode) {
                if ($orderCode) {
                    $query->orWhere('provider_order_code', (int) $orderCode)
                        ->orWhere('id', (int) $orderCode);
                }
                if ($content) {
                    $query->orWhere('code', trim($content))
                        ->orWhereRaw('? LIKE CONCAT("%", code, "%")', [$content]);
                }
            })
            ->first();

        if (!$order) {
            return response()->json([
                'code' => '00',
                'desc' => 'success',
                'success' => true,
                'message' => 'No matching order',
            ], 200);
        }

        $this->markOrderPaid(
            $order,
            data_get($payload, 'data.paymentLinkId') ? 'payos' : request()->input('provider', 'webhook'),
            $reference,
            $payload
        );

        return response()->json([
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'message' => 'OK',
        ]);
    }

    private function syncPayosOrder(TopupRequest $order, PayosService $payos): void
    {
        try {
            $payment = $payos->getPaymentLinkInformation($order->provider_order_code ?: $order->id);
            $status = strtoupper((string) data_get($payment, 'data.status'));

            if ($status !== 'PAID') {
                return;
            }

            $amount = (int) data_get($payment, 'data.amount');
            if ($amount !== (int) $order->amount) {
                return;
            }

            $this->markOrderPaid($order, 'payos', data_get($payment, 'data.paymentLinkId'), $payment);
        } catch (\Throwable $e) {
            Log::warning('PayOS sync failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function markOrderPaid(TopupRequest $order, string $provider, ?string $reference, array $payload): void
    {
        DB::transaction(function () use ($order, $provider, $reference, $payload) {
            $lockedOrder = TopupRequest::whereKey($order->id)->lockForUpdate()->first();
            if (!$lockedOrder || $lockedOrder->status !== 'pending') {
                return;
            }

            $lockedUser = User::whereKey($lockedOrder->user_id)->lockForUpdate()->firstOrFail();
            $lockedUser->balance = (int) $lockedUser->balance + (int) $lockedOrder->amount;
            $lockedUser->save();

            $bonusInfo = app(PromotionEngine::class)->calculateTopupBonus((int) $lockedOrder->amount);
            $bonusAmount = (int) ($bonusInfo['bonus_amount'] ?? 0);
            $campaignId = $bonusInfo['campaign']['id'] ?? null;

            $lockedOrder->update([
                'status' => 'paid',
                'provider' => $provider,
                'transaction_ref' => $reference,
                'raw_payload' => $payload,
                'bonus_amount' => $bonusAmount,
                'campaign_id' => $campaignId,
                'paid_at' => now(),
            ]);

            if ($bonusAmount > 0) {
                app(WalletService::class)->credit($lockedUser, $bonusAmount, [
                    'idempotency_key' => "topup_bonus_{$lockedOrder->id}",
                    'campaign_id' => $campaignId,
                    'type' => 'topup_bonus',
                    'description' => "Thưởng sự kiện +{$bonusInfo['bonus_percent']}% cho đơn nạp {$lockedOrder->code}",
                ]);
            }
        });
    }

    private function makeCode(): string
    {
        do {
            $code = 'NAP' . now()->format('ymd') . strtoupper(Str::random(6));
        } while (TopupRequest::where('code', $code)->exists());

        return $code;
    }

    private function makePayosOrderCode(TopupRequest $order): int
    {
        return ((int) now()->timestamp * 1000) + ((int) $order->id % 1000);
    }

    private function extractContent(array $payload): ?string
    {
        return $payload['content']
            ?? $payload['description']
            ?? $payload['addInfo']
            ?? data_get($payload, 'data.description')
            ?? data_get($payload, 'data.content')
            ?? data_get($payload, 'transaction.content');
    }

    private function extractAmount(array $payload): ?int
    {
        $amount = $payload['amount']
            ?? data_get($payload, 'data.amount')
            ?? data_get($payload, 'data.transferAmount')
            ?? data_get($payload, 'transaction.amount')
            ?? data_get($payload, 'transferAmount');

        return $amount !== null ? (int) $amount : null;
    }

    private function extractReference(array $payload): ?string
    {
        return $payload['reference']
            ?? $payload['transaction_ref']
            ?? data_get($payload, 'data.reference')
            ?? data_get($payload, 'transaction.reference')
            ?? data_get($payload, 'data.transactionId')
            ?? data_get($payload, 'data.paymentLinkId');
    }
}

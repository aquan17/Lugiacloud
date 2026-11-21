<?php

namespace App\Http\Controllers;

use App\Models\DedicatedServer;
use App\Services\AffiliateService;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use App\Services\CloudSunnyDedicatedPricingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use RuntimeException;

class DedicatedServerStoreController extends Controller
{
    public function index(CloudSunnyDedicatedPricingService $pricing)
    {
        $user = Auth::user();
        $servers = $pricing->getCatalogServers($user);

        return Inertia::render('Servers/StoreIndex', [
            'servers' => $servers,
        ]);
    }

    public function create(string $slug, CloudSunnyDedicatedPricingService $pricing)
    {
        $user = Auth::user();
        $server = $pricing->findBySlug($slug, $user);

        if (!$server) {
            return redirect()->route('servers.store.index')->with('error', 'Gói Máy chủ vật lý này hiện không khả dụng.');
        }

        return Inertia::render('Servers/Create', [
            'server' => $server,
            'balance' => (int) ($user?->balance ?? 0),
        ]);
    }

    public function store(
        Request $request,
        CloudSunnyDedicatedPricingService $pricing,
        CloudSunnyAccountRouter $router,
        CloudSunnyApiService $api,
        WalletService $wallet
    ) {
        $user = Auth::user();
        $validated = $request->validate([
            'plan_slug' => 'required|string|max:64',
            'billing_cycle' => 'required|in:monthly,twomonthly,quarterly,semi_annually,annually,biennially,triennially',
            'quantity' => 'required|integer|min:1|max:5',
            'label' => 'nullable|string|max:120',
            'os_note' => 'nullable|string|max:120',
        ], [
            'quantity.max' => 'Mỗi đơn đặt tối đa 5 máy chủ vật lý. Cần số lượng lớn vui lòng liên hệ hỗ trợ.',
        ]);

        $product = $pricing->findBySlug($validated['plan_slug'], $user);
        if (!$product) {
            return back()->with('error', 'Gói Máy chủ vật lý hiện không khả dụng.');
        }

        $quote = $pricing->computeCycleQuote(
            $product,
            $validated['billing_cycle'],
            (int) $validated['quantity'],
            false
        );

        if (!$quote) {
            return back()->with('error', 'Chu kỳ thanh toán này không khả dụng cho cấu hình máy chủ đã chọn.');
        }

        $totalPrice = (int) $quote['total_price'];
        if ($user->balance < $totalPrice) {
            return back()->with('error', 'Số dư ví không đủ (' . number_format($totalPrice, 0, ',', '.') . 'đ). Vui lòng nạp thêm số dư.');
        }

        $account = $router->firstActive();
        if (!$account) {
            return back()->with('error', 'Hệ thống hạ tầng tạm bảo trì. Vui lòng thử lại sau ít phút.');
        }

        $lock = Cache::lock("dedicated_order_user_{$user->id}", 30);
        if (!$lock->get()) {
            return back()->with('error', 'Bạn đang có một đơn đặt máy chủ đang xử lý. Vui lòng chờ hoàn tất.');
        }

        $apiClient = $api->forAccount($account);

        // Kiểm tra giá & tồn kho máy vật lý trước khi trừ tiền khách (read-only, không trừ tiền)
        try {
            $priceCheck = $apiClient->checkPrice('create_server', [
                'product_id' => (int) $product['provider_product_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'quantity' => (int) $validated['quantity'],
            ]);
            $providerTotalCost = (int) ($priceCheck['total'] ?? $quote['total_provider_cost']);
            if ($providerTotalCost > 0) {
                $quote['total_provider_cost'] = $providerTotalCost;
            }
        } catch (\Throwable $e) {
            $lock->release();
            return back()->with('error', 'Cấu hình này tạm hết máy hoặc chưa thể đặt lúc này: ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        }

        $qty = (int) $validated['quantity'];
        $baseLabel = trim((string) ($validated['label'] ?? ''));
        $osNote = trim((string) ($validated['os_note'] ?? ''));
        $orderRef = 'DSRV-' . strtoupper(Str::random(8));
        $createdServers = [];
        $transaction = null;

        try {
            $unitPaid = (int) floor($totalPrice / $qty);
            $unitCost = (int) floor($quote['total_provider_cost'] / $qty);

            DB::transaction(function () use (
                &$createdServers,
                &$transaction,
                $qty,
                $user,
                $account,
                $product,
                $validated,
                $quote,
                $totalPrice,
                $unitPaid,
                $unitCost,
                $baseLabel,
                $osNote,
                $orderRef,
                $wallet
            ) {
                for ($i = 1; $i <= $qty; $i++) {
                    $label = $baseLabel !== ''
                        ? ($qty > 1 ? "{$baseLabel} #{$i}" : $baseLabel)
                        : ($product['name'] . ' · ' . strtoupper(Str::random(4)));

                    $createdServers[] = DedicatedServer::create([
                        'user_id' => $user->id,
                        'cloudsunny_account_id' => $account->id,
                        'provider_order_id' => $orderRef,
                        'provider_product_id' => (int) $product['provider_product_id'],
                        'plan_slug' => $product['slug'],
                        'plan_name' => $product['name'],
                        'label' => $label,
                        'hardware_config' => $product['config'],
                        'billing_cycle' => $validated['billing_cycle'],
                        'status' => 'Chờ bàn giao',
                        'os_installed' => $osNote !== '' ? $osNote : null,
                        'setup_fee' => (int) ($product['setup_fee'] ?? 0),
                        'provider_setup_fee' => (int) ($product['provider_setup_fee'] ?? 0),
                        'paid_amount' => $unitPaid,
                        'provider_cost' => $unitCost,
                    ]);
                }

                $firstServer = $createdServers[0];
                $transaction = $wallet->debit($user, $totalPrice, [
                    'dedicated_server_id' => $firstServer->id,
                    'type' => 'buy_server',
                    'provider_cost' => (int) $quote['total_provider_cost'],
                    'discount_amount' => (int) $quote['discount_amount'],
                    'description' => "Đặt thuê {$qty}x Máy chủ vật lý {$product['name']} ({$quote['cycle_label']})",
                ]);
            });

            $remoteItems = $apiClient->createServerOrder(
                (int) $product['provider_product_id'],
                $validated['billing_cycle'],
                $qty
            );

            foreach ($createdServers as $idx => $srv) {
                $remote = is_array($remoteItems[$idx] ?? null) ? $remoteItems[$idx] : null;
                if ($remote && !empty($remote['id'])) {
                    $srv->update([
                        'provider_server_id' => (int) $remote['id'],
                        'status' => DedicatedServer::mapUpstreamStatus((string) ($remote['status'] ?? 'waiting')),
                        'provider_payload' => $remote,
                    ]);
                }
            }

            if ($transaction) {
                app(AffiliateService::class)->processCommission($user, $totalPrice, 'buy_server', "txn_{$transaction->id}");
            }

            $target = $createdServers[0];

            return redirect()
                ->route('servers.show', $target)
                ->with('success', "Đã đặt thuê thành công {$qty}x Máy chủ vật lý {$product['name']}! Kỹ thuật sẽ lắp đặt và bàn giao thông tin truy cập + IPMI trực tiếp trên trang quản lý.");
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                DedicatedServer::whereIn('id', collect($createdServers)->pluck('id'))->delete();
                return back()->with('error', 'Số dư ví không đủ để hoàn tất thanh toán.');
            }
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            DedicatedServer::whereIn('id', collect($createdServers)->pluck('id'))->delete();
            Log::error('Dedicated server order failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Đặt thuê Máy chủ vật lý chưa thành công (đã hoàn 100% tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            if ($transaction) {
                $wallet->refundTransaction($transaction, CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
            }
            DedicatedServer::whereIn('id', collect($createdServers)->pluck('id'))->delete();
            Log::error('Dedicated server order exception', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Đặt thuê Máy chủ vật lý thất bại (đã hoàn tiền về ví): ' . CloudSunnyApiService::sanitizeErrorMessage($e->getMessage()));
        } finally {
            $lock->release();
        }
    }
}

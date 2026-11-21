<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\AffiliateService;
use App\Services\CloudSunnyAccountRouter;
use App\Services\CloudSunnyApiService;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessResellerVpsCreation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $vpsId;
    protected $userId;
    protected $planId;
    protected $osId;

    public $timeout = 120;

    public function __construct($vpsId, $userId, $planId, $osId)
    {
        $this->vpsId = $vpsId;
        $this->userId = $userId;
        $this->planId = $planId;
        $this->osId = $osId;
    }

    public function handle(
        CloudSunnyApiService $apiService,
        CloudSunnyAccountRouter $accountRouter,
        WalletService $wallet
    ): void {
        $vps = VpsInstance::find($this->vpsId);
        $reseller = User::find($this->userId);

        if (!$vps || !$reseller) {
            return;
        }

        $transaction = Transaction::where('vps_instance_id', $vps->id)
            ->whereIn('type', ['buy', 'buy_vps'])
            ->first();

        try {
            // Chọn account theo đúng giá vốn gốc (provider_cost) chứ không chọn bừa account hết tiền
            $account = $accountRouter->pickForOrder((int) $vps->provider_cost);
            if (!$account) {
                throw new \RuntimeException('Cụm máy chủ hiện đã đầy hạn mức cấp phát.');
            }

            $client = $apiService->forAccount($account);
            $client->authenticate($account);

            $billingCycleStr = (string) $vps->billing_cycle;
            $duration = (int) filter_var($billingCycleStr, FILTER_SANITIZE_NUMBER_INT);

            $cycleCode = match ($duration) {
                3 => 'quarterly',
                6 => 'semi_annually',
                12 => 'annually',
                24 => 'biennially',
                36 => 'triennially',
                default => in_array($billingCycleStr, ['quarterly', 'semi_annually', 'annually', 'biennially', 'triennially'], true)
                    ? $billingCycleStr
                    : 'monthly',
            };

            $result = $client->createVpsOrder((int) $this->planId, $cycleCode, (int) $this->osId);
            $serverInfo = $result[0] ?? $result;

            $getValue = static function ($arr, array $keys, $default = null) {
                if (!is_array($arr)) {
                    return $default;
                }
                foreach ($keys as $k) {
                    if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                        return $arr[$k];
                    }
                }
                return $default;
            };

            $vps->cloudsunny_account_id = $account->id;
            $vps->provider_vps_id = $getValue($serverInfo, ['id', 'vps_id', 'server_id', 'service_id'], null);
            $vps->public_ip = $getValue($serverInfo, ['ip', 'ip_address', 'ipv4', 'main_ip', 'public_ip'], null);
            $vps->root_password = $getValue($serverInfo, ['password', 'root_password', 'login_password'], '');
            $vps->login_username = $getValue($serverInfo, ['username', 'user', 'login_username', 'login_user'], 'root');
            $vps->status = $vps->public_ip ? 'Sẵn sàng' : 'Đang khởi tạo...';
            $vps->provider_payload = is_array($serverInfo) ? $serverInfo : null;
            $vps->save();

            if ($transaction) {
                app(AffiliateService::class)->processCommission($reseller, abs((int) $transaction->amount), 'buy_vps', "txn_{$transaction->id}");
            }

            if (!empty($reseller->webhook_url) && \App\Http\Controllers\ResellerApiController::isSafePublicWebhookUrl((string) $reseller->webhook_url)) {
                try {
                    Http::timeout(10)->post($reseller->webhook_url, [
                        'event' => 'vps.created',
                        'order_id' => $vps->provider_order_id,
                        'timestamp' => time(),
                        'data' => [
                            'vps_id' => $vps->id,
                            'ip_address' => $vps->public_ip,
                            'username' => $vps->login_username,
                            'password' => $vps->root_password,
                            'status' => $vps->public_ip ? 'active' : 'provisioning',
                            'expired_at' => $vps->expires_at ? $vps->expires_at->toIso8601String() : null,
                        ],
                    ]);
                } catch (\Throwable $hookError) {
                    Log::warning('Reseller Webhook vps.created delivery failed: ' . $hookError->getMessage(), [
                        'user_id' => $reseller->id,
                        'vps_id' => $vps->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('ProcessResellerVpsCreation Failed: ' . $e->getMessage());

            $sanitizedReason = CloudSunnyApiService::sanitizeErrorMessage($e->getMessage());
            $vps->status = 'Lỗi - đã hoàn tiền';
            $vps->save();

            if ($transaction) {
                $wallet->refundTransaction($transaction, $sanitizedReason);
            }

            if (!empty($reseller->webhook_url) && \App\Http\Controllers\ResellerApiController::isSafePublicWebhookUrl((string) $reseller->webhook_url)) {
                try {
                    Http::timeout(10)->post($reseller->webhook_url, [
                        'event' => 'vps.failed',
                        'order_id' => $vps->provider_order_id,
                        'timestamp' => time(),
                        'data' => [
                            'vps_id' => $vps->id,
                            'status' => 'failed',
                            'refunded' => true,
                            'reason' => $sanitizedReason,
                        ],
                    ]);
                } catch (\Throwable $hookError) {
                    Log::warning('Reseller Webhook vps.failed delivery failed: ' . $hookError->getMessage());
                }
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CloudSunnyAccount;
use App\Models\DedicatedServer;
use App\Models\ProxyInstance;
use App\Models\VpsInstance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CloudSunnyWebhookController extends Controller
{
    /**
     * Tiếp nhận Webhook Callback bất đồng bộ từ hạ tầng CloudSunny.
     * Xác minh chữ ký HMAC-SHA256 (header X-Callback-Signature) trên raw body theo api_secret, fail-closed HTTP 401.
     */
    public function handle(Request $request): JsonResponse
    {
        $rawBody = (string) $request->getContent();
        $signature = trim((string) $request->header('X-Callback-Signature', ''));

        if ($rawBody === '' || $signature === '') {
            return response()->json(['ok' => false, 'message' => 'Missing signature'], 401);
        }

        $matchedAccount = $this->verifyAccountSignature($rawBody, $signature);
        if ($matchedAccount === false) {
            Log::warning('CloudSunny webhook rejected: invalid HMAC signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return response()->json(['ok' => false, 'message' => 'Invalid JSON'], 400);
        }

        $action = strtolower(trim((string) ($payload['action'] ?? '')));
        $hasError = (int) ($payload['error'] ?? 0) !== 0;
        $data = $payload['data'] ?? [];
        $accountId = $matchedAccount instanceof CloudSunnyAccount ? $matchedAccount->id : null;

        try {
            if (in_array($action, [
                'create_vps',
                'rebuild_vps',
                'change_ip_vps',
                'extend_disk_vps',
                'upgrade_vps',
                'renew_vps',
                'change_password_vps',
                'on_vps',
                'off_vps',
                'restart_vps',
            ], true)) {
                $this->handleVpsCallback($action, $hasError, $data, $accountId);
            } elseif (in_array($action, [
                'create_proxy',
                'edit_proxy',
                'edit_sock5_proxy',
                'change_type_proxy',
                'sync_ip_proxy',
                'renew_proxy',
            ], true)) {
                $this->handleProxyCallback($action, $hasError, $data, $accountId);
            } elseif (in_array($action, ['create_server', 'renew_server'], true)) {
                $this->handleServerCallback($action, $hasError, $data, $accountId);
            }
        } catch (\Throwable $e) {
            Log::error('CloudSunny webhook processing error', [
                'action' => $action,
                'callback_id' => $payload['callback_id'] ?? null,
                'msg' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Kiểm tra X-Callback-Signature trên toàn bộ tài khoản đại lý đang hoạt động (hoặc config fallback).
     * Trả về CloudSunnyAccount | true (config fallback) | false (không khớp).
     */
    private function verifyAccountSignature(string $rawBody, string $signature): CloudSunnyAccount|bool
    {
        $accounts = CloudSunnyAccount::where('is_active', true)->get();
        foreach ($accounts as $account) {
            $secret = (string) $account->api_secret;
            if ($secret === '') {
                continue;
            }
            $expected = hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals($expected, $signature)) {
                return $account;
            }
        }

        $fallbackSecret = (string) config('cloudsunny.api_secret', '');
        if ($fallbackSecret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $fallbackSecret), $signature)) {
            return true;
        }

        return false;
    }

    private function handleVpsCallback(string $action, bool $hasError, mixed $data, ?int $accountId): void
    {
        $items = $this->normalizeItemList($data);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $providerId = (int) ($item['id'] ?? $item['vps_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }

            $query = VpsInstance::where('provider_vps_id', $providerId);
            if ($accountId !== null) {
                $query->where('cloudsunny_account_id', $accountId);
            }

            /** @var VpsInstance|null $vps */
            $vps = $query->first();
            if (!$vps) {
                continue;
            }

            $itemError = isset($item['error']) && ((int) $item['error'] !== 0 || $item['error'] === true);
            $update = [];

            if (!empty($item['ip']) || !empty($item['ip_address'])) {
                $update['public_ip'] = $item['ip'] ?? $item['ip_address'];
            }
            if (!empty($item['username']) || !empty($item['login_username'])) {
                $update['login_username'] = $item['username'] ?? $item['login_username'];
            }
            if (!empty($item['password']) || !empty($item['root_password'])) {
                $update['root_password'] = $item['password'] ?? $item['root_password'];
            }

            if (!empty($item['status'])) {
                $update['status'] = VpsInstance::mapUpstreamStatus((string) $item['status']);
            } elseif (!$itemError && !$hasError) {
                $update['status'] = match ($action) {
                    'off_vps' => 'Đã tắt',
                    'on_vps', 'restart_vps', 'create_vps', 'rebuild_vps', 'change_ip_vps', 'extend_disk_vps', 'upgrade_vps', 'renew_vps' => 'Sẵn sàng',
                    default => $vps->status,
                };
            } elseif ($itemError && in_array($action, ['change_ip_vps', 'extend_disk_vps'], true)) {
                $update['status'] = 'Sẵn sàng';
            }

            if (!empty($item['next_due_date'])) {
                try {
                    $parsedDue = Carbon::parse($item['next_due_date']);
                    $curExp = $vps->expires_at ? $vps->expires_at->copy() : Carbon::now();
                    $update['expires_at'] = $curExp->setDate($parsedDue->year, $parsedDue->month, $parsedDue->day);
                } catch (\Throwable $e) {
                }
            }

            if (is_array($vps->provider_payload)) {
                $update['provider_payload'] = array_merge($vps->provider_payload, $item);
            } else {
                $update['provider_payload'] = $item;
            }

            if (!empty($update)) {
                $vps->update($update);
            }
        }
    }

    private function handleProxyCallback(string $action, bool $hasError, mixed $data, ?int $accountId): void
    {
        $items = $this->normalizeItemList($data);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $providerId = (int) ($item['id'] ?? $item['proxy_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }

            $query = ProxyInstance::where('provider_proxy_id', $providerId);
            if ($accountId !== null) {
                $query->where('cloudsunny_account_id', $accountId);
            }

            /** @var ProxyInstance|null $proxy */
            $proxy = $query->first();
            if (!$proxy) {
                continue;
            }

            $itemError = isset($item['error']) && ((int) $item['error'] !== 0 || $item['error'] === true);
            if ($itemError || $hasError) {
                continue;
            }

            $type = strtoupper((string) ($item['type'] ?? $proxy->type_proxy ?? 'HTTP'));
            $isSocks5 = $type === 'SOCKS5';

            $update = [
                'type_proxy' => $type,
                'status' => str_contains(strtolower((string) ($item['status'] ?? 'on')), 'off') ? 'Đã tắt' : 'Hoạt động',
            ];

            if (!empty($item['ip'])) {
                $update['ip'] = $item['ip'];
            }
            if (!empty($item['port'])) {
                $update['port'] = $item['port'];
                if ($isSocks5) {
                    $update['sock5_port'] = $item['port'];
                }
            }
            if (!empty($item['username'])) {
                $update['username'] = $item['username'];
                if ($isSocks5) {
                    $update['sock5_username'] = $item['username'];
                }
            }
            if (!empty($item['password'])) {
                $update['password'] = $item['password'];
                if ($isSocks5) {
                    $update['sock5_password'] = $item['password'];
                }
            }
            if (!empty($item['sock5_port'])) {
                $update['sock5_port'] = $item['sock5_port'];
            }
            if (!empty($item['sock5_username'])) {
                $update['sock5_username'] = $item['sock5_username'];
            }
            if (!empty($item['sock5_password'])) {
                $update['sock5_password'] = $item['sock5_password'];
            }
            if (!empty($item['next_due_date']) || !empty($item['expires_at'])) {
                try {
                    $update['expires_at'] = Carbon::parse($item['next_due_date'] ?? $item['expires_at']);
                } catch (\Throwable $e) {
                }
            }

            $proxy->update($update);
        }
    }

    private function handleServerCallback(string $action, bool $hasError, mixed $data, ?int $accountId): void
    {
        $items = $this->normalizeItemList($data);
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $providerId = (int) ($item['id'] ?? $item['server_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }

            $query = DedicatedServer::where('provider_server_id', $providerId);
            if ($accountId !== null) {
                $query->where('cloudsunny_account_id', $accountId);
            }

            /** @var DedicatedServer|null $server */
            $server = $query->first();
            if (!$server) {
                continue;
            }

            $itemError = isset($item['error']) && ((int) $item['error'] !== 0 || $item['error'] === true);
            if ($itemError || $hasError) {
                continue;
            }

            $update = [
                'status' => !empty($item['status'])
                    ? DedicatedServer::mapUpstreamStatus((string) $item['status'])
                    : 'Hoạt động',
                'provider_payload' => is_array($server->provider_payload)
                    ? array_merge($server->provider_payload, $item)
                    : $item,
            ];

            if (!empty($item['next_due_date'])) {
                try {
                    $update['expires_at'] = Carbon::parse($item['next_due_date']);
                } catch (\Throwable $e) {
                }
            }

            if ($update['status'] === 'Hoạt động' && !$server->handed_over_at) {
                $update['handed_over_at'] = now();
            }

            $server->update($update);
        }
    }

    private function normalizeItemList(mixed $data): array
    {
        if (!is_array($data) || empty($data)) {
            return [];
        }

        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }

        if (isset($data['id']) || isset($data['vps_id']) || isset($data['proxy_id'])) {
            return [$data];
        }

        if (isset($data['ids']) && is_array($data['ids'])) {
            return array_map(static fn ($id) => ['id' => (int) $id], $data['ids']);
        }

        return [];
    }
}

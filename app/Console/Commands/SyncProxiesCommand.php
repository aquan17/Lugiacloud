<?php

namespace App\Console\Commands;

use App\Models\ProxyInstance;
use App\Models\CloudSunnyAccount;
use App\Services\CloudSunnyApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncProxiesCommand extends Command
{
    protected $signature = 'cloudsunny:sync-proxies';
    protected $description = 'Đồng bộ thông tin (IP, Port, Status...) của toàn bộ Proxy từ API gốc';

    public function handle(CloudSunnyApiService $api): int
    {
        $this->info('Bắt đầu đồng bộ Proxies...');
        
        // Lấy danh sách các tài khoản đang có proxies
        $accounts = CloudSunnyAccount::whereHas('proxies', function($q) {
            $q->whereNotNull('provider_proxy_id');
        })->get();

        $count = 0;
        
        foreach ($accounts as $account) {
            $this->info("Đang xử lý Account: {$account->id}...");
            try {
                // Lấy danh sách proxies từ API
                $remoteProxies = $api->forAccount($account)->listProxies();
                
                $remoteMap = [];
                foreach ($remoteProxies as $rp) {
                    if (isset($rp['id'])) {
                        $remoteMap[$rp['id']] = $rp;
                    }
                }
                
                // Lấy proxies local của tài khoản này
                $localProxies = ProxyInstance::where('cloudsunny_account_id', $account->id)
                                ->whereNotNull('provider_proxy_id')
                                ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn']) // Bỏ qua những con không cần thiết nếu muốn tối ưu
                                ->get();
                                
                foreach ($localProxies as $proxy) {
                    if (isset($remoteMap[$proxy->provider_proxy_id])) {
                        $remote = $remoteMap[$proxy->provider_proxy_id];
                        $newStatus = $this->mapProxyStatus((string) ($remote['status'] ?? 'on'));
                        
                        $expiresAt = null;
                        foreach (['next_due_date', 'expires_at', 'expired_at', 'expire_at', 'expiration_at', 'end_at', 'end_date', 'ngay_het_han'] as $k) {
                            if (!empty($remote[$k])) {
                                try {
                                    $expiresAt = \Carbon\Carbon::parse($remote[$k]);
                                    break;
                                } catch (\Throwable $err) {}
                            }
                        }

                        $changed = false;
                        if ($proxy->ip !== ($remote['ip'] ?? $proxy->ip) || 
                            $proxy->port != ($remote['port'] ?? $proxy->port) ||
                            $proxy->status !== $newStatus ||
                            ($expiresAt && $proxy->expires_at != $expiresAt)) 
                        {
                            $changed = true;
                        }
                        
                        if ($changed) {
                            $type = strtoupper((string) ($remote['type'] ?? $proxy->type_proxy ?? 'HTTP'));
                            $isSocks5 = $type === 'SOCKS5';

                            $proxy->update(array_filter([
                                'ip'             => $remote['ip'] ?? $proxy->ip,
                                'port'           => $remote['port'] ?? $proxy->port,
                                'username'       => $remote['username'] ?? $proxy->username,
                                'password'       => $remote['password'] ?? $proxy->password,
                                'sock5_port'     => $isSocks5 ? ($remote['port'] ?? $proxy->port) : ($remote['sock5_port'] ?? $proxy->sock5_port),
                                'sock5_username' => $isSocks5 ? ($remote['username'] ?? $proxy->username) : ($remote['sock5_username'] ?? $proxy->sock5_username),
                                'sock5_password' => $isSocks5 ? ($remote['password'] ?? $proxy->password) : ($remote['sock5_password'] ?? $proxy->sock5_password),
                                'type_proxy'     => $type,
                                'status'         => $newStatus,
                                'expires_at'     => $expiresAt ?: $proxy->expires_at,
                            ], function($val) {
                                return $val !== null;
                            }));
                            
                            $this->info("- Đã cập nhật Proxy #{$proxy->id} (API ID: {$proxy->provider_proxy_id})");
                            $count++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Lỗi đồng bộ Proxy cho Account {$account->id}: " . $e->getMessage());
                $this->error("Lỗi với tài khoản {$account->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Đồng bộ hoàn tất! Đã cập nhật thay đổi cho {$count} proxies.");
        return 0;
    }

    private function mapProxyStatus(string $status): string
    {
        $status = strtolower(strip_tags($status));

        if (in_array($status, ['on', 'active', 'running', 'ready', 'hoạt động'], true)) {
            return 'Hoạt động';
        }

        if (in_array($status, ['off', 'inactive', 'stopped', 'tắt', 'đã tắt'], true)) {
            return 'Đã tắt';
        }

        if (in_array($status, ['progressing', 'pending', 'creating', 'waiting'], true)) {
            return 'Đang khởi tạo';
        }

        if (in_array($status, ['renewing', 'renew'], true)) {
            return 'Đang gia hạn';
        }

        if (in_array($status, ['expire', 'expired', 'het han', 'hết hạn'], true)) {
            return 'Hết hạn';
        }

        if (in_array($status, ['deleted', 'delete', 'đã xóa'], true)) {
            return 'Đã xóa';
        }

        return 'Hoạt động';
    }
}

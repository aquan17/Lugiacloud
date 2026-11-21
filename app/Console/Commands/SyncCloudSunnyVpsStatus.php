<?php

namespace App\Console\Commands;

use App\Models\VpsInstance;
use App\Services\CloudSunnyApiService;
use Illuminate\Console\Command;

class SyncCloudSunnyVpsStatus extends Command
{
    protected $signature = 'cloudsunny:sync-status';
    protected $description = 'Dong bo trang thai VPS tu CloudSunny';

    public function handle(CloudSunnyApiService $api): int
    {
        $accounts = \App\Models\CloudSunnyAccount::where('is_active', true)->get();

        foreach ($accounts as $account) {
            try {
                // Lấy toàn bộ danh sách VPS của Account trong 1 request duy nhất
                $remoteVpsList = $api->forAccount($account)->listVps();

                if (isset($remoteVpsList['vps']) && is_array($remoteVpsList['vps'])) {
                    $remoteVpsList = $remoteVpsList['vps'];
                } elseif (isset($remoteVpsList['servers']) && is_array($remoteVpsList['servers'])) {
                    $remoteVpsList = $remoteVpsList['servers'];
                } elseif (isset($remoteVpsList['items']) && is_array($remoteVpsList['items'])) {
                    $remoteVpsList = $remoteVpsList['items'];
                }

                $remoteMap = [];
                if (is_array($remoteVpsList)) {
                    foreach ($remoteVpsList as $rv) {
                        $vId = $rv['id'] ?? $rv['vps_id'] ?? null;
                        if ($vId) {
                            $remoteMap[$vId] = $rv;
                        }
                    }
                }

                $instances = VpsInstance::where('cloudsunny_account_id', $account->id)
                    ->whereNotNull('provider_vps_id')
                    ->whereNotIn('status', ['Đã xóa', 'Đã xoá', 'Hết hạn'])
                    ->get();

                foreach ($instances as $vps) {
                    try {
                        $remote = $remoteMap[$vps->provider_vps_id] ?? null;

                        // Chỉ gọi riêng chi tiết nếu VPS đang khởi tạo/bảo trì/đổi IP và chưa có trong danh sách tổng
                        if (!$remote && $vps->isProvisioning()) {
                            $remote = $api->forAccount($account)->getVps((int) $vps->provider_vps_id);
                        }

                        if (!$remote) {
                            continue;
                        }

                        $remotePass = $this->remoteValue($remote, ['password', 'root_password', 'login_password']);

                        $updateData = [
                            'public_ip' => $this->remoteValue($remote, ['ip', 'ip_address', 'ipv4', 'main_ip', 'public_ip'], $vps->public_ip),
                            'login_username' => $this->remoteValue($remote, ['username', 'user', 'login_username', 'login_user'], $vps->login_username),
                            'status' => VpsInstance::mapUpstreamStatus((string) $this->remoteValue($remote, ['status', 'state', 'power_status', 'vm_status', 'trang_thai'], $vps->status)),
                            'provider_payload' => $remote,
                        ];

                        $remoteDueDate = $this->remoteValue($remote, ['next_due_date', 'text_next_due_date', 'expires_at', 'ngay_het_han']);
                        if (!empty($remoteDueDate)) {
                            try {
                                $parsedDue = \Carbon\Carbon::parse($remoteDueDate);
                                $curExp = $vps->expires_at ? $vps->expires_at->copy() : \Carbon\Carbon::now();
                                $updateData['expires_at'] = $curExp->setDate($parsedDue->year, $parsedDue->month, $parsedDue->day);
                            } catch (\Throwable $e) {}
                        }

                        if (!empty($remotePass)) {
                            $updateData['root_password'] = $remotePass;
                        }

                        $vps->update($updateData);
                        $this->info("Updated VPS ID {$vps->id}");
                    } catch (\Throwable $e) {
                        $this->error("VPS {$vps->id}: {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("Account {$account->id}: {$e->getMessage()}");
            }
        }

        return 0;
    }

    private function remoteValue(array $remote, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $remote) && $remote[$key] !== null && $remote[$key] !== '') {
                return $remote[$key];
            }
        }

        return $default;
    }
}

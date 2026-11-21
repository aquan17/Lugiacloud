<?php

namespace App\Console\Commands;

use App\Models\CloudSunnyAccount;
use App\Models\VpsInstance;
use App\Services\CloudSunnyApiService;
use Illuminate\Console\Command;

class AuditVpsMismatch extends Command
{
    protected $signature = 'cloudsunny:audit-vps';
    protected $description = 'So sanh danh sach VPS giua API CloudSunny va database local, tim ra VPS bi thieu';

    public function handle(CloudSunnyApiService $api): int
    {
        $accounts = CloudSunnyAccount::where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            $this->error('Khong tim thay CloudSunny account nao active.');
            return 1;
        }

        $allApiVps = collect();
        $allDbVps = VpsInstance::whereNotIn('status', ['Đã xóa'])->get();

        $this->info("=== DATABASE: {$allDbVps->count()} VPS (khong tinh Da xoa) ===");
        $this->newLine();

        foreach ($accounts as $account) {
            $this->info("--- Account: {$account->label} (ID: {$account->id}) ---");

            try {
                $api->forAccount($account);
                $remoteList = $api->listVps();

                // listVps() trả về data, có thể là mảng VPS hoặc có key chứa mảng
                $vpsList = $this->extractVpsList($remoteList);

                $this->info("  API tra ve: " . count($vpsList) . " VPS");

                foreach ($vpsList as $vps) {
                    $vpsId = $vps['id'] ?? $vps['vps_id'] ?? $vps['server_id'] ?? null;
                    if ($vpsId) {
                        $allApiVps->push([
                            'provider_vps_id' => $vpsId,
                            'account_id'      => $account->id,
                            'account_label'   => $account->label,
                            'ip'              => $vps['ip'] ?? $vps['ip_address'] ?? $vps['ipv4'] ?? $vps['main_ip'] ?? 'N/A',
                            'status'          => $vps['status'] ?? $vps['trang_thai'] ?? $vps['state'] ?? 'N/A',
                            'label'           => $vps['label'] ?? $vps['hostname'] ?? $vps['name'] ?? 'N/A',
                            'product'         => $vps['product_name'] ?? $vps['plan'] ?? $vps['ten_san_pham'] ?? 'N/A',
                            'expires'         => $vps['expires_at'] ?? $vps['ngay_het_han'] ?? $vps['expiry_date'] ?? 'N/A',
                            'raw'             => $vps,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  Loi khi goi API cho account {$account->label}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("=== API TONG CONG: {$allApiVps->count()} VPS ===");
        $this->newLine();

        // --- TIM VPS CO TREN API NHUNG KHONG CO TRONG DB ---
        $dbProviderIds = $allDbVps->pluck('provider_vps_id')->filter()->map(fn($id) => (string) $id)->toArray();
        $apiProviderIds = $allApiVps->pluck('provider_vps_id')->map(fn($id) => (string) $id)->toArray();

        $missingInDb = $allApiVps->filter(function ($item) use ($dbProviderIds) {
            return !in_array((string) $item['provider_vps_id'], $dbProviderIds, true);
        });

        if ($missingInDb->isNotEmpty()) {
            $this->warn(">>> {$missingInDb->count()} VPS CO TREN API NHUNG KHONG CO TRONG DB <<<");
            $this->table(
                ['Provider VPS ID', 'Account', 'IP', 'Status (API)', 'Label', 'Product', 'Expires'],
                $missingInDb->map(fn($v) => [
                    $v['provider_vps_id'],
                    $v['account_label'],
                    $v['ip'],
                    $v['status'],
                    $v['label'],
                    $v['product'],
                    $v['expires'],
                ])->toArray()
            );
        } else {
            $this->info('>>> Khong co VPS nao tren API bi thieu trong DB.');
        }

        $this->newLine();

        // --- TIM VPS CO TRONG DB NHUNG KHONG CO TREN API ---
        $missingInApi = $allDbVps->filter(function ($vps) use ($apiProviderIds) {
            if (!$vps->provider_vps_id) return false;
            return !in_array((string) $vps->provider_vps_id, $apiProviderIds, true);
        });

        if ($missingInApi->isNotEmpty()) {
            $this->warn(">>> {$missingInApi->count()} VPS CO TRONG DB NHUNG KHONG CO TREN API <<<");
            $this->table(
                ['DB ID', 'Provider VPS ID', 'Account ID', 'IP', 'Status (DB)', 'Label', 'User ID', 'Expires'],
                $missingInApi->map(fn($v) => [
                    $v->id,
                    $v->provider_vps_id,
                    $v->cloudsunny_account_id,
                    $v->public_ip ?? 'N/A',
                    $v->status,
                    $v->label ?? 'N/A',
                    $v->user_id,
                    $v->expires_at ? $v->expires_at->toDateTimeString() : 'N/A',
                ])->toArray()
            );
        } else {
            $this->info('>>> Khong co VPS nao trong DB bi thieu tren API.');
        }

        $this->newLine();

        // --- VPS TRONG DB KHONG CO provider_vps_id (orphan) ---
        $orphans = $allDbVps->filter(fn($v) => empty($v->provider_vps_id));
        if ($orphans->isNotEmpty()) {
            $this->warn(">>> {$orphans->count()} VPS TRONG DB KHONG CO provider_vps_id (orphan) <<<");
            $this->table(
                ['DB ID', 'Status', 'Label', 'IP', 'User ID', 'Created At'],
                $orphans->map(fn($v) => [
                    $v->id,
                    $v->status,
                    $v->label ?? 'N/A',
                    $v->public_ip ?? 'N/A',
                    $v->user_id,
                    $v->created_at ? $v->created_at->toDateTimeString() : 'N/A',
                ])->toArray()
            );
        }

        // --- SUMMARY ---
        $this->newLine();
        $this->info('=== TONG KET ===');
        $this->info("DB (khong tinh Da xoa): {$allDbVps->count()}");
        $this->info("API: {$allApiVps->count()}");
        $this->info("Chenh lech: " . abs($allApiVps->count() - $allDbVps->count()));
        $this->info("Thieu trong DB: {$missingInDb->count()}");
        $this->info("Thieu tren API: {$missingInApi->count()}");
        $this->info("Orphan (khong co provider_vps_id): {$orphans->count()}");

        return 0;
    }

    /**
     * Extract VPS list from various API response formats.
     */
    private function extractVpsList(array $data): array
    {
        // Nếu data có key chứa mảng VPS
        foreach (['vps', 'servers', 'items', 'data', 'list'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && !empty($data[$key])) {
                // Kiểm tra xem đây có phải mảng VPS hay là 1 VPS đơn lẻ
                if (isset($data[$key][0]) && is_array($data[$key][0])) {
                    return $data[$key];
                }
            }
        }

        // Nếu data là mảng VPS trực tiếp
        if (isset($data[0]) && is_array($data[0])) {
            return $data;
        }

        // Nếu chỉ có 1 VPS
        if (isset($data['id'])) {
            return [$data];
        }

        return $data;
    }
}

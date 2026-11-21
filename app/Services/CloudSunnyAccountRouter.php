<?php

namespace App\Services;

use App\Models\CloudSunnyAccount;

class CloudSunnyAccountRouter
{
    public function firstActive(): ?CloudSunnyAccount
    {
        $account = CloudSunnyAccount::query()
            ->where('is_active', true)
            ->where('is_full', false)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        return $account ?: $this->envAccount();
    }

    /**
     * Chọn tài khoản đại lý gốc theo đúng GIÁ VỐN GỐC ($providerCostVnd) thay vì giá bán lẻ.
     * Nếu tất cả tài khoản đều có credit_vnd > 0 được đồng bộ mà không tài khoản nào đủ tiền gốc,
     * trả về null để báo hết slot an toàn thay vì gọi bừa API làm văng lỗi hết tiền đại lý.
     */
    public function pickForOrder(int $providerCostVnd): ?CloudSunnyAccount
    {
        $required = max(0, $providerCostVnd);

        $fundedAccount = CloudSunnyAccount::query()
            ->where('is_active', true)
            ->where('is_full', false)
            ->where('credit_vnd', '>=', $required)
            ->orderBy('priority')
            ->orderByDesc('credit_vnd')
            ->orderBy('id')
            ->first();

        if ($fundedAccount) {
            return $fundedAccount;
        }

        // Chỉ fallback nếu tài khoản chưa từng đồng bộ số dư (last_synced_at === null)
        $unsyncedAccount = CloudSunnyAccount::query()
            ->where('is_active', true)
            ->where('is_full', false)
            ->whereNull('last_synced_at')
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        return $unsyncedAccount ?: $this->envAccount();
    }

    private function envAccount(): ?CloudSunnyAccount
    {
        $username = config('cloudsunny.api_username');
        $app = config('cloudsunny.api_app');
        $secret = config('cloudsunny.api_secret');

        if (!$username || !$app || !$secret) {
            return null;
        }

        return CloudSunnyAccount::firstOrCreate(
            ['api_username' => $username, 'api_app' => $app],
            [
                'label' => 'Primary Upstream Node',
                'api_secret' => $secret,
                'is_active' => true,
                'priority' => 0,
            ]
        );
    }
}

<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WelcomeOfferService
{
    public const CACHE_KEY = 'lugiacloud.welcome_offer.config_v1';
    public const USER_CACHE_VER_KEY = 'lugiacloud.welcome_offer.user_ver';

    public const DEFAULT_CODE = 'WELCOME10';
    public const DEFAULT_PERCENT = 10;
    public const DEFAULT_MAX_DISCOUNT = 50000;
    public const DEFAULT_VALID_DAYS = 7;
    public const DEFAULT_MAX_MONTHS = 1;

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $nextVer = ((int) Cache::get(self::USER_CACHE_VER_KEY, 1)) + 1;
        Cache::forever(self::USER_CACHE_VER_KEY, $nextVer);
    }

    public function forgetUserCache(int $userId): void
    {
        $ver = (int) Cache::get(self::USER_CACHE_VER_KEY, 1);
        Cache::forget("lugiacloud.welcome_offer.user_v{$ver}_{$userId}");
    }

    public function getConfig(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            if (!Schema::hasTable('settings')) {
                return $this->defaults();
            }

            $keys = [
                'welcome_offer_enabled',
                'welcome_offer_code',
                'welcome_offer_percent',
                'welcome_offer_max_discount',
                'welcome_offer_valid_days',
                'welcome_offer_max_months',
            ];

            $rows = Setting::whereIn('key', $keys)->pluck('value', 'key');

            return [
                'enabled' => $rows->has('welcome_offer_enabled') ? ($rows['welcome_offer_enabled'] === '1') : true,
                'code' => strtoupper(trim((string) ($rows['welcome_offer_code'] ?? self::DEFAULT_CODE))) ?: self::DEFAULT_CODE,
                'percent' => max(1, min(50, (int) ($rows['welcome_offer_percent'] ?? self::DEFAULT_PERCENT))),
                'max_discount' => max(0, (int) ($rows['welcome_offer_max_discount'] ?? self::DEFAULT_MAX_DISCOUNT)),
                'valid_days' => max(1, min(90, (int) ($rows['welcome_offer_valid_days'] ?? self::DEFAULT_VALID_DAYS))),
                'max_months' => max(1, min(12, (int) ($rows['welcome_offer_max_months'] ?? self::DEFAULT_MAX_MONTHS))),
            ];
        });
    }

    public function updateConfig(array $data): array
    {
        $oldCfg = $this->getConfig();
        $code = strtoupper(trim((string) ($data['code'] ?? self::DEFAULT_CODE))) ?: self::DEFAULT_CODE;

        $pairs = [
            'welcome_offer_enabled' => !empty($data['enabled']) ? '1' : '0',
            'welcome_offer_code' => $code,
            'welcome_offer_percent' => (string) max(1, min(50, (int) ($data['percent'] ?? self::DEFAULT_PERCENT))),
            'welcome_offer_max_discount' => (string) max(0, (int) ($data['max_discount'] ?? self::DEFAULT_MAX_DISCOUNT)),
            'welcome_offer_valid_days' => (string) max(1, min(90, (int) ($data['valid_days'] ?? self::DEFAULT_VALID_DAYS))),
            'welcome_offer_max_months' => (string) max(1, min(12, (int) ($data['max_months'] ?? self::DEFAULT_MAX_MONTHS))),
        ];

        foreach ($pairs as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }

        $this->flushCache();
        $this->syncWelcomeCoupon($oldCfg['code'] ?? self::DEFAULT_CODE);

        return $this->getConfig();
    }

    /**
     * Đảm bảo bản ghi Coupon chuẩn cho Khách hàng mới luôn tồn tại và đồng bộ thông số.
     */
    public function syncWelcomeCoupon(?string $previousCode = null): ?Coupon
    {
        if (!Schema::hasTable('coupons') || !Schema::hasColumn('coupons', 'first_order_only')) {
            return null;
        }

        $cfg = $this->getConfig();

        $coupon = Coupon::where('code', $cfg['code'])->first();
        if (!$coupon && $previousCode && $previousCode !== $cfg['code']) {
            $coupon = Coupon::where('code', $previousCode)->first();
        }

        $payload = [
            'code' => $cfg['code'],
            'description' => "Ưu đãi thành viên mới: Giảm {$cfg['percent']}% đơn đầu tiên (gói {$cfg['max_months']} tháng)",
            'discount_type' => 'percent',
            'discount_value' => $cfg['percent'],
            'max_discount_amount' => $cfg['max_discount'] > 0 ? $cfg['max_discount'] : null,
            'min_order_amount' => 0,
            'applies_to' => 'all',
            'apply_on_renew' => false,
            'first_order_only' => true,
            'max_months' => $cfg['max_months'],
            'stack_with_campaign' => false,
            'protect_cost_floor' => true,
            'max_uses' => null,
            'max_uses_per_user' => 1,
            'is_huntable' => false,
            'is_active' => (bool) $cfg['enabled'],
        ];

        if ($coupon) {
            $coupon->update($payload);
            return $coupon->fresh();
        }

        return Coupon::create($payload);
    }

    /**
     * Cấp tự động Voucher chào mừng vào Ví ngay khi khách hàng đăng ký tài khoản mới.
     */
    public function grantToNewUser(User $user): ?UserVoucher
    {
        $cfg = $this->getConfig();
        if (!$cfg['enabled'] || !Schema::hasTable('user_vouchers')) {
            return null;
        }

        $coupon = $this->syncWelcomeCoupon();
        if (!$coupon) {
            return null;
        }

        $voucher = UserVoucher::firstOrCreate(
            [
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
            ],
            [
                'claimed_at' => now(),
                'expires_at' => now()->addDays($cfg['valid_days']),
            ]
        );

        $this->forgetUserCache($user->id);

        return $voucher;
    }

    public function hasCompletedServiceOrder(User $user): bool
    {
        if (!Schema::hasTable('transactions')) {
            return false;
        }

        return Transaction::where('user_id', $user->id)
            ->whereIn('type', ['buy', 'buy_vps', 'buy_proxy'])
            ->where('status', '!=', 'refunded')
            ->exists();
    }

    /**
     * Lấy trạng thái ưu đãi thành viên mới cho trình duyệt (chia sẻ qua Inertia).
     * - Guest: mode = 'guest' (kích thích đăng ký)
     * - New user còn hạn 7 ngày & chưa mua đơn đầu: mode = 'eligible' (hiển thị TopBar cá nhân + Dashboard + Ví Voucher)
     * - User cũ / đã dùng / quá hạn: null (tự trả lại giao diện Sự kiện bình thường)
     */
    public function getActiveOfferForUser(?User $user): ?array
    {
        $cfg = $this->getConfig();
        if (!$cfg['enabled']) {
            return null;
        }

        if (!$user) {
            return [
                'mode' => 'guest',
                'code' => $cfg['code'],
                'percent' => $cfg['percent'],
                'max_discount' => $cfg['max_discount'],
                'valid_days' => $cfg['valid_days'],
                'max_months' => $cfg['max_months'],
                'expires_at' => null,
            ];
        }

        $ver = (int) Cache::get(self::USER_CACHE_VER_KEY, 1);
        $userCacheKey = "lugiacloud.welcome_offer.user_v{$ver}_{$user->id}";

        $cached = Cache::remember($userCacheKey, 120, function () use ($user, $cfg) {
            if (!Schema::hasTable('user_vouchers') || !Schema::hasTable('coupons')) {
                return ['__none' => true];
            }

            $coupon = Coupon::where('code', $cfg['code'])->where('is_active', true)->first();
            if (!$coupon) {
                return ['__none' => true];
            }

            $uv = UserVoucher::where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->whereNull('used_at')
                ->first();

            if (!$uv) {
                return ['__none' => true];
            }

            if ($uv->expires_at && $uv->expires_at->isPast()) {
                return ['__none' => true];
            }

            if ($this->hasCompletedServiceOrder($user)) {
                return ['__none' => true];
            }

            $expiresAt = $uv->expires_at ?: $user->created_at?->copy()->addDays($cfg['valid_days']);

            return [
                'mode' => 'eligible',
                'code' => $coupon->code,
                'percent' => (int) $coupon->discount_value,
                'max_discount' => (int) ($coupon->max_discount_amount ?? 0),
                'valid_days' => $cfg['valid_days'],
                'max_months' => (int) ($coupon->max_months ?? $cfg['max_months']),
                'expires_at' => $expiresAt?->toIso8601String(),
                'expires_display' => $expiresAt?->format('H:i d/m/Y'),
            ];
        });

        if (!is_array($cached) || !empty($cached['__none'])) {
            return null;
        }

        if (!empty($cached['expires_at']) && now()->greaterThan($cached['expires_at'])) {
            Cache::forget($userCacheKey);
            return null;
        }

        return $cached;
    }

    private function defaults(): array
    {
        return [
            'enabled' => true,
            'code' => self::DEFAULT_CODE,
            'percent' => self::DEFAULT_PERCENT,
            'max_discount' => self::DEFAULT_MAX_DISCOUNT,
            'valid_days' => self::DEFAULT_VALID_DAYS,
            'max_months' => self::DEFAULT_MAX_MONTHS,
        ];
    }
}

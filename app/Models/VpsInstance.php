<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VpsInstance extends Model
{
    protected $fillable = [
        'user_id',
        'cloudsunny_account_id',
        'provider_vps_id',
        'provider_order_id',
        'provider_product_id',
        'provider_os_id',
        'billing_cycle',
        'label',
        'root_password',
        'region',
        'plan_id',
        'status',
        'is_auto_renew',
        'public_ip',
        'login_username',
        'provider_payload',
        'cpu',
        'ram',
        'disk',
        'cost_monthly_usd',
        'hourly_price_usd',
        'provider_cost',
        'paid_amount',
        'expires_at',
    ];

    protected $casts = [
        'cost_monthly_usd' => 'decimal:4',
        'hourly_price_usd' => 'decimal:6',
        'provider_payload' => 'array',
        'provider_cost' => 'integer',
        'paid_amount' => 'integer',
        'cpu' => 'integer',
        'ram' => 'integer',
        'disk' => 'integer',
        'is_auto_renew' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public const BILLING_CYCLES = [
        'monthly' => '1 Tháng',
        'twomonthly' => '2 Tháng',
        'quarterly' => '3 Tháng',
        'semi_annually' => '6 Tháng',
        'half-yearly' => '6 Tháng',
        'annually' => '1 Năm',
        'yearly' => '1 Năm',
        'biennially' => '2 Năm',
        'triennially' => '3 Năm',
    ];

    public function getBillingCycleLabelAttribute(): string
    {
        $key = strtolower(trim((string) $this->billing_cycle));

        return self::BILLING_CYCLES[$key]
            ?? config("cloudsunny.billing_cycles.{$key}.label")
            ?? ($key ? ucfirst(str_replace('_', ' ', $key)) : '1 Tháng');
    }

    public function getRootPasswordAttribute($value)
    {
        if (empty($value)) {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }

    public function setRootPasswordAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['root_password'] = $value;
        } else {
            $this->attributes['root_password'] = Crypt::encryptString((string) $value);
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cloudSunnyAccount()
    {
        return $this->belongsTo(CloudSunnyAccount::class, 'cloudsunny_account_id');
    }

    /**
     * Ánh xạ chuẩn hóa 100% toàn bộ mã trạng thái từ hạ tầng gốc về nhãn hiển thị tiếng Việt.
     */
    public static function mapUpstreamStatus(string $status): string
    {
        $s = mb_strtolower(strip_tags(trim($status)), 'UTF-8');
        $matchWord = static fn (string $needle): bool => (bool) preg_match(
            '/(?:^|[^a-z0-9_\p{L}])' . preg_quote($needle, '/') . '(?:$|[^a-z0-9_\p{L}])/u',
            $s
        );

        foreach (['running', 'online', 'ready', 'started', 'power on', 'poweron', 'on', 'completed', 'complete', 'active', 'start_vps', 'đang chạy', 'hoạt động', 'sẵn sàng', 'bật'] as $k) {
            if ($matchWord($k)) {
                return 'Sẵn sàng';
            }
        }
        foreach (['offline', 'stopped', 'shutdown', 'power off', 'poweroff', 'off', 'stop_vps', 'đã tắt'] as $k) {
            if ($matchWord($k)) {
                return 'Đã tắt';
            }
        }
        foreach (['deleted', 'removed', 'cancel', 'delete_vps', 'change_user', 'đã xóa', 'đã xoá', 'đã hủy'] as $k) {
            if ($matchWord($k)) {
                return 'Đã xóa';
            }
        }
        foreach (['expire', 'expired', 'hết hạn', 'quá hạn'] as $k) {
            if ($matchWord($k)) {
                return 'Hết hạn';
            }
        }
        foreach (['change_ip', 'đổi ip'] as $k) {
            if ($matchWord($k)) {
                return 'Đang đổi IP...';
            }
        }
        foreach (['maintenance', 'extend_disk', 'bảo trì', 'mở rộng'] as $k) {
            if ($matchWord($k)) {
                return 'Đang bảo trì...';
            }
        }
        foreach (['upgrade', 'nâng cấp'] as $k) {
            if ($matchWord($k)) {
                return 'Đang nâng cấp...';
            }
        }
        foreach (['rebuild', 'cài đặt lại', 'tạo lại'] as $k) {
            if ($matchWord($k)) {
                return 'Đang cài đặt lại...';
            }
        }
        foreach (['renewing', 'chờ gia hạn'] as $k) {
            if ($matchWord($k)) {
                return 'Đang xử lý gia hạn...';
            }
        }
        foreach (['restart', 'reboot', 'khởi động lại'] as $k) {
            if ($matchWord($k)) {
                return 'Đang khởi động lại';
            }
        }
        foreach (['error', 'failed', 'fail', 'lỗi'] as $k) {
            if ($matchWord($k)) {
                return 'Lỗi';
            }
        }

        return 'Đang khởi tạo...';
    }

    public function normalizedState(): string
    {
        if ($this->isOverdue5Days()) {
            return 'deleted';
        }
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isProvisioning()) {
            return 'provisioning';
        }
        $st = mb_strtolower((string) $this->status, 'UTF-8');
        if (str_contains($st, 'lỗi') || str_contains($st, 'error') || str_contains($st, 'fail')) {
            return 'failed';
        }
        if (str_contains($st, 'tắt') || str_contains($st, 'stop') || str_contains($st, 'off')) {
            return 'stopped';
        }
        if (str_contains($st, 'khởi động lại') || str_contains($st, 'reboot') || str_contains($st, 'restart')) {
            return 'rebooting';
        }

        return 'running';
    }

    public function displayStatus(): string
    {
        $state = $this->normalizedState();
        if ($state === 'provisioning') {
            $raw = trim((string) $this->status);
            $st = mb_strtolower($raw, 'UTF-8');
            if (str_contains($st, 'đổi ip') || str_contains($st, 'change_ip')) {
                return 'Đang đổi IP';
            }
            if (str_contains($st, 'mở rộng') || str_contains($st, 'extend_disk')) {
                return 'Đang mở rộng ổ cứng';
            }
            if (str_contains($st, 'bảo trì') || str_contains($st, 'maintenance')) {
                return 'Đang bảo trì';
            }
            if (str_contains($st, 'nâng cấp') || str_contains($st, 'upgrade')) {
                return 'Đang nâng cấp';
            }
            if (str_contains($st, 'cài đặt lại') || str_contains($st, 'rebuild')) {
                return 'Đang cài lại OS';
            }
            if (str_contains($st, 'gia hạn') || str_contains($st, 'renewing')) {
                return 'Đang gia hạn';
            }
            return 'Đang khởi tạo';
        }

        return match ($state) {
            'deleted' => 'Đã xóa',
            'expired' => 'Quá hạn',
            'stopped' => 'Đã tắt',
            'rebooting' => 'Khởi động lại',
            'failed' => 'Lỗi kết nối',
            default => $this->status ?: 'Sẵn sàng',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this->normalizedState(), ['failed', 'deleted', 'expired'], true);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->normalizedState()) {
            'running' => 'badge-success',
            'failed', 'deleted' => 'badge-danger',
            default => 'badge-warning',
        };
    }

    public function isProvisioning(): bool
    {
        $st = mb_strtolower((string) $this->status, 'UTF-8');

        return str_contains($st, 'khởi tạo')
            || str_contains($st, 'xử lý')
            || str_contains($st, 'cài đặt')
            || str_contains($st, 'cài lại')
            || str_contains($st, 'đổi ip')
            || str_contains($st, 'bảo trì')
            || str_contains($st, 'mở rộng')
            || str_contains($st, 'nâng cấp')
            || str_contains($st, 'pending')
            || str_contains($st, 'creating')
            || str_contains($st, 'waiting')
            || str_contains($st, 'progressing')
            || str_contains($st, 'renewing')
            || str_contains($st, 'rebuild')
            || str_contains($st, 'change_ip')
            || str_contains($st, 'maintenance')
            || str_contains($st, 'upgrade')
            || str_contains($st, 'provisioning');
    }

    public function getExpiresAtAttribute($value)
    {
        return $value ? Carbon::parse($value) : null;
    }

    public function isOverdue5Days(): bool
    {
        $st = mb_strtolower((string) $this->status, 'UTF-8');
        if ($st === 'đã xóa' || str_contains($st, 'xóa') || str_contains($st, 'xoá') || in_array($st, ['cancel', 'delete_vps', 'change_user'], true)) {
            return true;
        }
        if (!$this->expires_at) {
            return false;
        }
        $daysOverdue = (int) $this->expires_at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false);

        return $daysOverdue >= 5;
    }

    public function isExpired(): bool
    {
        if ($this->isOverdue5Days()) {
            return false;
        }
        $st = mb_strtolower((string) $this->status, 'UTF-8');

        return $st === 'hết hạn' || $st === 'expire' || $st === 'expired' || (bool) ($this->expires_at && $this->expires_at->isPast());
    }

    public function canRenew(): bool
    {
        return !$this->isOverdue5Days();
    }

    /**
     * Tối ưu Bulk SQL Update: Không tải Collection ra PHP memory, không chạy vòng lặp N+1 UPDATE.
     */
    public static function syncOverdueStatus($userId = null): void
    {
        $cacheKey = 'vps_sync_overdue_' . ($userId ?: 'all');
        if (!app()->runningUnitTests() && Cache::has($cacheKey)) {
            return;
        }

        $fiveDaysAgo = now()->startOfDay()->subDays(5);
        $now = now();

        $deletedQuery = self::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $fiveDaysAgo)
            ->where('status', '!=', 'Đã xóa');

        $expiredQuery = self::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $fiveDaysAgo)
            ->where('expires_at', '<', $now)
            ->whereNotIn('status', ['Hết hạn', 'Đã xóa']);

        if ($userId) {
            $deletedQuery->where('user_id', $userId);
            $expiredQuery->where('user_id', $userId);
        }

        $deletedQuery->update(['status' => 'Đã xóa']);
        $expiredQuery->update(['status' => 'Hết hạn']);

        if (!app()->runningUnitTests()) {
            Cache::put($cacheKey, true, 60);
        }
    }

    protected static function booted()
    {
        static::updated(function (VpsInstance $vps) {
            $isNewlyReady = $vps->wasChanged('status') && $vps->status === 'Sẵn sàng';
            $isNewlyProvisionedIp = $vps->wasChanged('public_ip') && $vps->status === 'Sẵn sàng' && !empty($vps->public_ip);
            $isRecentInstance = !$vps->created_at || $vps->created_at->greaterThan(now()->subHours(24));

            if ($isRecentInstance && ($isNewlyReady || $isNewlyProvisionedIp) && !empty($vps->public_ip) && !empty($vps->root_password)) {
                $cacheKey = "vps_welcome_email_sent_{$vps->id}";

                if (Cache::add($cacheKey, true, 86400 * 30)) {
                    try {
                        if ($vps->user && !empty($vps->user->email)) {
                            Mail::to($vps->user->email)->queue(
                                new \App\Mail\VpsCreated($vps, (string) $vps->root_password)
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to send VPS welcome email', [
                            'vps_id' => $vps->id,
                            'msg' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }
}

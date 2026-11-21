<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProxyInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cloudsunny_account_id',
        'provider_proxy_id',
        'provider_order_id',
        'product_id',
        'type_proxy',
        'ip',
        'port',
        'username',
        'password',
        'sock5_port',
        'sock5_username',
        'sock5_password',
        'status',
        'billing_cycle',
        'cost_monthly_usd',
        'paid_amount',
        'expires_at',
        'is_auto_renew',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'cost_monthly_usd' => 'decimal:4',
        'paid_amount' => 'integer',
        'is_auto_renew' => 'boolean',
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

    public function getPasswordAttribute($value)
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

    public function setPasswordAttribute($value): void
    {
        $this->attributes['password'] = empty($value) ? $value : Crypt::encryptString((string) $value);
    }

    public function getSock5PasswordAttribute($value)
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

    public function setSock5PasswordAttribute($value): void
    {
        $this->attributes['sock5_password'] = empty($value) ? $value : Crypt::encryptString((string) $value);
    }

    public function getBillingCycleLabelAttribute(): string
    {
        $key = strtolower(trim((string) $this->billing_cycle));

        return self::BILLING_CYCLES[$key]
            ?? config("cloudsunny.billing_cycles.{$key}.label")
            ?? ($key ? ucfirst(str_replace('_', ' ', $key)) : '1 Tháng');
    }

    public function normalizedState(): string
    {
        $st = mb_strtolower((string) $this->status, 'UTF-8');
        if (
            str_contains($st, 'xóa')
            || str_contains($st, 'xoá')
            || str_contains($st, 'hết hạn')
            || str_contains($st, 'đã tắt')
            || ($this->expires_at && $this->expires_at->isPast())
        ) {
            return 'deleted';
        }
        if (str_contains($st, 'lỗi') || str_contains($st, 'fail') || str_contains($st, 'error')) {
            return 'failed';
        }
        if (str_contains($st, 'khởi tạo') || str_contains($st, 'pending') || str_contains($st, 'progress')) {
            return 'provisioning';
        }

        return 'active';
    }

    public function displayStatus(): string
    {
        return match ($this->normalizedState()) {
            'deleted' => 'Đã xóa',
            'provisioning' => 'Đang khởi tạo',
            'failed' => 'Lỗi kết nối',
            default => $this->status ?: 'Hoạt động',
        };
    }

    /**
     * Proxy hết hạn sẽ bị thu hồi IP ngay lập tức -> chuyển thẳng sang Đã xóa và tắt tự động gia hạn.
     */
    public static function syncExpiredToDeleted(?int $userId = null): void
    {
        $cacheKey = 'proxy_sync_expired_' . ($userId ?: 'all');
        if (!app()->runningUnitTests() && Cache::has($cacheKey)) {
            return;
        }

        $query = self::query()
            ->whereNotIn('status', ['Đã xóa', 'Đã xoá'])
            ->where(function ($q) {
                $q->whereIn('status', ['Hết hạn', 'Đã tắt'])
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('expires_at')->where('expires_at', '<', now());
                    });
            });

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $query->update([
            'status' => 'Đã xóa',
            'is_auto_renew' => false,
        ]);

        if (!app()->runningUnitTests()) {
            Cache::put($cacheKey, true, 60);
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

    protected static function booted()
    {
        static::updated(function (ProxyInstance $proxy) {
            $becameActiveOrIp = ($proxy->wasChanged('status') || $proxy->wasChanged('ip'))
                && $proxy->status === 'Hoạt động'
                && !empty($proxy->ip);
            $isRecentInstance = !$proxy->created_at || $proxy->created_at->greaterThan(now()->subHours(24));

            if ($isRecentInstance && $becameActiveOrIp) {
                $cacheKey = "proxy_welcome_email_sent_{$proxy->id}";

                if (Cache::add($cacheKey, true, 86400 * 30)) {
                    try {
                        if ($proxy->user && !empty($proxy->user->email)) {
                            Mail::to($proxy->user->email)->queue(new \App\Mail\ProxyCreated($proxy));
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to send Proxy welcome email', [
                            'proxy_id' => $proxy->id,
                            'msg' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }
}

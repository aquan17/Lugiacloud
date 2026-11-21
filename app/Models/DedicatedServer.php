<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DedicatedServer extends Model
{
    protected $fillable = [
        'user_id',
        'cloudsunny_account_id',
        'provider_server_id',
        'provider_order_id',
        'provider_product_id',
        'plan_slug',
        'plan_name',
        'label',
        'hardware_config',
        'billing_cycle',
        'status',
        'is_auto_renew',
        'public_ip',
        'os_installed',
        'login_username',
        'root_password',
        'ipmi_url',
        'ipmi_username',
        'ipmi_password',
        'handover_note',
        'setup_fee',
        'provider_setup_fee',
        'paid_amount',
        'provider_cost',
        'provider_payload',
        'expires_at',
        'handed_over_at',
    ];

    protected $casts = [
        'hardware_config' => 'array',
        'provider_payload' => 'array',
        'is_auto_renew' => 'boolean',
        'setup_fee' => 'integer',
        'provider_setup_fee' => 'integer',
        'paid_amount' => 'integer',
        'provider_cost' => 'integer',
        'expires_at' => 'datetime',
        'handed_over_at' => 'datetime',
    ];

    public const BILLING_CYCLES = [
        'monthly' => '1 Tháng',
        'twomonthly' => '2 Tháng',
        'quarterly' => '3 Tháng',
        'semi_annually' => '6 Tháng',
        'annually' => '1 Năm',
        'biennially' => '2 Năm',
        'triennially' => '3 Năm',
    ];

    public function getBillingCycleLabelAttribute(): string
    {
        $key = strtolower(trim((string) $this->billing_cycle));

        return self::BILLING_CYCLES[$key] ?? ($key ? ucfirst(str_replace('_', ' ', $key)) : '1 Tháng');
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
        $this->attributes['root_password'] = empty($value) ? null : Crypt::encryptString((string) $value);
    }

    public function getIpmiPasswordAttribute($value)
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

    public function setIpmiPasswordAttribute($value): void
    {
        $this->attributes['ipmi_password'] = empty($value) ? null : Crypt::encryptString((string) $value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cloudSunnyAccount()
    {
        return $this->belongsTo(CloudSunnyAccount::class, 'cloudsunny_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'dedicated_server_id');
    }

    /**
     * Ánh xạ toàn bộ trạng thái Server vật lý từ CloudSunny API.
     */
    public static function mapUpstreamStatus(string $status): string
    {
        $s = mb_strtolower(trim($status), 'UTF-8');

        return match ($s) {
            'on', 'active', 'running', 'hoạt động' => 'Hoạt động',
            'waiting', 'pending', 'chờ bàn giao' => 'Chờ bàn giao',
            'renewing', 'đang gia hạn' => 'Đang gia hạn',
            'expire', 'expired', 'hết hạn' => 'Hết hạn',
            'cancel', 'change_user', 'delete', 'deleted', 'đã xóa', 'đã xoá', 'đã hủy' => 'Đã xóa',
            default => $status !== '' ? $status : 'Chờ bàn giao',
        };
    }

    public function normalizedState(): string
    {
        $st = mb_strtolower((string) $this->status, 'UTF-8');
        if (str_contains($st, 'xóa') || str_contains($st, 'xoá') || str_contains($st, 'hủy') || in_array($st, ['cancel', 'change_user', 'delete', 'deleted'], true)) {
            return 'deleted';
        }
        if (str_contains($st, 'chờ bàn giao') || str_contains($st, 'waiting') || str_contains($st, 'pending')) {
            return 'provisioning';
        }
        if (str_contains($st, 'gia hạn') || str_contains($st, 'renewing')) {
            return 'provisioning';
        }
        if (str_contains($st, 'hết hạn') || str_contains($st, 'expire') || ($this->expires_at && $this->expires_at->isPast())) {
            return 'expired';
        }

        return 'running';
    }

    public function displayStatus(): string
    {
        $st = mb_strtolower((string) $this->status, 'UTF-8');
        if (str_contains($st, 'gia hạn') || str_contains($st, 'renewing')) {
            return 'Đang gia hạn';
        }

        return match ($this->normalizedState()) {
            'deleted' => 'Đã hủy / Xóa',
            'provisioning' => 'Chờ bàn giao',
            'expired' => 'Hết hạn',
            default => 'Hoạt động',
        };
    }

    public function canRenew(): bool
    {
        return in_array($this->normalizedState(), ['running', 'expired'], true)
            && (bool) ($this->cloudSunnyAccount && $this->provider_server_id);
    }

    public function hasCredentials(): bool
    {
        return !empty($this->public_ip) || !empty($this->login_username) || !empty($this->ipmi_url);
    }
}

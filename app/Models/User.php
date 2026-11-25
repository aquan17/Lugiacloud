<?php

namespace App\Models;

use App\Constants\UserRole;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const SETTING_REQUIRE_EMAIL_VERIFICATION = 'require_email_verification';
    public const CACHE_REQUIRE_EMAIL_VERIFICATION = 'setting_require_email_verification';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'balance',
        'is_admin',
        'ref_code',
        'referred_by',
        'api_key',
        'webhook_url',
        'discount_percent',
        'proxy_discount_percent',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_key',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'balance' => 'integer',
        'is_admin' => 'integer',
        'discount_percent' => 'integer',
        'proxy_discount_percent' => 'integer',
    ];

    public function vpsInstances()
    {
        return $this->hasMany(VpsInstance::class);
    }

    public function proxyInstances()
    {
        return $this->hasMany(ProxyInstance::class);
    }

    public function dedicatedServers()
    {
        return $this->hasMany(DedicatedServer::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function topupRequests()
    {
        return $this->hasMany(TopupRequest::class);
    }

    public function isAdmin(): bool
    {
        return (int) $this->is_admin === UserRole::ADMIN;
    }

    public function isReseller(): bool
    {
        return (int) $this->is_admin === UserRole::RESELLER;
    }

    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Quản trị viên';
        }
        if ($this->isReseller()) {
            return 'Đại lý API';
        }

        return 'Khách hàng';
    }

    public static function isEmailVerificationRequired(): bool
    {
        return Cache::remember(self::CACHE_REQUIRE_EMAIL_VERIFICATION, 300, function () {
            if (!Schema::hasTable('settings')) {
                return true;
            }

            $val = Setting::where('key', self::SETTING_REQUIRE_EMAIL_VERIFICATION)->value('value');

            return $val === null ? true : ((string) $val === '1');
        });
    }

    public function hasVerifiedEmail(): bool
    {
        if (!self::isEmailVerificationRequired()) {
            return true;
        }

        return !is_null($this->email_verified_at);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new QueuedResetPassword($token));
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }
}

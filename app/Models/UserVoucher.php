<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVoucher extends Model
{
    protected $fillable = [
        'user_id',
        'coupon_id',
        'claimed_at',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $clear = function (self $uv) {
            if ($uv->user_id) {
                app(\App\Services\WelcomeOfferService::class)->forgetUserCache((int) $uv->user_id);
            }
        };

        static::saved($clear);
        static::deleted($clear);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}

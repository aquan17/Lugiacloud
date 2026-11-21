<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'idempotency_key',
        'user_id',
        'vps_instance_id',
        'proxy_instance_id',
        'dedicated_server_id',
        'campaign_id',
        'coupon_code',
        'type',
        'status',
        'amount',
        'provider_cost',
        'discount_amount',
        'balance_before',
        'balance_after',
        'description',
    ];

    protected $casts = [
        'amount' => 'integer',
        'provider_cost' => 'integer',
        'discount_amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (self $transaction) {
            if (empty($transaction->status)) {
                $transaction->status = 'completed';
            }

            if (empty($transaction->code)) {
                $prefix = 'TXN';
                $type = strtolower((string) $transaction->type);

                if (in_array($type, ['buy', 'buy_vps', 'buy_proxy', 'buy_server'], true)) {
                    $prefix = !empty($transaction->dedicated_server_id) || $type === 'buy_server'
                        ? 'SRV'
                        : (!empty($transaction->proxy_instance_id) ? 'PRX' : 'VPS');
                } elseif ($type === 'renew') {
                    $prefix = !empty($transaction->dedicated_server_id)
                        ? 'SRV-RNW'
                        : (!empty($transaction->proxy_instance_id) ? 'PRX-RNW' : 'VPS-RNW');
                } elseif ($type === 'upgrade') {
                    $prefix = 'UPG';
                } elseif ($type === 'change_ip') {
                    $prefix = 'IP';
                } elseif ($type === 'refund') {
                    $prefix = 'REF';
                } elseif ($type === 'topup_bonus') {
                    $prefix = 'BNS';
                } elseif ($type === 'affiliate_bonus') {
                    $prefix = 'AFF';
                }

                do {
                    $random = strtoupper(Str::random(6));
                    $code = "LUG-{$prefix}-{$random}";
                } while (self::where('code', $code)->exists());

                $transaction->code = $code;
            }
        });

        static::saved(function (self $transaction) {
            if ($transaction->user_id && in_array($transaction->type, ['buy', 'buy_vps', 'buy_proxy', 'buy_server', 'refund'], true)) {
                app(\App\Services\WelcomeOfferService::class)->forgetUserCache((int) $transaction->user_id);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vps()
    {
        return $this->belongsTo(VpsInstance::class, 'vps_instance_id');
    }

    public function proxy()
    {
        return $this->belongsTo(ProxyInstance::class, 'proxy_instance_id');
    }

    public function dedicatedServer()
    {
        return $this->belongsTo(DedicatedServer::class, 'dedicated_server_id');
    }

    public function campaign()
    {
        return $this->belongsTo(PromotionCampaign::class, 'campaign_id');
    }
}

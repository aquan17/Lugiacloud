<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'campaign_id',
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'applies_to',
        'apply_on_renew',
        'first_order_only',
        'max_months',
        'stack_with_campaign',
        'protect_cost_floor',
        'max_uses',
        'used_count',
        'max_uses_per_user',
        'starts_at',
        'ends_at',
        'is_active',
        'is_huntable',
        'claim_limit',
        'claimed_count',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'max_discount_amount' => 'integer',
        'min_order_amount' => 'integer',
        'apply_on_renew' => 'boolean',
        'first_order_only' => 'boolean',
        'max_months' => 'integer',
        'stack_with_campaign' => 'boolean',
        'protect_cost_floor' => 'boolean',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'max_uses_per_user' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'is_huntable' => 'boolean',
        'claim_limit' => 'integer',
        'claimed_count' => 'integer',
    ];

    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function campaign()
    {
        return $this->belongsTo(PromotionCampaign::class, 'campaign_id');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}

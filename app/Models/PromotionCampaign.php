<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PromotionCampaign extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'event_tag',
        'badge_text',
        'accent_color',
        'description',
        'starts_at',
        'ends_at',
        'is_active',
        'send_launch_email',
        'launch_email_sent_at',
        'launch_email_count',
        'priority',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'target_scope',
        'target_plans',
        'min_months',
        'apply_on_renew',
        'apply_on_upgrade',
        'stack_with_user_discount',
        'protect_cost_floor',
        'topup_bonus_percent',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'send_launch_email' => 'boolean',
        'launch_email_sent_at' => 'datetime',
        'launch_email_count' => 'integer',
        'priority' => 'integer',
        'discount_value' => 'integer',
        'max_discount_amount' => 'integer',
        'target_plans' => 'array',
        'min_months' => 'integer',
        'apply_on_renew' => 'boolean',
        'apply_on_upgrade' => 'boolean',
        'stack_with_user_discount' => 'boolean',
        'protect_cost_floor' => 'boolean',
        'topup_bonus_percent' => 'float',
    ];

    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    public function isRunningNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function banners()
    {
        return $this->hasMany(PromotionBanner::class, 'campaign_id');
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'campaign_id');
    }
}

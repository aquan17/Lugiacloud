<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PromotionBanner extends Model
{
    protected $fillable = [
        'campaign_id',
        'placement',
        'eyebrow',
        'title',
        'subtitle',
        'cta_text',
        'cta_url',
        'coupon_code_display',
        'image_desktop',
        'image_mobile',
        'theme_preset',
        'bg_from',
        'bg_to',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('campaign_id')
                    ->orWhereHas('campaign', function (Builder $cq) use ($now) {
                        $cq->where('is_active', true)
                            ->where(fn (Builder $sq) => $sq->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                            ->where(fn (Builder $eq) => $eq->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
                    });
            })
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function campaign()
    {
        return $this->belongsTo(PromotionCampaign::class, 'campaign_id');
    }
}

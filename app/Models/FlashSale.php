<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FlashSale extends Model
{
    protected $fillable = [
        'name',
        'badge_text',
        'service_type',
        'plan_slug',
        'discount_type',
        'discount_value',
        'total_slots',
        'sold_slots',
        'max_per_user',
        'max_months',
        'protect_cost_floor',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'total_slots' => 'integer',
        'sold_slots' => 'integer',
        'max_per_user' => 'integer',
        'max_months' => 'integer',
        'protect_cost_floor' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeLiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderByDesc('id');
    }

    public function isLiveNow(): bool
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

    public function isUpcoming(): bool
    {
        return $this->is_active && $this->starts_at && $this->starts_at->isFuture();
    }

    public function remainingSlots(): int
    {
        return max(0, (int) $this->total_slots - (int) $this->sold_slots);
    }

    public function claims()
    {
        return $this->hasMany(FlashSaleClaim::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingCatalog extends Model
{
    protected $fillable = [
        'service_type',
        'slug',
        'provider_code',
        'provider_product_id',
        'name',
        'description',
        'badge',
        'specs',
        'provider_cost_monthly',
        'provider_cycle_costs',
        'markup_mode',
        'markup_percent',
        'retail_price_monthly',
        'retail_cycle_prices',
        'protect_cost_floor',
        'min_margin_percent',
        'is_active',
        'is_featured',
        'sort_order',
        'last_synced_at',
    ];

    protected $casts = [
        'specs' => 'array',
        'provider_cost_monthly' => 'integer',
        'provider_cycle_costs' => 'array',
        'markup_percent' => 'float',
        'retail_price_monthly' => 'integer',
        'retail_cycle_prices' => 'array',
        'protect_cost_floor' => 'boolean',
        'min_margin_percent' => 'float',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function effectiveMonthlyRetail(): int
    {
        if ($this->markup_mode === 'percent_markup' && $this->provider_cost_monthly > 0) {
            $raw = $this->provider_cost_monthly * (1 + ($this->markup_percent / 100));
            return (int) (ceil($raw / 1000) * 1000);
        }

        return (int) $this->retail_price_monthly;
    }

    public function providerCostForCycle(string $cycle, int $months = 1): int
    {
        $cycleCosts = $this->provider_cycle_costs ?? [];
        if (isset($cycleCosts[$cycle]) && (int) $cycleCosts[$cycle] > 0) {
            return (int) $cycleCosts[$cycle];
        }

        return (int) $this->provider_cost_monthly * max(1, $months);
    }

    public function floorRetailForCost(int $providerCost): int
    {
        if (!$this->protect_cost_floor || $providerCost <= 0) {
            return 0;
        }

        $minMargin = max(0, (float) $this->min_margin_percent);
        return (int) ceil($providerCost * (1 + ($minMargin / 100)));
    }
}

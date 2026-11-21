<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlashSaleClaim extends Model
{
    protected $fillable = [
        'flash_sale_id',
        'user_id',
        'transaction_id',
        'quantity',
        'discount_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'discount_amount' => 'integer',
    ];

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

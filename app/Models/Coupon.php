<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'diamond_amount', 'max_uses', 'uses_count', 'expires_at', 'is_active'])]
class Coupon extends Model
{
    protected function casts(): array
    {
        return [
            'diamond_amount' => 'integer',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CouponClaim::class);
    }
}

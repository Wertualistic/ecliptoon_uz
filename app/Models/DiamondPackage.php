<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'diamond_amount', 'price', 'currency', 'is_active', 'sort_order'])]
class DiamondPackage extends Model
{
    protected function casts(): array
    {
        return [
            'diamond_amount' => 'integer',
            'price' => 'float',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}

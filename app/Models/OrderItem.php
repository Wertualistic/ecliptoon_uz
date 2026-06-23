<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'book_id', 'quantity', 'price'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'book_id' => 'integer',
            'quantity' => 'integer',
            'price' => 'integer',
        ];
    }

    /**
     * Parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Book purchased.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}

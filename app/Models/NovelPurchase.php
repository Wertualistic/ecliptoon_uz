<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NovelPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'novel_id',
        'novel_chapter_id',
        'series_id',
        'chapter_id',
        'payment_method_id',
        'receipt_image_path',
        'purchase_type',
        'amount',
        'status',
        'admin_note',
        'expires_at'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * User who made the purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Dedicated novel purchased.
     */
    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }

    /**
     * Dedicated novel chapter purchased.
     */
    public function novelChapter(): BelongsTo
    {
        return $this->belongsTo(NovelChapter::class, 'novel_chapter_id');
    }

    /**
     * Legacy series relationship.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Legacy chapter relationship.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Payment method used for transfer.
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}

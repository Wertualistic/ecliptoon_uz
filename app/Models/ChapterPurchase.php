<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'chapter_id', 'diamonds_spent'])]
class ChapterPurchase extends Model
{
    protected function casts(): array
    {
        return [
            'diamonds_spent' => 'integer',
        ];
    }

    /**
     * User who purchased.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Chapter purchased.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}

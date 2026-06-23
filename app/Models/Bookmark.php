<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'series_id'])]
class Bookmark extends Model
{
    /**
     * User who bookmarked.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Series bookmarked.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }
}

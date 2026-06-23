<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['series_id', 'chapter_number', 'title', 'is_free', 'price_in_diamonds', 'published_at', 'views_count', 'pdf_path'])]
class Chapter extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'chapter_number' => 'float',
            'is_free' => 'boolean',
            'price_in_diamonds' => 'integer',
            'views_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Series this chapter belongs to.
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * Images making up this chapter's pages.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ChapterImage::class)->orderBy('order', 'asc');
    }

    /**
     * Purchases of this chapter.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(ChapterPurchase::class);
    }
}

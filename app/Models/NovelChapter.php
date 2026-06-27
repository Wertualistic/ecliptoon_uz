<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NovelChapter extends Model
{
    protected $table = 'novel_chapters';

    protected $fillable = [
        'novel_id',
        'chapter_number',
        'title',
        'is_free',
        'price_in_uzs',
        'content_text',
        'published_at',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'price_in_uzs' => 'float',
        'chapter_number' => 'float',
        'published_at' => 'datetime',
    ];

    /**
     * Parent novel.
     */
    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class, 'novel_id');
    }

    /**
     * Purchases for this chapter.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(NovelPurchase::class, 'novel_chapter_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'slug', 'alternative_titles', 'description', 'cover_image', 'type', 'status', 'is_mature', 'is_pinned', 'is_slider', 'views_count', 'rating_avg', 'rating_count', 'likes_count', 'translator_id'])]
class Series extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'alternative_titles' => 'array',
            'is_mature' => 'boolean',
            'is_pinned' => 'boolean',
            'is_slider' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    /**
     * Translator for this series.
     */
    public function translator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'translator_id');
    }

    /**
     * Genres associated with this series.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'series_genre');
    }

    /**
     * Chapters belonging to this series.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('chapter_number', 'asc');
    }

    /**
     * Bookmarks for this series.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Sponsors associated with this series.
     */
    public function sponsors(): BelongsToMany
    {
        return $this->belongsToMany(Sponsor::class, 'series_sponsor');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Novel extends Model
{
    protected $table = 'novels';

    protected $fillable = [
        'creator_id',
        'title',
        'slug',
        'alternative_titles',
        'description',
        'cover_image',
        'status',
        'is_mature',
        'views_count',
        'rating_avg',
        'rating_count',
        'likes_count',
    ];

    protected $casts = [
        'is_mature' => 'boolean',
        'views_count' => 'integer',
        'rating_avg' => 'float',
        'rating_count' => 'integer',
        'likes_count' => 'integer',
    ];

    protected $appends = ['type'];

    public function getTypeAttribute(): string
    {
        return 'novel';
    }

    /**
     * Creator (user) who wrote this novel.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Chapters belonging to this novel.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(NovelChapter::class, 'novel_id')->orderBy('chapter_number', 'asc');
    }

    /**
     * Genres assigned to this novel.
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'genre_novel', 'novel_id', 'genre_id');
    }

    /**
     * Purchases associated with this novel.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(NovelPurchase::class, 'novel_id');
    }
}

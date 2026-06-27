<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'chapter_id',
        'novel_chapter_id',
        'content',
        'image_path'
    ];

    /**
     * The user who posted this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Manhwa chapter this comment belongs to.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * The Novel chapter this comment belongs to.
     */
    public function novelChapter(): BelongsTo
    {
        return $this->belongsTo(NovelChapter::class, 'novel_chapter_id');
    }
}

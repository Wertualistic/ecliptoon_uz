<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chapter_id', 'image_path', 'order'])]
class ChapterImage extends Model
{
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /**
     * Chapter this image belongs to.
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}

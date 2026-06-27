<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NovelCreatorApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'receipt_image_path',
        'user_note',
        'status',
        'admin_note'
    ];

    /**
     * User who applied.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

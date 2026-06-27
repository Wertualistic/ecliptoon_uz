<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeriesRating extends Model
{
    use HasFactory;

    protected $table = 'series_ratings';

    protected $fillable = [
        'user_id',
        'series_id',
        'novel_id',
        'rating'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    public function novel(): BelongsTo
    {
        return $this->belongsTo(Novel::class);
    }
}

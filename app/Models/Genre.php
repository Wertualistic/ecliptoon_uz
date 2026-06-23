<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class Genre extends Model
{
    use HasFactory;

    /**
     * Series in this genre.
     */
    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'series_genre');
    }
}

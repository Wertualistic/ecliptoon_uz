<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'package_id', 'amount', 'receipt_image_path', 'user_note', 'status', 'admin_note', 'reviewed_by', 'reviewed_at'])]
class TopupRequest extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * User who submitted this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Diamond package selected.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(DiamondPackage::class);
    }

    /**
     * Admin who reviewed this request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

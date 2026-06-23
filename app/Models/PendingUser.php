<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'password', 'code', 'expires_at', 'referred_by'])]
class PendingUser extends Model
{
    protected $table = 'pending_users';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}

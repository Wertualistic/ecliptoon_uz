<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['role', 'permission'])]
class RolePermission extends Model
{
    protected $table = 'role_permissions';
}

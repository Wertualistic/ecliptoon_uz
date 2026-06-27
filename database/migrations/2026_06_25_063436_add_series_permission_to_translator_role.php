<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add series permission for translators
        \Illuminate\Support\Facades\DB::table('role_permissions')->updateOrInsert(
            [
                'role' => 'translator',
                'permission' => 'series',
            ],
            [
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('role_permissions')
            ->where('role', 'translator')
            ->where('permission', 'series')
            ->delete();
    }
};

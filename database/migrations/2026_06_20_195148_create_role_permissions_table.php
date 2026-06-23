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
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->unique(['role', 'permission']);
            $table->timestamps();
        });

        // Seed default moderator permissions: series and sponsors
        \Illuminate\Support\Facades\DB::table('role_permissions')->insert([
            [
                'role' => 'moderator',
                'permission' => 'series',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'role' => 'moderator',
                'permission' => 'sponsors',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};

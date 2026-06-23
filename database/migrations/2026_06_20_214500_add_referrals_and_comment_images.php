<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add referred_by to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_by')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });

        // 2. Add referred_by to pending_users (we store the ID of the referrer)
        Schema::table('pending_users', function (Blueprint $table) {
            $table->foreignId('referred_by')->nullable()->after('password')->constrained('users')->nullOnDelete();
        });

        // 3. Add referral to diamond_transactions enum
        DB::statement("ALTER TABLE diamond_transactions MODIFY COLUMN type ENUM('topup', 'purchase', 'refund', 'admin_adjustment', 'referral') NOT NULL");

        // 4. Add image_path to chapter_comments
        Schema::table('chapter_comments', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('chapter_comments', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        DB::statement("ALTER TABLE diamond_transactions MODIFY COLUMN type ENUM('topup', 'purchase', 'refund', 'admin_adjustment') NOT NULL");

        Schema::table('pending_users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn('referred_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn('referred_by');
        });
    }
};

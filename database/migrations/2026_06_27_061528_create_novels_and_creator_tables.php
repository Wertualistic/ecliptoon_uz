<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alter series table
        Schema::table('series', function (Blueprint $table) {
            $table->decimal('price_1m', 12, 2)->nullable()->after('translator_id');
            $table->decimal('price_3m', 12, 2)->nullable()->after('price_1m');
            $table->decimal('price_6m', 12, 2)->nullable()->after('price_3m');
        });
        
        // Alter series type column to VARCHAR to support 'novel' alongside enum values
        DB::statement("ALTER TABLE series MODIFY COLUMN type VARCHAR(50) NOT NULL");

        // 2. Alter chapters table
        Schema::table('chapters', function (Blueprint $table) {
            $table->longText('content_text')->nullable()->after('pdf_path');
            $table->decimal('price_in_uzs', 12, 2)->default(0.00)->after('price_in_diamonds');
        });

        // 3. Alter payment_methods table
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('is_active')->constrained('users')->cascadeOnDelete();
        });

        // 4. Alter users table role and add expiry
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('novel_creator_expires_at')->nullable()->after('role');
        });
        
        // Modify user role enum to support novel_creator
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'moderator', 'translator', 'novel_creator') NOT NULL DEFAULT 'user'");

        // 5. Create novel_creator_applications table
        Schema::create('novel_creator_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('receipt_image_path');
            $table->text('user_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        // 6. Create novel_purchases table
        Schema::create('novel_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained('chapters')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->cascadeOnDelete();
            $table->string('receipt_image_path');
            $table->enum('purchase_type', ['single_chapter', 'subscription_1m', 'subscription_3m', 'subscription_6m']);
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('novel_purchases');
        Schema::dropIfExists('novel_creator_applications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('novel_creator_expires_at');
        });
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'moderator', 'translator') NOT NULL DEFAULT 'user'");

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn(['content_text', 'price_in_uzs']);
        });

        DB::statement("ALTER TABLE series MODIFY COLUMN type ENUM('manhwa', 'manga', 'manhua') NOT NULL");
        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['price_1m', 'price_3m', 'price_6m']);
        });
    }
};


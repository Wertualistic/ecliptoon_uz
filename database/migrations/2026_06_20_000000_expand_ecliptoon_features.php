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
        // 1. Extend series table
        Schema::table('series', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->default(0.00)->after('views_count');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            $table->unsignedInteger('likes_count')->default(0)->after('rating_count');
        });

        // 2. Series Ratings
        Schema::create('series_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned(); // 1 to 5 stars
            $table->timestamps();
            $table->unique(['user_id', 'series_id']);
        });

        // 3. Series Likes
        Schema::create('series_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'series_id']);
        });

        // 4. Coupons
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedInteger('diamond_amount');
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Coupon Claims
        Schema::create('coupon_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at')->useCurrent();
            $table->unique(['user_id', 'coupon_id']);
        });

        // 6. Sponsors
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_path');
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 7. Chapter Reads (for sequential reading history tracking)
        Schema::create('chapter_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'chapter_id']);
        });

        // 8. Pending Users (holds registration data prior to email verification)
        Schema::create('pending_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_users');
        Schema::dropIfExists('chapter_reads');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('coupon_claims');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('series_likes');
        Schema::dropIfExists('series_ratings');

        Schema::table('series', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count', 'likes_count']);
        });
    }
};

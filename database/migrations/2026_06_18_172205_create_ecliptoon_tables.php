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
        // 1. Series
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('alternative_titles')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('type', ['manhwa', 'manga', 'manhua']);
            $table->enum('status', ['ongoing', 'completed', 'paused', 'dropped'])->default('ongoing');
            $table->boolean('is_mature')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });

        // 2. Genres
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // 3. Series-Genre Pivot
        Schema::create('series_genre', function (Blueprint $table) {
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->primary(['series_id', 'genre_id']);
        });

        // 4. Chapters
        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->decimal('chapter_number', 8, 2);
            $table->string('title')->nullable();
            $table->boolean('is_free')->default(true);
            $table->unsignedInteger('price_in_diamonds')->default(0);
            $table->timestamp('published_at')->useCurrent();
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });

        // 5. Chapter Images
        Schema::create('chapter_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        // 6. Bookmarks
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'series_id']);
        });

        // 7. Diamond Packages
        Schema::create('diamond_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('diamond_amount');
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('UZS');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 8. Payment Methods (Bank Cards for manual transfer)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('card_number');
            $table->string('card_holder_name');
            $table->string('bank_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 9. Topup Requests
        Schema::create('topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('diamond_packages')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('receipt_image_path');
            $table->text('user_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 10. Diamond Transactions (History logs)
        Schema::create('diamond_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['topup', 'purchase', 'refund', 'admin_adjustment']);
            $table->integer('amount'); // Positive or negative
            $table->string('reference_type')->nullable(); // e.g. 'TopupRequest' or 'Chapter'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('balance_after');
            $table->timestamps();
        });

        // 11. Chapter Purchases
        Schema::create('chapter_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('diamonds_spent');
            $table->timestamps();
            $table->unique(['user_id', 'chapter_id']);
        });

        // 12. Reports (User bug/issue submissions)
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->timestamps();
        });

        // 13. Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('chapter_purchases');
        Schema::dropIfExists('diamond_transactions');
        Schema::dropIfExists('topup_requests');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('diamond_packages');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('chapter_images');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('series_genre');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('series');
    }
};

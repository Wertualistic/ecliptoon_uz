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
        // 1. Dedicated novels table
        Schema::create('novels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('alternative_titles')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('ongoing');
            $table->boolean('is_mature')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedBigInteger('rating_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->timestamps();
        });

        // 2. Dedicated novel_chapters table
        Schema::create('novel_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained('novels')->onDelete('cascade');
            $table->decimal('chapter_number', 8, 2);
            $table->string('title')->nullable();
            $table->boolean('is_free')->default(true);
            $table->decimal('price_in_uzs', 12, 2)->default(0.00);
            $table->longText('content_text');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // 3. Dedicated genre_novel pivot table
        Schema::create('genre_novel', function (Blueprint $table) {
            $table->foreignId('novel_id')->constrained('novels')->onDelete('cascade');
            $table->foreignId('genre_id')->constrained('genres')->onDelete('cascade');
            $table->primary(['novel_id', 'genre_id']);
        });

        // 4. Alter novel_purchases table to link to novel_id and novel_chapter_id
        Schema::table('novel_purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('novel_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('novel_chapter_id')->nullable()->after('novel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('novel_purchases', function (Blueprint $table) {
            $table->dropColumn(['novel_id', 'novel_chapter_id']);
        });
        Schema::dropIfExists('genre_novel');
        Schema::dropIfExists('novel_chapters');
        Schema::dropIfExists('novels');
    }
};

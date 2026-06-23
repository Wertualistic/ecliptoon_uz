<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create books table
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('price');
            $table->string('cover_path')->nullable();
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        // 2. Create orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('total_price');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // 3. Create order_items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->integer('quantity');
            $table->integer('price');
            $table->timestamps();
        });

        // 4. Alter diamond_transactions type enum to support book_purchase
        DB::statement("ALTER TABLE diamond_transactions MODIFY COLUMN type ENUM('topup', 'purchase', 'refund', 'admin_adjustment', 'referral', 'book_purchase') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE diamond_transactions MODIFY COLUMN type ENUM('topup', 'purchase', 'refund', 'admin_adjustment', 'referral') NOT NULL");
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('books');
    }
};

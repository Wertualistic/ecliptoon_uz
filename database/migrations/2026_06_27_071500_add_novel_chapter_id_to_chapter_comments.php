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
        Schema::table('chapter_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('novel_chapter_id')->nullable()->after('chapter_id');
            $table->unsignedBigInteger('chapter_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapter_comments', function (Blueprint $table) {
            $table->dropColumn('novel_chapter_id');
        });
    }
};

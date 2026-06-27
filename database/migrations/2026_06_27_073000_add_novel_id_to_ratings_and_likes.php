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
        Schema::table('series_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('series_id')->nullable()->change();
            $table->unsignedBigInteger('novel_id')->nullable()->after('series_id');
        });

        Schema::table('series_likes', function (Blueprint $table) {
            $table->unsignedBigInteger('series_id')->nullable()->change();
            $table->unsignedBigInteger('novel_id')->nullable()->after('series_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series_ratings', function (Blueprint $table) {
            $table->dropColumn('novel_id');
        });

        Schema::table('series_likes', function (Blueprint $table) {
            $table->dropColumn('novel_id');
        });
    }
};

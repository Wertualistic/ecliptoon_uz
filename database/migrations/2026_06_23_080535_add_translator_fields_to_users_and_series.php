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
        Schema::table('users', function (Blueprint $table) {
            $table->string('instagram_url')->nullable()->after('avatar');
            $table->string('telegram_url')->nullable()->after('instagram_url');
        });

        Schema::table('series', function (Blueprint $table) {
            $table->unsignedBigInteger('translator_id')->nullable()->after('id');
            $table->foreign('translator_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropForeign(['translator_id']);
            $table->dropColumn('translator_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['instagram_url', 'telegram_url']);
        });
    }
};

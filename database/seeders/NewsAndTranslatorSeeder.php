<?php

namespace Database\Seeders;

use App\Models\News;
use App\Models\User;
use App\Models\Series;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NewsAndTranslatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed News
        News::create([
            'title' => 'Ecliptoon platformasining yangi versiyasi ishga tushdi!',
            'content' => "Aziz foydalanuvchilar, Ecliptoon platformasi yanada qulay va tezkor bo'lishi uchun to'liq yangilandi. Endi siz sevgan manhwalaringizni yanada sifatli formatda o'qishingiz mumkin.\n\nYangi dizayn va qo'shimcha imkoniyatlardan bahramand bo'ling!",
        ]);

        News::create([
            'title' => 'Yangi "Solo Leveling" manhvasi qo\'shildi',
            'content' => "Kutishlar o'z nihoyasiga yetdi. Barchaning sevimli manhvasi endi o'zbek tilida sifatli tarjimada saytimizda mavjud. Hoziroq o'qishni boshlang!",
        ]);

        // 2. Ensure Translator permissions exist
        RolePermission::firstOrCreate(['role' => 'translator', 'permission' => 'series']);

        // 3. Seed Translator
        $translator = User::create([
            'name' => 'UzTarjimon',
            'email' => 'translator@ecliptoon.uz',
            'password' => Hash::make('password123'),
            'role' => 'translator',
            'diamond_balance' => 0,
            'instagram_url' => 'https://instagram.com/uztarjimon',
            'telegram_url' => 'https://t.me/uztarjimon_official',
            'is_banned' => false,
            'email_verified_at' => now(),
        ]);

        // 4. Seed a Series for this Translator
        $series = Series::create([
            'title' => 'Soya qiroli (Solo Leveling)',
            'slug' => 'soya-qiroli-' . Str::random(5),
            'description' => 'Eng kuchsiz ovchi qanday qilib eng kuchli Soya Qiroliga aylangani haqida hikoya...',
            'type' => 'manhwa',
            'status' => 'ongoing',
            'is_mature' => false,
            'is_pinned' => true,
            'translator_id' => $translator->id,
            'views_count' => 15420,
            'rating_avg' => 4.9,
        ]);
        
        // Add some dummy chapters to the series
        for ($i = 1; $i <= 3; $i++) {
            \App\Models\Chapter::create([
                'series_id' => $series->id,
                'chapter_number' => $i,
                'title' => $i . '-bob',
                'is_free' => true,
                'price_in_diamonds' => 0,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Genre;
use App\Models\Series;
use App\Models\Chapter;
use App\Models\ChapterImage;
use App\Models\DiamondPackage;
use App\Models\PaymentMethod;
use App\Models\Book;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Users
        User::create([
            'name' => 'Admin Administrator',
            'email' => 'admin@ecliptoon.uz',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'diamond_balance' => 9999,
        ]);

        User::create([
            'name' => 'Akbarali Xasanov',
            'email' => 'user@ecliptoon.uz',
            'password' => Hash::make('Password123!'),
            'role' => 'user',
            'diamond_balance' => 150,
        ]);

        // 2. Create Genres
        $genresData = [
            ['name' => 'Jangari (Action)', 'slug' => 'action'],
            ['name' => 'Komediya (Comedy)', 'slug' => 'comedy'],
            ['name' => 'Romantika (Romance)', 'slug' => 'romance'],
            ['name' => 'Fantastika (Fantasy)', 'slug' => 'fantasy'],
            ['name' => 'Drama (Drama)', 'slug' => 'drama'],
            ['name' => 'Sarguzasht (Adventure)', 'slug' => 'adventure'],
            ['name' => 'Kundalik hayot (Slice of Life)', 'slug' => 'slice-of-life'],
        ];

        $genres = [];
        foreach ($genresData as $g) {
            $genres[$g['slug']] = Genre::create($g);
        }

        // 3. Create Diamond Packages (Diamond Packages)
        DiamondPackage::create([
            'name' => '100 Olmos',
            'diamond_amount' => 100,
            'price' => 15000,
            'badge_text' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        DiamondPackage::create([
            'name' => '300 Olmos',
            'diamond_amount' => 300,
            'price' => 40000,
            'badge_text' => 'Eng Ommabop',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        DiamondPackage::create([
            'name' => '1000 Olmos',
            'diamond_amount' => 1000,
            'price' => 120000,
            'badge_text' => 'Foydali',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // 4. Create Payment Methods
        PaymentMethod::create([
            'card_number' => '8600123456789012',
            'card_holder_name' => 'ECLIPTION PAYMENTS',
            'bank_name' => 'Tenge Bank (Uzcard)',
            'is_active' => true,
        ]);

        PaymentMethod::create([
            'card_number' => '9860987654321098',
            'card_holder_name' => 'ECLIPTION SUPPORT',
            'bank_name' => 'Anorbank (Humo)',
            'is_active' => true,
        ]);

        // 5. Create Series
        $series1 = Series::create([
            'title' => 'Yolg\'iz darajani ko\'tarish (Solo Leveling)',
            'slug' => 'solo-leveling',
            'alternative_titles' => json_encode(['Only I Level Up', 'Na Honjaman Level Up']),
            'description' => '10 yil muqaddam dunyo va maxluqlar dunyosini bog\'lovchi Darvoza ochildi. Oddiy insonlar orasida g\'ayritabiiy kuchga ega bo\'lgan Ovchilar paydo bo\'ldi. Sung Jin-Woo eng quyi E-darajali ovchi bo\'lib, omon qolish uchun kurashadi. Ammo bir kuni u dahshatli qo\'shaloq daxmaga duch keladi va undan omon qolib, sirli tizim yordamida yolg\'iz o\'zi darajasini oshirish imkoniyatiga ega bo\'ladi.',
            'cover_image' => 'series/solo_leveling_cover.webp',
            'type' => 'manhwa',
            'status' => 'ongoing',
            'is_mature' => false,
            'is_pinned' => true,
            'views_count' => 12450,
        ]);
        $series1->genres()->attach([$genres['action']->id, $genres['fantasy']->id, $genres['adventure']->id]);

        $series2 = Series::create([
            'title' => 'Demontlarni kesuvchi tig\' (Demon Slayer)',
            'slug' => 'demon-slayer',
            'alternative_titles' => json_encode(['Kimetsu no Yaiba', 'Blade of Demon Destruction']),
            'description' => 'Tanjirou Kamado mehribon va aqlli bola bo\'lib, oilasi bilan tog\'da ko\'mir sotib kun kechiradi. Ammo bir kuni u uyiga qaytganida butun oilasini demonlar o\'ldirganini va faqat singlisi Nezuko tirik qolganini ko\'radi, lekin u ham demonga aylangan edi. Tanjirou singlisini yana insonga aylantirish va oilasini o\'ldirgan demonlardan o\'ch olish uchun Demon Slayers safiga qo\'shiladi.',
            'cover_image' => 'series/demon_slayer_cover.webp',
            'type' => 'manga',
            'status' => 'completed',
            'is_mature' => false,
            'is_pinned' => true,
            'views_count' => 8420,
        ]);
        $series2->genres()->attach([$genres['action']->id, $genres['fantasy']->id, $genres['drama']->id]);

        $series3 = Series::create([
            'title' => 'Jang san\'ati cho\'qqisi (Martial Peak)',
            'slug' => 'martial-peak',
            'alternative_titles' => json_encode(['Wu Lian Dian Feng']),
            'description' => 'Jang san\'ati cho\'qqisiga sayohat yolg\'iz va uzoq davom etadi. Qiyinchiliklar oldida omon qolish uchun kuchli iroda kerak. Yang Kai Lingxiao mazhabining oddiy tozalovchisi bo\'lib, tasodifan sirli qora kitobni topib oladi. Bu qora kitob uning taqdirini butunlay o\'zgartiradi va uni jang san\'ati dunyosining cho\'qqisiga olib chiqadi.',
            'cover_image' => 'series/martial_peak_cover.webp',
            'type' => 'manhua',
            'status' => 'ongoing',
            'is_mature' => false,
            'is_pinned' => false,
            'views_count' => 3120,
        ]);
        $series3->genres()->attach([$genres['fantasy']->id, $genres['adventure']->id]);

        $series4 = Series::create([
            'title' => 'Qonli shirinliklar (Sweet Home)',
            'slug' => 'sweet-home',
            'alternative_titles' => json_encode(['Seuwiteu Hom']),
            'description' => 'Ota-onasidan ayrilgan va yolg\'iz yashovchi maktab o\'quvchisi Cha Hyun-su yangi kvartiraga ko\'chib o\'tadi. Ko\'p o\'tmay, dunyoda g\'alati hodisalar sodir bo\'la boshlaydi: odamlar o\'zlarining ichki nafs va orzulariga qarab dahshatli maxluqlarga aylanishadi. U omon qolish uchun qo\'shnilari bilan birlashishga majbur bo\'ladi.',
            'cover_image' => 'series/sweet_home_cover.webp',
            'type' => 'manhwa',
            'status' => 'completed',
            'is_mature' => true,
            'is_pinned' => false,
            'views_count' => 5030,
        ]);
        $series4->genres()->attach([$genres['drama']->id, $genres['action']->id]);

        // 6. Chapters & images for Series 1 (Solo Leveling)
        for ($i = 1; $i <= 5; $i++) {
            $isFree = $i <= 2;
            $price = $isFree ? 0 : 10;
            $chapter = Chapter::create([
                'series_id' => $series1->id,
                'chapter_number' => $i,
                'title' => "Darvoza ortidagi sir - Bob $i",
                'is_free' => $isFree,
                'price_in_diamonds' => $price,
                'published_at' => now()->subDays(6 - $i),
                'views_count' => 100 * (10 - $i),
            ]);

            // Create 4 mock pages per chapter
            for ($p = 1; $p <= 4; $p++) {
                ChapterImage::create([
                    'chapter_id' => $chapter->id,
                    'image_path' => "chapters/mock_webtoon_page.webp",
                    'order' => $p,
                ]);
            }
        }

        // Chapters & images for Series 2 (Demon Slayer)
        for ($i = 1; $i <= 3; $i++) {
            $isFree = $i <= 2;
            $price = $isFree ? 0 : 12;
            $chapter = Chapter::create([
                'series_id' => $series2->id,
                'chapter_number' => $i,
                'title' => "Kamado oilasining fojiasi - Bob $i",
                'is_free' => $isFree,
                'price_in_diamonds' => $price,
                'published_at' => now()->subDays(4 - $i),
                'views_count' => 80 * (6 - $i),
            ]);

            for ($p = 1; $p <= 4; $p++) {
                ChapterImage::create([
                    'chapter_id' => $chapter->id,
                    'image_path' => "chapters/mock_webtoon_page.webp",
                    'order' => $p,
                ]);
            }
        }

        // 7. Create Books for Shop
        Book::create([
            'title' => 'Yolg\'iz darajani ko\'tarish - 1-jild (Solo Leveling Vol. 1)',
            'description' => 'Mashhur Solo Leveling manhvasining o\'zbek tilidagi to\'liq rangli va yuqori sifatli qog\'ozda chop etilgan birinchi jildi.',
            'price' => 15,
            'stock' => 10,
            'cover_path' => null,
        ]);

        Book::create([
            'title' => 'Demontlarni kesuvchi tig\' - 1-jild (Demon Slayer Vol. 1)',
            'description' => 'Kamado Tanjirou sarguzashtlarining boshlanishi. Yaponiya sifat standartlari asosida chop etilgan manga kitobi.',
            'price' => 12,
            'stock' => 5,
            'cover_path' => null,
        ]);

        Book::create([
            'title' => 'Jang san\'ati cho\'qqisi - 1-jild (Martial Peak Vol. 1)',
            'description' => 'Yang Kai sarguzashtlarining muqaddimasi. Jang san\'ati cho\'qqisiga sayohat kitob ko\'rinishida.',
            'price' => 20,
            'stock' => 15,
            'cover_path' => null,
        ]);

        Book::create([
            'title' => 'Qonli shirinliklar - 1-jild (Sweet Home Vol. 1)',
            'description' => 'Nafs va orzular dushmaniga aylangan maxluqlarga qarshi kurash. Mashhur Sweet Home manhvasining o\'zbekcha nashri.',
            'price' => 18,
            'stock' => 0, // Out of stock to test UI states
            'cover_path' => null,
        ]);
    }
}

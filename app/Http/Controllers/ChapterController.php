<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ChapterPurchase;
use App\Models\DiamondTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    /**
     * Get chapter details + images if free or purchased.
     */
    public function show($id, Request $request)
    {
        $chapter = Chapter::with(['series'])->findOrFail($id);
        $user = $request->user('sanctum');

        // 1. Sequential reading check
        if (!$user || !in_array($user->role, ['admin', 'moderator'])) {
            $prevChapter = Chapter::where('series_id', $chapter->series_id)
                ->where('chapter_number', '<', $chapter->chapter_number)
                ->orderBy('chapter_number', 'desc')
                ->first();

            if ($prevChapter) {
                if (!$user) {
                    return response()->json([
                        'message' => 'Ushbu bobni o\'qishdan oldin avvalgi bobni o\'qishingiz kerak. Iltimos, hisobingizga kiring.',
                        'code' => 'sequential_locked'
                    ], 403);
                }

                $hasReadPrev = \App\Models\ChapterRead::where('user_id', $user->id)->where('chapter_id', $prevChapter->id)->exists()
                    || \App\Models\ChapterPurchase::where('user_id', $user->id)->where('chapter_id', $prevChapter->id)->exists();

                if (!$hasReadPrev) {
                    return response()->json([
                        'message' => 'Ushbu bobni o\'qishdan oldin avvalgi bobni o\'qishingiz kerak.',
                        'code' => 'sequential_locked',
                        'prev_chapter_id' => $prevChapter->id
                    ], 403);
                }
            }
        }

        // Increment views
        $chapter->increment('views_count');

        $isUnlocked = false;

        if ($chapter->is_free) {
            $isUnlocked = true;
        } elseif ($user) {
            // Check if user is admin or moderator (unlocked automatically)
            if (in_array($user->role, ['admin', 'moderator'])) {
                $isUnlocked = true;
            } else {
                // Check if user purchased the chapter
                $isUnlocked = ChapterPurchase::where('user_id', $user->id)
                    ->where('chapter_id', $chapter->id)
                    ->exists();
            }
        }

        // 2. Log read progression if unlocked
        if ($isUnlocked && $user) {
            \App\Models\ChapterRead::firstOrCreate([
                'user_id' => $user->id,
                'series_id' => $chapter->series_id,
                'chapter_id' => $chapter->id
            ]);
        }

        // Prepare response
        $response = [
            'id' => $chapter->id,
            'series_id' => $chapter->series_id,
            'series_title' => $chapter->series->title,
            'series_slug' => $chapter->series->slug,
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'is_free' => $chapter->is_free,
            'price_in_diamonds' => $chapter->price_in_diamonds,
            'is_locked' => !$isUnlocked,
            'published_at' => $chapter->published_at,
        ];

        if ($isUnlocked) {
            if ($chapter->pdf_path) {
                $response['pdf_url'] = asset('storage/' . $chapter->pdf_path);
            } else {
                // Fallback to images for older chapters
                $response['images'] = $chapter->images()->orderBy('order', 'asc')->get()->map(function($img) {
                    return [
                        'id' => $img->id,
                        'image_url' => asset('storage/' . $img->image_path),
                        'order' => $img->order
                    ];
                });
            }
        }

        return response()->json($response);
    }

    /**
     * Purchase a chapter using diamonds.
     */
    public function purchase($id, Request $request)
    {
        $user = $request->user(); // Fully authenticated
        $chapter = Chapter::findOrFail($id);

        if ($chapter->is_free) {
            return response()->json([
                'message' => 'Bu bob bepul, uni sotib olish shart emas.' // "This chapter is free, no need to purchase it."
            ], 400);
        }

        // Check if already purchased
        $alreadyPurchased = ChapterPurchase::where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();

        if ($alreadyPurchased) {
            return response()->json([
                'message' => 'Siz ushbu bobni allaqachon sotib olgansiz.' // "You have already purchased this chapter."
            ]);
        }

        // Check balance
        if ($user->diamond_balance < $chapter->price_in_diamonds) {
            return response()->json([
                'message' => 'Balansingizda olmoslar yetarli emas. Iltimos, hisobingizni to\'ldiring.' // "Insufficient diamonds. Please top up your balance."
            ], 403);
        }

        // Run transaction
        DB::transaction(function () use ($user, $chapter) {
            // Deduct balance
            $user->diamond_balance -= $chapter->price_in_diamonds;
            $user->save();

            // Create purchase record
            ChapterPurchase::create([
                'user_id' => $user->id,
                'chapter_id' => $chapter->id,
                'diamonds_spent' => $chapter->price_in_diamonds,
            ]);

            // Log transaction
            DiamondTransaction::create([
                'user_id' => $user->id,
                'type' => 'purchase',
                'amount' => -$chapter->price_in_diamonds,
                'reference_type' => 'Chapter',
                'reference_id' => $chapter->id,
                'balance_after' => $user->diamond_balance,
            ]);
        });

        return response()->json([
            'message' => 'Bob muvaffaqiyatli xarid qilindi.', // "Chapter successfully purchased."
            'diamond_balance' => $user->fresh()->diamond_balance
        ]);
    }
}

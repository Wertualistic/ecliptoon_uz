<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\NovelChapter;
use App\Models\ChapterPurchase;
use App\Models\NovelPurchase;
use App\Models\DiamondTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    /**
     * Get Manhwa chapter details + images if free or purchased.
     */
    public function show($id, Request $request)
    {
        $user = $request->user('sanctum');
        $chapter = Chapter::with(['series'])->findOrFail($id);
        $chapter->increment('views_count');
        $isUnlocked = false;
        $isFree = $chapter->is_free;

        if ($isFree) {
            $isUnlocked = true;
        } elseif ($user) {
            if (in_array($user->role, ['admin', 'moderator'])) {
                $isUnlocked = true;
            } else {
                $isUnlocked = ChapterPurchase::where('user_id', $user->id)
                    ->where('chapter_id', $chapter->id)
                    ->exists();
            }
        }

        if ($isUnlocked && $user) {
            \App\Models\ChapterRead::firstOrCreate([
                'user_id' => $user->id,
                'series_id' => $chapter->series_id,
                'chapter_id' => $chapter->id
            ]);
        }

        $response = [
            'id' => $chapter->id,
            'series_id' => $chapter->series_id,
            'series_title' => $chapter->series->title,
            'series_slug' => $chapter->series->slug,
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'is_free' => $isFree,
            'price_in_diamonds' => $chapter->price_in_diamonds,
            'price_in_uzs' => $chapter->price_in_uzs,
            'is_locked' => !$isUnlocked,
            'published_at' => $chapter->published_at,
        ];

        if ($isUnlocked) {
            $pagesArray = is_string($chapter->pages) ? json_decode($chapter->pages, true) : $chapter->pages;
            if (!empty($pagesArray)) {
                $response['pages'] = array_map(function($page) {
                    return asset('storage/' . $page);
                }, $pagesArray);
            } elseif ($chapter->pdf_path) {
                $response['pdf_url'] = asset('storage/' . $chapter->pdf_path);
            } else {
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
     * Get dedicated Novel Chapter details + text content if free or purchased.
     */
    public function showNovelChapter($id, Request $request)
    {
        $user = $request->user('sanctum');
        $novelChapter = NovelChapter::with(['novel'])->findOrFail($id);
        $isUnlocked = false;
        $isFree = ($novelChapter->is_free || $novelChapter->price_in_uzs == 0);

        if ($isFree) {
            $isUnlocked = true;
        } elseif ($user) {
            if (in_array($user->role, ['admin', 'moderator']) || $novelChapter->novel->creator_id === $user->id) {
                $isUnlocked = true;
            } else {
                $isUnlocked = NovelPurchase::where('user_id', $user->id)
                    ->where('novel_chapter_id', $novelChapter->id)
                    ->where('status', 'approved')
                    ->exists();
            }
        }

        $response = [
            'id' => $novelChapter->id,
            'novel_id' => $novelChapter->novel_id,
            'novel_title' => $novelChapter->novel->title,
            'novel_slug' => $novelChapter->novel->slug,
            'chapter_number' => $novelChapter->chapter_number,
            'title' => $novelChapter->title,
            'is_free' => $isFree,
            'price_in_uzs' => $novelChapter->price_in_uzs,
            'is_locked' => !$isUnlocked,
            'published_at' => $novelChapter->published_at,
        ];

        if ($isUnlocked) {
            $response['content_text'] = $novelChapter->content_text;
        }

        return response()->json($response);
    }

    /**
     * Purchase a chapter using diamonds (Manhwas only).
     */
    public function purchase($id, Request $request)
    {
        $user = $request->user();
        $chapter = Chapter::findOrFail($id);

        if ($chapter->is_free) {
            return response()->json([
                'message' => 'Bu bob bepul, uni sotib olish shart emas.'
            ], 400);
        }

        $alreadyPurchased = ChapterPurchase::where('user_id', $user->id)
            ->where('chapter_id', $chapter->id)
            ->exists();

        if ($alreadyPurchased) {
            return response()->json([
                'message' => 'Siz ushbu bobni allaqachon sotib olgansiz.'
            ]);
        }

        if ($user->diamond_balance < $chapter->price_in_diamonds) {
            return response()->json([
                'message' => 'Balansingizda olmoslar yetarli emas. Iltimos, hisobingizni to\'ldiring.'
            ], 403);
        }

        DB::transaction(function () use ($user, $chapter) {
            $user->diamond_balance -= $chapter->price_in_diamonds;
            $user->save();

            ChapterPurchase::create([
                'user_id' => $user->id,
                'chapter_id' => $chapter->id,
                'diamonds_spent' => $chapter->price_in_diamonds,
            ]);

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
            'message' => 'Bob muvaffaqiyatli xarid qilindi.',
            'diamond_balance' => $user->fresh()->diamond_balance
        ]);
    }
}

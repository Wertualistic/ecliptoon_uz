<?php

namespace App\Http\Controllers;

use App\Models\Series;
use App\Models\SeriesRating;
use App\Models\SeriesLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingLikeController extends Controller
{
    /**
     * Rate a series (1 to 5 stars).
     */
    public function rate($id, Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        $user = $request->user();
        $series = Series::findOrFail($id);

        SeriesRating::updateOrCreate(
            [
                'user_id' => $user->id,
                'series_id' => $series->id,
            ],
            [
                'rating' => $request->rating,
            ]
        );

        // Recalculate average rating and count
        $stats = SeriesRating::where('series_id', $series->id)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(id) as count_ratings')
            ->first();

        $series->rating_avg = round($stats->avg_rating, 2);
        $series->rating_count = $stats->count_ratings;
        $series->save();

        return response()->json([
            'message' => 'Baholaganingiz uchun rahmat!', // "Thank you for rating!"
            'rating_avg' => $series->rating_avg,
            'rating_count' => $series->rating_count,
        ]);
    }

    /**
     * Toggle like state for a series.
     */
    public function toggleLike($id, Request $request)
    {
        $user = $request->user();
        $series = Series::findOrFail($id);

        $like = SeriesLike::where('user_id', $user->id)
            ->where('series_id', $series->id)
            ->first();

        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            SeriesLike::create([
                'user_id' => $user->id,
                'series_id' => $series->id,
            ]);
            $isLiked = true;
        }

        // Recalculate likes count
        $series->likes_count = SeriesLike::where('series_id', $series->id)->count();
        $series->save();

        return response()->json([
            'message' => $isLiked ? 'Sizga yoqdi.' : 'Sizga yoqish bekor qilindi.',
            'is_liked' => $isLiked,
            'likes_count' => $series->likes_count,
        ]);
    }

    /**
     * Check if a series is rated and liked by the authenticated user.
     */
    public function checkStatus($id, Request $request)
    {
        $user = $request->user('sanctum');
        if (!$user) {
            return response()->json([
                'is_liked' => false,
                'user_rating' => 0,
            ]);
        }

        $liked = SeriesLike::where('user_id', $user->id)
            ->where('series_id', $id)
            ->exists();

        $rating = SeriesRating::where('user_id', $user->id)
            ->where('series_id', $id)
            ->first();

        return response()->json([
            'is_liked' => $liked,
            'user_rating' => $rating ? $rating->rating : 0,
        ]);
    }
}

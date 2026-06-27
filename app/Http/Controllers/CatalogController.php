<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Series;
use App\Models\Chapter;
use App\Models\Novel;
use App\Models\NovelChapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /**
     * List series with filters, sorting, and search.
     */
    public function series(Request $request)
    {
        if ($request->has('type') && $request->type === 'novel') {
            $query = Novel::with('genres');

            if ($request->has('genre') && $request->genre) {
                $query->whereHas('genres', function ($q) use ($request) {
                    $q->where('slug', $request->genre);
                });
            }

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('alternative_titles', 'like', "%{$search}%");
                });
            }

            $sort = $request->get('sort', 'popularity');
            if ($sort === 'newest') {
                $query->orderBy('created_at', 'desc');
            } elseif ($sort === 'alphabetical') {
                $query->orderBy('title', 'asc');
            } else {
                $query->orderBy('views_count', 'desc');
            }

            return response()->json($query->paginate(12));
        }

        $query = Series::with('genres');

        if ($request->has('genre') && $request->genre) {
            $query->whereHas('genres', function ($q) use ($request) {
                $q->where('slug', $request->genre);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        } else {
            $query->where('type', '!=', 'novel');
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('alternative_titles', 'like', "%{$search}%");
            });
        }

        $sort = $request->get('sort', 'popularity');
        if ($sort === 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($sort === 'alphabetical') {
            $query->orderBy('title', 'asc');
        } else {
            $query->orderBy('views_count', 'desc');
        }

        return response()->json($query->paginate(12));
    }

    /**
     * Get single series/novel details.
     */
    public function showSeries($slug)
    {
        $series = Series::with(['genres', 'sponsors', 'translator'])->where('slug', $slug)->first();
        if ($series) {
            $series->increment('views_count');
            return response()->json($series);
        }

        $novel = Novel::with(['genres', 'creator'])->where('slug', $slug)->firstOrFail();
        $novel->increment('views_count');
        
        // Map creator to translator for frontend compatibility
        $novelData = $novel->toArray();
        $novelData['translator'] = $novel->creator;
        $novelData['type'] = 'novel';
        
        return response()->json($novelData);
    }

    /**
     * Get chapters of a series/novel.
     */
    public function seriesChapters($slug)
    {
        $series = Series::where('slug', $slug)->first();
        if ($series) {
            $chapters = $series->chapters()->orderBy('chapter_number', 'desc')->get();
            return response()->json($chapters);
        }

        $novel = Novel::where('slug', $slug)->firstOrFail();
        $chapters = $novel->chapters()->orderBy('chapter_number', 'desc')->get();

        return response()->json($chapters);
    }

    /**
     * Get list of genres.
     */
    public function genres()
    {
        return response()->json(Genre::orderBy('name', 'asc')->get());
    }

    /**
     * Get trending series.
     */
    public function trending()
    {
        $trendingSeries = Series::with('genres')
            ->orderBy('views_count', 'desc')
            ->limit(6)
            ->get();

        $trendingNovels = Novel::with('genres')
            ->orderBy('views_count', 'desc')
            ->limit(6)
            ->get();

        $combined = $trendingSeries->concat($trendingNovels)->sortByDesc('views_count')->take(10)->values();

        return response()->json($combined);
    }

    /**
     * Get latest updates (recently published chapters).
     */
    public function latestUpdates()
    {
        $chapters = Chapter::with('series')
            ->orderBy('published_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($chapters);
    }

    /**
     * Get completed series.
     */
    public function completed()
    {
        $completed = Series::with('genres')
            ->where('status', 'completed')
            ->orderBy('views_count', 'desc')
            ->limit(6)
            ->get();

        return response()->json($completed);
    }

    /**
     * Get the rankings leaderboard for users.
     */
    public function leaderboard()
    {
        $topReaders = DB::table('chapter_reads')
            ->join('users', 'chapter_reads.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.avatar_url', DB::raw('COUNT(chapter_reads.id) as chapters_count'))
            ->whereDate('chapter_reads.created_at', now()->toDateString())
            ->where('users.role', '=', 'user')
            ->where('users.is_banned', '=', false)
            ->groupBy('users.id', 'users.name', 'users.avatar_url')
            ->orderBy('chapters_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'avatar_url' => $row->avatar_url ? asset('storage/' . $row->avatar_url) : null,
                    'score' => (int)$row->chapters_count,
                ];
            });

        $topBuyers = DB::table('diamond_transactions')
            ->join('users', 'diamond_transactions.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.avatar_url', DB::raw('SUM(diamond_transactions.amount) as total_diamonds'))
            ->where('diamond_transactions.type', '=', 'topup')
            ->where('users.role', '=', 'user')
            ->where('users.is_banned', '=', false)
            ->groupBy('users.id', 'users.name', 'users.avatar_url')
            ->orderBy('total_diamonds', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'avatar_url' => $row->avatar_url ? asset('storage/' . $row->avatar_url) : null,
                    'score' => (int)$row->total_diamonds,
                ];
            });

        return response()->json([
            'top_readers' => $topReaders,
            'top_buyers' => $topBuyers,
        ]);
    }

    /**
     * Get series marked to be shown in the home page slider.
     */
    public function slider()
    {
        $sliderSeries = Series::with('genres')
            ->where('is_slider', true)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($sliderSeries->isEmpty()) {
            $sliderSeries = Series::with('genres')
                ->orderBy('views_count', 'desc')
                ->limit(4)
                ->get();
        }

        return response()->json($sliderSeries);
    }
}

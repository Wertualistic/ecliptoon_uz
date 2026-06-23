<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Series;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /**
     * List series with filters, sorting, and search.
     */
    public function series(Request $request)
    {
        $query = Series::with('genres');

        // Filter by Genre (slug)
        if ($request->has('genre') && $request->genre) {
            $query->whereHas('genres', function ($q) use ($request) {
                $q->where('slug', $request->genre);
            });
        }

        // Filter by Status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by Type
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        // Search by Title or Alternative Titles
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('alternative_titles', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'popularity');
        if ($sort === 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($sort === 'alphabetical') {
            $query->orderBy('title', 'asc');
        } else { // default 'popularity'
            $query->orderBy('views_count', 'desc');
        }

        return response()->json($query->paginate(12));
    }

    /**
     * Get single series details.
     */
    public function showSeries($slug)
    {
        $series = Series::with(['genres', 'sponsors', 'translator'])->where('slug', $slug)->firstOrFail();
        
        // Increment view count
        $series->increment('views_count');

        return response()->json($series);
    }

    /**
     * Get chapters of a series.
     */
    public function seriesChapters($slug)
    {
        $series = Series::where('slug', $slug)->firstOrFail();
        $chapters = $series->chapters()->orderBy('chapter_number', 'desc')->get();

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
        $trending = Series::with('genres')
            ->orderBy('views_count', 'desc')
            ->limit(6)
            ->get();

        return response()->json($trending);
    }

    /**
     * Get latest updates (recently published chapters).
     */
    public function latestUpdates()
    {
        // Load latest chapters with their series
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
        // 1. Top Daily Readers (unique chapter reads today by standard active users)
        $topReaders = DB::table('chapter_reads')
            ->join('users', 'chapter_reads.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.avatar', DB::raw('COUNT(chapter_reads.id) as chapters_count'))
            ->whereDate('chapter_reads.created_at', now()->toDateString())
            ->where('users.role', '=', 'user')
            ->where('users.is_banned', '=', false)
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderBy('chapters_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'avatar_url' => $row->avatar ? asset('storage/' . $row->avatar) : null,
                    'score' => (int)$row->chapters_count,
                ];
            });

        // 2. Top Diamond Buyers (total diamonds purchased by standard active users)
        $topBuyers = DB::table('diamond_transactions')
            ->join('users', 'diamond_transactions.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.avatar', DB::raw('SUM(diamond_transactions.amount) as total_diamonds'))
            ->where('diamond_transactions.type', '=', 'topup')
            ->where('users.role', '=', 'user')
            ->where('users.is_banned', '=', false)
            ->groupBy('users.id', 'users.name', 'users.avatar')
            ->orderBy('total_diamonds', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'avatar_url' => $row->avatar ? asset('storage/' . $row->avatar) : null,
                    'score' => (int)$row->total_diamonds,
                ];
            });

        return response()->json([
            'top_readers' => $topReaders,
            'top_buyers' => $topBuyers,
        ]);
    }
}

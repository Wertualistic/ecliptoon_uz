<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Series;
use App\Models\Notification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get user bookmarks list.
     */
    public function bookmarks(Request $request)
    {
        $user = $request->user();
        $bookmarks = Bookmark::with(['series.genres', 'novel.genres'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($b) {
                return $b->series ?: $b->novel;
            })
            ->filter();

        return response()->json($bookmarks->values());
    }

    /**
     * Add series or novel to bookmarks.
     */
    public function addBookmark(Request $request)
    {
        $request->validate([
            'series_id' => 'nullable|exists:series,id',
            'novel_id' => 'nullable|exists:novels,id',
        ]);

        $user = $request->user();

        if ($request->novel_id) {
            $bookmark = Bookmark::firstOrCreate([
                'user_id' => $user->id,
                'novel_id' => $request->novel_id,
            ]);
        } else {
            $bookmark = Bookmark::firstOrCreate([
                'user_id' => $user->id,
                'series_id' => $request->series_id,
            ]);
        }

        return response()->json([
            'message' => 'Kutubxonaga muvaffaqiyatli qo\'shildi.',
            'bookmark' => $bookmark
        ]);
    }

    /**
     * Remove series or novel from bookmarks.
     */
    public function removeBookmark($id, Request $request)
    {
        $user = $request->user();

        $deleted = Bookmark::where('user_id', $user->id)
            ->where(function($q) use ($id) {
                $q->where('series_id', $id)
                  ->orWhere('novel_id', $id)
                  ->orWhere('id', $id);
            })
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Kutubxonadan o\'chirildi.'
            ]);
        }

        return response()->json([
            'message' => 'Bookmark topilmadi.'
        ], 404);
    }

    /**
     * Get user notifications list.
     */
    public function notifications(Request $request)
    {
        $notifications = $request->user()->notifications;
        return response()->json($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id, Request $request)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Xabar o\'qildi deb belgilandi.' // "Message marked as read."
        ]);
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()->update(['is_read' => true]);

        return response()->json([
            'message' => 'Barcha xabarlar o\'qildi deb belgilandi.' // "All messages marked as read."
        ]);
    }
}

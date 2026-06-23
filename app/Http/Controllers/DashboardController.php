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
        $bookmarks = Bookmark::with(['series.genres'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($b) {
                return $b->series;
            });

        return response()->json($bookmarks);
    }

    /**
     * Add series to bookmarks.
     */
    public function addBookmark(Request $request)
    {
        $request->validate([
            'series_id' => 'required|exists:series,id',
        ]);

        $user = $request->user();

        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $user->id,
            'series_id' => $request->series_id,
        ]);

        return response()->json([
            'message' => 'Kutubxonaga muvaffaqiyatli qo\'shildi.', // "Successfully added to library."
            'bookmark' => $bookmark
        ]);
    }

    /**
     * Remove series from bookmarks.
     */
    public function removeBookmark($seriesId, Request $request)
    {
        $user = $request->user();

        // Check if user passed the bookmark ID or series ID. We will search by both to be safe.
        $deleted = Bookmark::where('user_id', $user->id)
            ->where(function($q) use ($seriesId) {
                $q->where('series_id', $seriesId)
                  ->orWhere('id', $seriesId);
            })
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Kutubxonadan o\'chirildi.' // "Removed from library."
            ]);
        }

        return response()->json([
            'message' => 'Bookmark topilmadi.' // "Bookmark not found."
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

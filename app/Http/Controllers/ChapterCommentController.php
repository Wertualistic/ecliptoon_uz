<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\NovelChapter;
use App\Models\ChapterComment;
use Illuminate\Http\Request;

class ChapterCommentController extends Controller
{
    /**
     * List comments for a Manhwa chapter.
     */
    public function index($chapterId)
    {
        $chapter = Chapter::findOrFail($chapterId);

        $comments = ChapterComment::with(['user:id,name,avatar'])
            ->where('chapter_id', $chapter->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'user_name' => $comment->user->name,
                    'user_avatar' => $comment->user->avatar ? asset('storage/' . $comment->user->avatar) : null,
                    'content' => $comment->content,
                    'image_url' => $comment->image_path ? asset('storage/' . $comment->image_path) : null,
                    'created_at' => $comment->created_at->toISOString(),
                ];
            });

        return response()->json($comments);
    }

    /**
     * Store a comment for a Manhwa chapter.
     */
    public function store($chapterId, Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'image' => 'nullable|image|max:5120',
        ]);

        $chapter = Chapter::findOrFail($chapterId);
        $user = $request->user();

        if ($user->is_banned) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan, izoh qoldira olmaysiz.'
            ], 403);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('comments', 'public');
        }

        $comment = ChapterComment::create([
            'user_id' => $user->id,
            'chapter_id' => $chapter->id,
            'content' => $request->content,
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Izoh muvaffaqiyatli qo\'shildi.',
            'comment' => [
                'id' => $comment->id,
                'user_id' => $comment->user_id,
                'user_name' => $user->name,
                'user_avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'content' => $comment->content,
                'image_url' => $comment->image_path ? asset('storage/' . $comment->image_path) : null,
                'created_at' => $comment->created_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * List comments for a Novel chapter.
     */
    public function novelComments($chapterId)
    {
        $novelChapter = NovelChapter::findOrFail($chapterId);

        $comments = ChapterComment::with(['user:id,name,avatar'])
            ->where('novel_chapter_id', $novelChapter->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'user_id' => $comment->user_id,
                    'user_name' => $comment->user->name,
                    'user_avatar' => $comment->user->avatar ? asset('storage/' . $comment->user->avatar) : null,
                    'content' => $comment->content,
                    'image_url' => $comment->image_path ? asset('storage/' . $comment->image_path) : null,
                    'created_at' => $comment->created_at->toISOString(),
                ];
            });

        return response()->json($comments);
    }

    /**
     * Store a comment for a Novel chapter.
     */
    public function storeNovelComment($chapterId, Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'image' => 'nullable|image|max:5120',
        ]);

        $novelChapter = NovelChapter::findOrFail($chapterId);
        $user = $request->user();

        if ($user->is_banned) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan, izoh qoldira olmaysiz.'
            ], 403);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('comments', 'public');
        }

        $comment = ChapterComment::create([
            'user_id' => $user->id,
            'novel_chapter_id' => $novelChapter->id,
            'content' => $request->content,
            'image_path' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Izoh muvaffaqiyatli qo\'shildi.',
            'comment' => [
                'id' => $comment->id,
                'user_id' => $comment->user_id,
                'user_name' => $user->name,
                'user_avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'content' => $comment->content,
                'image_url' => $comment->image_path ? asset('storage/' . $comment->image_path) : null,
                'created_at' => $comment->created_at->toISOString(),
            ]
        ], 201);
    }

    /**
     * Delete a comment.
     */
    public function destroy($id, Request $request)
    {
        $comment = ChapterComment::findOrFail($id);
        $user = $request->user();

        if ($comment->user_id !== $user->id && !in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'message' => 'Ushbu amalni bajarish uchun sizda huquq yo\'q.'
            ], 403);
        }

        if ($comment->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($comment->image_path);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Izoh muvaffaqiyatli o\'chirildi.'
        ]);
    }
}

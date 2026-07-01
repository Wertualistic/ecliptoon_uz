<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\TranslatorApplication;

class TranslatorController extends Controller
{
    public function index()
    {
        $translators = User::whereIn('role', ['translator', 'novel_creator', 'admin'])
            ->withCount(['translatedSeries', 'createdNovels', 'followers'])
            ->orderBy('followers_count', 'desc')
            ->get();

        return response()->json($translators);
    }

    public function show($id, Request $request)
    {
        $translator = User::where('id', $id)
            ->whereIn('role', ['translator', 'novel_creator', 'admin'])
            ->withCount('followers')
            ->with([
                'translatedSeries' => function($query) {
                    $query->with('genres')->orderBy('created_at', 'desc');
                },
                'createdNovels' => function($query) {
                    $query->with('genres')->orderBy('created_at', 'desc');
                }
            ])
            ->firstOrFail();

        $isFollowing = false;
        if ($user = $request->user('sanctum')) {
            $isFollowing = $translator->followers()->where('user_id', $user->id)->exists();
        }

        $translatorArr = $translator->toArray();
        // Merge series and novels into projects for frontend rendering
        $seriesProjects = collect($translatorArr['translated_series'] ?? [])->map(function($item) {
            $item['type'] = $item['type'] ?? 'manhwa';
            return $item;
        });
        $novelProjects = collect($translatorArr['created_novels'] ?? [])->map(function($item) {
            $item['type'] = 'novel';
            return $item;
        });

        $translatorArr['projects'] = $seriesProjects->concat($novelProjects)->sortByDesc('created_at')->values()->all();
        $translatorArr['projects_count'] = count($translatorArr['projects']);

        return response()->json([
            'translator' => $translatorArr,
            'is_following' => $isFollowing
        ]);
    }

    public function toggleFollow($id, Request $request)
    {
        $user = $request->user();
        $translator = User::where('id', $id)->whereIn('role', ['translator', 'novel_creator', 'admin'])->firstOrFail();

        if ($translator->id === $user->id) {
            return response()->json(['message' => 'O\'zingizga obuna bo\'lolmaysiz.'], 400);
        }

        $isFollowing = $user->followingTranslators()->where('translator_id', $id)->exists();

        if ($isFollowing) {
            $user->followingTranslators()->detach($id);
            return response()->json(['message' => 'Obuna bekor qilindi.', 'is_following' => false]);
        } else {
            $user->followingTranslators()->attach($id);
            return response()->json(['message' => 'Obuna bo\'ldingiz.', 'is_following' => true]);
        }
    }

    public function apply(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'translator' || $user->role === 'admin') {
            return response()->json(['message' => 'Siz allaqachon tarjimon yoki adminsiz.'], 400);
        }

        if ($user->translatorApplication) {
            return response()->json(['message' => 'Sizning arizangiz allaqachon mavjud (' . $user->translatorApplication->status . ').'], 400);
        }

        TranslatorApplication::create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Arizangiz muvaffaqiyatli yuborildi!']);
    }
}

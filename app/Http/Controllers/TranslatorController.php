<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

use App\Models\TranslatorApplication;

class TranslatorController extends Controller
{
    public function index()
    {
        $translators = User::where('role', 'translator')
            ->withCount('translatedSeries')
            ->withCount('followers')
            ->orderBy('followers_count', 'desc')
            ->get();

        return response()->json($translators);
    }

    public function show($id, Request $request)
    {
        $translator = User::where('id', $id)
            ->where('role', 'translator')
            ->withCount('followers')
            ->with(['translatedSeries' => function($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->firstOrFail();

        $isFollowing = false;
        if ($user = $request->user('sanctum')) {
            $isFollowing = $translator->followers()->where('user_id', $user->id)->exists();
        }

        return response()->json([
            'translator' => $translator,
            'is_following' => $isFollowing
        ]);
    }

    public function toggleFollow($id, Request $request)
    {
        $user = $request->user();
        $translator = User::where('id', $id)->where('role', 'translator')->firstOrFail();

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

<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    private function checkAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user || ($user->role !== 'admin' && $user->role !== 'moderator')) {
            abort(403, 'Sizda bu amalni bajarish uchun huquq yo\'q.');
        }
    }

    public function index()
    {
        return response()->json(News::orderBy('created_at', 'desc')->get());
    }

    public function show($id)
    {
        return response()->json(News::findOrFail($id));
    }

    public function store(Request $request)
    {
        $this->checkAdmin($request);
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $news = new News();
        $news->title = $request->title;
        $news->content = $request->content;

        if ($request->hasFile('image')) {
            $news->image_url = $request->file('image')->store('news', 'public');
        }

        $news->save();

        return response()->json($news, 201);
    }

    public function update(Request $request, $id)
    {
        $this->checkAdmin($request);
        $news = News::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $news->title = $request->title;
        $news->content = $request->content;

        if ($request->hasFile('image')) {
            if ($news->image_url) {
                Storage::disk('public')->delete($news->image_url);
            }
            $news->image_url = $request->file('image')->store('news', 'public');
        }

        $news->save();

        return response()->json($news);
    }

    public function destroy(Request $request, $id)
    {
        $this->checkAdmin($request);
        $news = News::findOrFail($id);
        
        if ($news->image_url) {
            Storage::disk('public')->delete($news->image_url);
        }
        
        $news->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}

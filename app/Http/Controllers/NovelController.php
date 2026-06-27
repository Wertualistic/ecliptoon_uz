<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Novel;
use App\Models\NovelChapter;
use App\Models\PaymentMethod;
use App\Models\NovelCreatorApplication;
use App\Models\NovelPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NovelController extends Controller
{
    /**
     * Helper to verify if user is admin (Super Admin).
     */
    private function checkAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
    }

    /**
     * Helper to verify if user is a novel creator.
     */
    private function checkCreator(Request $request)
    {
        $user = $request->user();
        if (!$user || ($user->role !== 'novel_creator' && $user->role !== 'admin')) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
    }

    /* ==========================================
       1. CREATOR APPLICATIONS FLOW (USER/ADMIN)
       ========================================== */

    /**
     * Submit an application to become a Novel Creator.
     */
    public function apply(Request $request)
    {
        $user = $request->user();

        // Check if there is already a pending application
        $pending = NovelCreatorApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json([
                'message' => 'Sizda allaqachon ko\'rib chiqilayotgan ariza mavjud.'
            ], 400);
        }

        $request->validate([
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // max 10MB
            'user_note' => 'nullable|string|max:1000',
        ]);

        $path = $request->file('receipt_image')->store('creator_receipts', 'public');

        $application = NovelCreatorApplication::create([
            'user_id' => $user->id,
            'receipt_image_path' => $path,
            'user_note' => $request->user_note,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Arizangiz muvaffaqiyatli qabul qilindi va administrator tomonidan tez orada ko\'rib chiqiladi.',
            'application' => $application
        ], 201);
    }

    /**
     * Check application status for current user.
     */
    public function applicationStatus(Request $request)
    {
        $user = $request->user();
        $latestApplication = NovelCreatorApplication::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'novel_creator_expires_at' => $user->novel_creator_expires_at,
            ],
            'application' => $latestApplication ? [
                'id' => $latestApplication->id,
                'receipt_url' => asset('storage/' . $latestApplication->receipt_image_path),
                'user_note' => $latestApplication->user_note,
                'status' => $latestApplication->status,
                'admin_note' => $latestApplication->admin_note,
                'created_at' => $latestApplication->created_at,
            ] : null,
            'monthly_fee' => (int)\App\Models\Setting::get('novel_creator_monthly_fee', 50000)
        ]);
    }

    /**
     * List all creator applications (Admin only).
     */
    public function listApplications(Request $request)
    {
        $this->checkAdmin($request);

        $applications = NovelCreatorApplication::with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'user' => [
                        'id' => $app->user->id,
                        'name' => $app->user->name,
                        'email' => $app->user->email,
                    ],
                    'receipt_url' => asset('storage/' . $app->receipt_image_path),
                    'user_note' => $app->user_note,
                    'status' => $app->status,
                    'admin_note' => $app->admin_note,
                    'created_at' => $app->created_at,
                ];
            });

        return response()->json($applications);
    }

    /**
     * Approve creator application (Admin only).
     */
    public function approveApplication($id, Request $request)
    {
        $this->checkAdmin($request);

        $application = NovelCreatorApplication::findOrFail($id);
        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Ushbu ariza allaqachon ko\'rib chiqilgan.'], 400);
        }

        $application->status = 'approved';
        $application->save();

        // Update user role to novel_creator and extend subscription by 30 days
        $applicant = User::findOrFail($application->user_id);
        $applicant->role = 'novel_creator';

        if ($applicant->novel_creator_expires_at && Carbon::parse($applicant->novel_creator_expires_at)->isFuture()) {
            $applicant->novel_creator_expires_at = Carbon::parse($applicant->novel_creator_expires_at)->addDays(30);
        } else {
            $applicant->novel_creator_expires_at = now()->addDays(30);
        }

        $applicant->save();

        return response()->json([
            'message' => 'Ariza muvaffaqiyatli tasdiqlandi. Foydalanuvchiga Yozuvchi (Novel Creator) roli berildi.',
            'application' => $application
        ]);
    }

    /**
     * Reject creator application (Admin only).
     */
    public function rejectApplication($id, Request $request)
    {
        $this->checkAdmin($request);

        $request->validate([
            'admin_note' => 'required|string|max:1000'
        ]);

        $application = NovelCreatorApplication::findOrFail($id);
        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Ushbu ariza allaqachon ko\'rib chiqilgan.'], 400);
        }

        $application->status = 'rejected';
        $application->admin_note = $request->admin_note;
        $application->save();

        return response()->json([
            'message' => 'Ariza rad etildi.',
            'application' => $application
        ]);
    }

    /* ==========================================
       2. CREATOR DASHBOARD ANALYTICS & NOVELS CRUD
       ========================================== */

    /**
     * Creator stats.
     */
    public function creatorStats(Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $novelsQuery = Novel::query();
        if ($user->role !== 'admin') {
            $novelsQuery->where('creator_id', $user->id);
        }
        $novelIds = $novelsQuery->pluck('id')->toArray();

        $totalNovels = count($novelIds);
        $totalChapters = NovelChapter::whereIn('novel_id', $novelIds)->count();

        $totalRevenue = NovelPurchase::whereIn('novel_id', $novelIds)
            ->where('status', 'approved')
            ->sum('amount');

        $pendingPurchases = NovelPurchase::whereIn('novel_id', $novelIds)
            ->where('status', 'pending')
            ->count();

        $topNovels = Novel::whereIn('id', $novelIds)
            ->orderBy('views_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'total_novels' => $totalNovels,
            'total_chapters' => $totalChapters,
            'total_revenue' => (float)$totalRevenue,
            'pending_purchases_count' => $pendingPurchases,
            'top_novels' => $topNovels,
        ]);
    }

    /**
     * List creator's own novels.
     */
    public function listCreatorNovels(Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $query = Novel::with('genres');
        if ($user->role !== 'admin') {
            $query->where('creator_id', $user->id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a new novel.
     */
    public function storeCreatorNovel(Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:novels,slug',
            'alternative_titles' => 'nullable|array',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'required|in:ongoing,completed,paused,dropped',
            'is_mature' => 'nullable',
            'genres' => 'nullable|array',
            'genres.*' => 'exists:genres,id',
        ]);

        $slug = $request->slug ? Str::slug($request->slug) : Str::slug($request->title);
        $baseSlug = $slug;
        $counter = 1;
        while (Novel::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('series', 'public');
        }

        $novel = Novel::create([
            'title' => $request->title,
            'slug' => $slug,
            'alternative_titles' => $request->alternative_titles ? json_encode($request->alternative_titles) : json_encode([]),
            'description' => $request->description,
            'cover_image' => $coverPath,
            'status' => $request->status,
            'is_mature' => filter_var($request->is_mature, FILTER_VALIDATE_BOOLEAN),
            'creator_id' => $user->id,
        ]);

        if ($request->genres) {
            $novel->genres()->sync($request->genres);
        }

        return response()->json($novel->load('genres'), 201);
    }

    /**
     * Update a novel.
     */
    public function updateCreatorNovel($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $novel = Novel::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        if ($user->role !== 'admin' && $novel->creator_id !== $user->id) {
            abort(403, 'Sizda ushbu novelni tahrirlash huquqi yo\'q.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:novels,slug,' . $novel->id,
            'alternative_titles' => 'nullable|array',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240',
            'status' => 'required|in:ongoing,completed,paused,dropped',
            'is_mature' => 'nullable',
            'genres' => 'nullable|array',
            'genres.*' => 'exists:genres,id',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($novel->cover_image) {
                Storage::disk('public')->delete($novel->cover_image);
            }
            $novel->cover_image = $request->file('cover_image')->store('series', 'public');
        }

        $novel->title = $request->title;
        if ($request->slug && $request->slug !== $novel->slug) {
            $novel->slug = Str::slug($request->slug);
        }
        $novel->alternative_titles = $request->alternative_titles ? json_encode($request->alternative_titles) : json_encode([]);
        $novel->description = $request->description;
        $novel->status = $request->status;
        $novel->is_mature = filter_var($request->is_mature, FILTER_VALIDATE_BOOLEAN);
        $novel->save();

        if ($request->has('genres')) {
            $novel->genres()->sync($request->genres);
        }

        return response()->json($novel->load('genres'));
    }

    /**
     * Delete a novel.
     */
    public function deleteCreatorNovel($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $novel = Novel::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        if ($user->role !== 'admin' && $novel->creator_id !== $user->id) {
            abort(403);
        }

        if ($novel->cover_image) {
            Storage::disk('public')->delete($novel->cover_image);
        }

        $novel->delete();

        return response()->json(['message' => 'Novel muvaffaqiyatli o\'chirildi.']);
    }

    /**
     * Store chapter (novel creator).
     */
    public function storeCreatorChapter($novelId, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $novel = Novel::where('id', $novelId)->orWhere('slug', $novelId)->firstOrFail();
        if ($user->role !== 'admin' && $novel->creator_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'chapter_number' => 'required|numeric',
            'title' => 'nullable|string|max:255',
            'is_free' => 'required|boolean',
            'price_in_uzs' => 'nullable|numeric|min:0',
            'content_text' => 'required|string',
            'published_at' => 'nullable|date',
        ]);

        $chapter = NovelChapter::create([
            'novel_id' => $novel->id,
            'chapter_number' => $request->chapter_number,
            'title' => $request->title,
            'is_free' => $request->is_free,
            'price_in_uzs' => $request->is_free ? 0 : ($request->price_in_uzs ?? 0),
            'content_text' => $request->content_text,
            'published_at' => $request->published_at ?? now(),
        ]);

        return response()->json($chapter, 201);
    }

    /**
     * Update creator chapter.
     */
    public function updateCreatorChapter($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $chapter = NovelChapter::with('novel')->findOrFail($id);
        if ($user->role !== 'admin' && $chapter->novel->creator_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'chapter_number' => 'required|numeric',
            'title' => 'nullable|string|max:255',
            'is_free' => 'required|boolean',
            'price_in_uzs' => 'nullable|numeric|min:0',
            'content_text' => 'required|string',
            'published_at' => 'nullable|date',
        ]);

        $chapter->update([
            'chapter_number' => $request->chapter_number,
            'title' => $request->title,
            'is_free' => $request->is_free,
            'price_in_uzs' => $request->is_free ? 0 : ($request->price_in_uzs ?? 0),
            'content_text' => $request->content_text,
            'published_at' => $request->published_at ?? $chapter->published_at,
        ]);

        return response()->json($chapter);
    }

    /**
     * Delete creator chapter.
     */
    public function deleteCreatorChapter($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $chapter = NovelChapter::with('novel')->findOrFail($id);
        if ($user->role !== 'admin' && $chapter->novel->creator_id !== $user->id) {
            abort(403);
        }

        $chapter->delete();

        return response()->json(['message' => 'Bob muvaffaqiyatli o\'chirildi.']);
    }

    /* ==========================================
       3. USER PURCHASES VERIFICATION FLOW
       ========================================== */

    /**
     * List purchases/receipts sent to creator's cards.
     */
    public function listCreatorPurchases(Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $query = NovelPurchase::with(['user', 'novel', 'novelChapter', 'paymentMethod']);

        if ($user->role !== 'admin') {
            $query->whereHas('novel', function($q) use ($user) {
                $q->where('creator_id', $user->id);
            });
        }

        $purchases = $query->orderBy('created_at', 'desc')->get()->map(function($purchase) {
            $purchase->receipt_url = asset('storage/' . $purchase->receipt_image_path);
            return $purchase;
        });

        return response()->json($purchases);
    }

    /**
     * Approve a purchase receipt.
     */
    public function approvePurchase($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $purchase = NovelPurchase::findOrFail($id);
        $novel = Novel::where('id', $purchase->novel_id)->orWhere('id', $purchase->series_id)->first();

        if ($user->role !== 'admin' && ($novel && $novel->creator_id !== $user->id)) {
            abort(403);
        }

        if ($purchase->status !== 'pending') {
            return response()->json(['message' => 'Ushbu xarid allaqachon ko\'rib chiqilgan.'], 400);
        }

        $purchase->status = 'approved';
        $purchase->save();

        return response()->json([
            'message' => 'Xarid muvaffaqiyatli tasdiqlandi.',
            'purchase' => $purchase
        ]);
    }

    /**
     * Reject a purchase receipt.
     */
    public function rejectPurchase($id, Request $request)
    {
        $this->checkCreator($request);
        $user = $request->user();

        $request->validate([
            'admin_note' => 'required|string|max:1000'
        ]);

        $purchase = NovelPurchase::findOrFail($id);
        $novel = Novel::where('id', $purchase->novel_id)->orWhere('id', $purchase->series_id)->first();

        if ($user->role !== 'admin' && ($novel && $novel->creator_id !== $user->id)) {
            abort(403);
        }

        if ($purchase->status !== 'pending') {
            return response()->json(['message' => 'Ushbu xarid allaqachon ko\'rib chiqilgan.'], 400);
        }

        $purchase->status = 'rejected';
        $purchase->admin_note = $request->admin_note;
        $purchase->save();

        return response()->json([
            'message' => 'Xarid rad etildi.',
            'purchase' => $purchase
        ]);
    }

    /* ==========================================
       4. CREATOR PAYMENT CARDS CRUD
       ========================================== */

    public function listCreatorPaymentMethods(Request $request)
    {
        $this->checkCreator($request);
        $cards = PaymentMethod::where('user_id', $request->user()->id)->get();
        return response()->json($cards);
    }

    public function storeCreatorPaymentMethod(Request $request)
    {
        $this->checkCreator($request);
        $request->validate([
            'card_number' => 'required|string|max:30',
            'card_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'is_active' => 'nullable',
        ]);

        $cleanCard = preg_replace('/\D/', '', $request->card_number);

        $card = PaymentMethod::create([
            'user_id' => $request->user()->id,
            'card_number' => $cleanCard,
            'card_holder_name' => $request->card_holder_name,
            'bank_name' => $request->bank_name,
            'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
        ]);

        return response()->json($card, 201);
    }

    public function updateCreatorPaymentMethod($id, Request $request)
    {
        $this->checkCreator($request);
        $card = PaymentMethod::findOrFail($id);
        if ($request->user()->role !== 'admin' && $card->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'card_number' => 'required|string|max:30',
            'card_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'is_active' => 'nullable',
        ]);

        $cleanCard = preg_replace('/\D/', '', $request->card_number);

        $card->update([
            'card_number' => $cleanCard,
            'card_holder_name' => $request->card_holder_name,
            'bank_name' => $request->bank_name,
            'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $card->is_active,
        ]);

        return response()->json($card);
    }

    public function deleteCreatorPaymentMethod($id, Request $request)
    {
        $this->checkCreator($request);
        $card = PaymentMethod::findOrFail($id);
        if ($request->user()->role !== 'admin' && $card->user_id !== $request->user()->id) {
            abort(403);
        }

        $card->delete();

        return response()->json(['message' => 'Karta o\'chirildi.']);
    }

    /* ==========================================
       5. PUBLIC NOVEL ACTIONS & PURCHASE SUBMISSIONS
       ========================================== */

    /**
     * Submit a purchase receipt for a chapter.
     */
    public function purchaseNovelOrChapter($id, Request $request)
    {
        $user = $request->user();
        $novel = Novel::where('id', $id)->orWhere('slug', $id)->firstOrFail();

        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'chapter_id' => 'required|exists:novel_chapters,id',
        ]);

        $card = PaymentMethod::findOrFail($request->payment_method_id);
        if ($card->user_id !== $novel->creator_id) {
            return response()->json(['message' => 'Noto\'g\'ri to\'lov kartasi tanlandi.'], 400);
        }

        $chapter = NovelChapter::findOrFail($request->chapter_id);
        $amount = $chapter->price_in_uzs;

        $path = $request->file('receipt_image')->store('novel_purchases', 'public');

        $purchase = NovelPurchase::create([
            'user_id' => $user->id,
            'novel_id' => $novel->id,
            'novel_chapter_id' => $chapter->id,
            'payment_method_id' => $card->id,
            'receipt_image_path' => $path,
            'purchase_type' => 'single_chapter',
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'To\'lov kvitansiyasi muvaffaqiyatli yuklandi. Novel muallifi tasdiqlashini kuting.',
            'purchase' => $purchase
        ], 201);
    }

    /**
     * Get user purchase status for a novel.
     */
    public function getPurchaseStatus($id, Request $request)
    {
        $user = $request->user('sanctum');
        $novel = Novel::where('id', $id)->orWhere('slug', $id)->firstOrFail();

        if (!$user) {
            return response()->json([
                'purchased_chapter_ids' => [],
                'pending_purchases' => [],
            ]);
        }

        $purchasedChapterIds = NovelPurchase::where('user_id', $user->id)
            ->where('novel_id', $novel->id)
            ->where('status', 'approved')
            ->pluck('novel_chapter_id')
            ->filter()
            ->toArray();

        $pendingPurchases = NovelPurchase::where('user_id', $user->id)
            ->where('novel_id', $novel->id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'purchase_type' => $p->purchase_type,
                    'chapter_id' => $p->novel_chapter_id,
                    'created_at' => $p->created_at,
                ];
            });

        return response()->json([
            'purchased_chapter_ids' => array_values($purchasedChapterIds),
            'pending_purchases' => $pendingPurchases,
        ]);
    }

    /**
     * Get active payment methods for a specific novel's creator.
     */
    public function getNovelPaymentMethods($id)
    {
        $novel = Novel::where('id', $id)->orWhere('slug', $id)->firstOrFail();
        $cards = PaymentMethod::where('user_id', $novel->creator_id)
            ->where('is_active', true)
            ->get();
        return response()->json($cards);
    }

    /**
     * Get settings list (Admin only).
     */
    public function getSettings(Request $request)
    {
        $this->checkAdmin($request);
        return response()->json([
            'novel_creator_monthly_fee' => (int)\App\Models\Setting::get('novel_creator_monthly_fee', 50000)
        ]);
    }

    /**
     * Update settings (Admin only).
     */
    public function updateSettings(Request $request)
    {
        $this->checkAdmin($request);
        $request->validate([
            'novel_creator_monthly_fee' => 'required|integer|min:0'
        ]);

        \App\Models\Setting::set('novel_creator_monthly_fee', $request->novel_creator_monthly_fee);

        return response()->json([
            'message' => 'Sozlamalar muvaffaqiyatli saqlandi.',
            'novel_creator_monthly_fee' => (int)\App\Models\Setting::get('novel_creator_monthly_fee', 50000)
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Series;
use App\Models\Chapter;
use App\Models\ChapterImage;
use App\Models\Genre;
use App\Models\DiamondPackage;
use App\Models\PaymentMethod;
use App\Models\TopupRequest;
use App\Models\DiamondTransaction;
use App\Models\Report;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\RolePermission;
use App\Models\Category;
use App\Models\Coupon;
use App\Jobs\ProcessChapterPdf;
use App\Models\TranslatorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
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
     * Helper to verify if user has a specific permission.
     */
    private function checkPermission(Request $request, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }

        // Super Admin always has full access
        if ($user->role === 'admin') {
            return;
        }

        // Check dynamic role_permissions
        $hasPermission = \App\Models\RolePermission::where('role', $user->role)
            ->where('permission', $permission)
            ->exists();

        if (!$hasPermission) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
    }

    /**
     * Get admin dashboard analytics stats.
     */
    public function stats(Request $request)
    {
        $this->checkPermission($request, 'dashboard');

        // Daily revenue (Approved topup amounts for today)
        $todayRevenue = TopupRequest::where('status', 'approved')
            ->whereDate('reviewed_at', now()->toDateString())
            ->sum('amount');

        // Total revenue (All time approved topups)
        $totalRevenue = TopupRequest::where('status', 'approved')
            ->sum('amount');

        $pendingRequests = TopupRequest::where('status', 'pending')->count();
        $totalUsers = User::count();
        $totalSeries = Series::count();

        $topSeries = Series::orderBy('views_count', 'desc')->limit(5)->get();

        return response()->json([
            'today_revenue' => (float)$todayRevenue,
            'total_revenue' => (float)$totalRevenue,
            'pending_requests_count' => $pendingRequests,
            'total_users' => $totalUsers,
            'total_series' => $totalSeries,
            'top_series' => $topSeries,
        ]);
    }

    /* ==========================================
       SERIES CRUD
       ========================================== */

    public function listSeries(Request $request)
    {
        $this->checkPermission($request, 'series');
        $user = $request->user();
        
        $query = Series::with(['genres', 'sponsors'])->orderBy('created_at', 'desc');
        
        if ($user->role === 'translator') {
            $query->where('translator_id', $user->id);
        }
        
        return response()->json($query->get());
    }

    public function storeSeries(Request $request)
    {
        $this->checkPermission($request, 'series');
        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:series,slug',
            'alternative_titles' => 'nullable|array',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240', // max 10MB
            'type' => 'required|in:manhwa,manga,manhua',
            'status' => 'required|in:ongoing,completed,paused,dropped',
            'is_mature' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'is_slider' => 'nullable|boolean',
            'genres' => 'nullable|array',
            'genres.*' => 'exists:genres,id',
            'sponsors' => 'nullable|array',
            'sponsors.*' => 'exists:sponsors,id',
        ]);

        $slug = $request->slug ? Str::slug($request->slug) : Str::slug($request->title);
        
        // Ensure slug is unique
        $baseSlug = $slug;
        $counter = 1;
        while (Series::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('series', 'public');
        }

        $series = Series::create([
            'title' => $request->title,
            'slug' => $slug,
            'alternative_titles' => $request->alternative_titles ? json_encode($request->alternative_titles) : json_encode([]),
            'description' => $request->description,
            'cover_image' => $coverPath,
            'type' => $request->type,
            'status' => $request->status,
            'is_mature' => filter_var($request->is_mature, FILTER_VALIDATE_BOOLEAN),
            'is_pinned' => filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN),
            'is_slider' => filter_var($request->is_slider, FILTER_VALIDATE_BOOLEAN),
            'translator_id' => $request->user()->role === 'translator' ? $request->user()->id : null,
        ]);

        if ($request->genres) {
            $series->genres()->attach($request->genres);
        }

        if ($request->sponsors) {
            $series->sponsors()->attach($request->sponsors);
        }

        return response()->json([
            'message' => 'Kino/Serial muvaffaqiyatli yaratildi.', // "Series successfully created."
            'series' => $series
        ], 201);
    }

    public function updateSeries($id, Request $request)
    {
        $this->checkPermission($request, 'series');
        $series = Series::findOrFail($id);
        
        $user = $request->user();
        if ($user->role === 'translator' && $series->translator_id !== $user->id) {
            abort(403, 'Siz faqat o\'zingizning manhwalaringizni tahrirlay olasiz.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:series,slug,' . $id,
            'alternative_titles' => 'nullable|array',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|max:10240', // max 10MB
            'type' => 'required|in:manhwa,manga,manhua',
            'status' => 'required|in:ongoing,completed,paused,dropped',
            'is_mature' => 'nullable|boolean',
            'is_pinned' => 'nullable|boolean',
            'is_slider' => 'nullable|boolean',
            'genres' => 'nullable|array',
            'genres.*' => 'exists:genres,id',
            'sponsors' => 'nullable|array',
            'sponsors.*' => 'exists:sponsors,id',
        ]);

        $slug = $request->slug ? Str::slug($request->slug) : Str::slug($request->title);

        if ($request->hasFile('cover_image')) {
            // Delete old
            if ($series->cover_image) {
                Storage::disk('public')->delete($series->cover_image);
            }
            $coverPath = $request->file('cover_image')->store('series', 'public');
            $series->cover_image = $coverPath;
        }

        $series->title = $request->title;
        $series->slug = $slug;
        $series->alternative_titles = $request->alternative_titles ? json_encode($request->alternative_titles) : json_encode([]);
        $series->description = $request->description;
        $series->type = $request->type;
        $series->status = $request->status;
        $series->is_mature = filter_var($request->is_mature, FILTER_VALIDATE_BOOLEAN);
        $series->is_pinned = filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN);
        $series->is_slider = filter_var($request->is_slider, FILTER_VALIDATE_BOOLEAN);
        $series->save();

        if ($request->genres) {
            $series->genres()->sync($request->genres);
        } else {
            $series->genres()->detach();
        }

        if ($request->sponsors) {
            $series->sponsors()->sync($request->sponsors);
        } else {
            $series->sponsors()->detach();
        }

        return response()->json([
            'message' => 'Muvaffaqiyatli yangilandi.', // "Successfully updated."
            'series' => $series
        ]);
    }

    public function deleteSeries($id, Request $request)
    {
        $this->checkPermission($request, 'series');
        $series = Series::findOrFail($id);

        $user = $request->user();
        if ($user->role === 'translator' && $series->translator_id !== $user->id) {
            abort(403, 'Siz faqat o\'zingizning manhwalaringizni o\'chira olasiz.');
        }

        if ($series->cover_image) {
            Storage::disk('public')->delete($series->cover_image);
        }

        // Delete all chapter files associated with this series
        foreach ($series->chapters as $chapter) {
            $this->deleteChapterFiles($chapter);
        }

        $series->delete();

        return response()->json([
            'message' => 'Muvaffaqiyatli o\'chirildi.' // "Successfully deleted."
        ]);
    }

    /* ==========================================
       CHAPTERS CRUD & IMAGES
       ========================================== */

    public function storeChapter($seriesId, Request $request)
    {
        $this->checkPermission($request, 'series');
        $series = Series::findOrFail($seriesId);
        
        $user = $request->user();
        if ($user->role === 'translator' && $series->translator_id !== $user->id) {
            abort(403, 'Siz faqat o\'zingizning manhwalaringizga bob qo\'sha olasiz.');
        }

        $request->validate([
            'chapter_number' => 'required|numeric',
            'title' => 'nullable|string|max:255',
            'is_free' => 'required|boolean',
            'price_in_diamonds' => 'required|integer|min:0',
        ]);

        $series = Series::findOrFail($seriesId);

        // Check if chapter number exists in series
        $exists = Chapter::where('series_id', $series->id)
            ->where('chapter_number', $request->chapter_number)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'chapter_number' => ['Ushbu bob raqami ushbu serialda allaqachon mavjud.'] // "This chapter number already exists in this series."
                ]
            ], 422);
        }

        $chapter = Chapter::create([
            'series_id' => $series->id,
            'chapter_number' => $request->chapter_number,
            'title' => $request->title,
            'is_free' => $request->is_free,
            'price_in_diamonds' => $request->is_free ? 0 : $request->price_in_diamonds,
            'published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Bob muvaffaqiyatli qo\'shildi.', // "Chapter successfully added."
            'chapter' => $chapter
        ], 201);
    }

    public function updateChapter($id, Request $request)
    {
        $this->checkPermission($request, 'series');
        $chapter = Chapter::findOrFail($id);
        $series = $chapter->series;

        $user = $request->user();
        if ($user->role === 'translator' && $series->translator_id !== $user->id) {
            abort(403, 'Siz faqat o\'zingizning manhwalaringiz boblarini tahrirlay olasiz.');
        }

        $request->validate([
            'chapter_number' => 'required|numeric',
            'title' => 'nullable|string|max:255',
            'is_free' => 'required|boolean',
            'price_in_diamonds' => 'required|integer|min:0',
        ]);

        // Check if chapter number exists in series (excluding current chapter)
        $exists = Chapter::where('series_id', $series->id)
            ->where('chapter_number', $request->chapter_number)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'chapter_number' => ['Ushbu bob raqami ushbu serialda allaqachon mavjud.']
                ]
            ], 422);
        }

        $chapter->chapter_number = $request->chapter_number;
        $chapter->title = $request->title;
        $chapter->is_free = $request->is_free;
        $chapter->price_in_diamonds = $request->is_free ? 0 : $request->price_in_diamonds;
        $chapter->save();

        return response()->json([
            'message' => 'Bob muvaffaqiyatli yangilandi.',
            'chapter' => $chapter
        ]);
    }

    public function uploadChapterImages($chapterId, Request $request)
    {
        $this->checkPermission($request, 'series');

        // Check if request size exceeded PHP's post_max_size
        if ($request->header('Content-Length') > 0 && count($request->allFiles()) === 0) {
            $maxPost = ini_get('post_max_size');
            return response()->json([
                'message' => "Fayl yuklashda xatolik yuz berdi. Yuklangan fayl hajmi juda katta. Serverning maksimal yuklash hajmi: {$maxPost}. Iltimos, php.ini sozlamalarida 'upload_max_filesize' va 'post_max_size' qiymatlarini oshiring."
            ], 422);
        }

        // Check for file upload errors if present
        if ($request->file('pdf')) {
            $file = $request->file('pdf');
            if (!$file->isValid()) {
                return response()->json([
                    'message' => "Fayl yuklashda xatolik yuz berdi: " . $file->getErrorMessage() . ". (Serverning maksimal yuklash hajmi: " . ini_get('upload_max_filesize') . ")"
                ], 422);
            }
        }

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:51200', // max 50MB PDF
        ]);

        $chapter = Chapter::findOrFail($chapterId);

        $file = $request->file('pdf');

        // Delete old PDF if exists
        if ($chapter->pdf_path) {
            Storage::disk('public')->delete($chapter->pdf_path);
        }

        // Store PDF in storage/app/public/chapters/
        $filename = 'chapter_' . $chapter->id . '_' . time() . '.pdf';
        $path = $file->storeAs('chapters', $filename, 'public');

        // Update chapter pdf_path
        $chapter->pdf_path = $path;
        $chapter->save();

        // Dispatch background job to slice PDF into images
        ProcessChapterPdf::dispatch($chapter);

        return response()->json([
            'message' => 'PDF fayli muvaffaqiyatli yuklandi va fon rejimida qayta ishlashga yuborildi.',
            'pdf_url' => asset('storage/' . $path)
        ], 200);
    }

    public function deleteChapter($id, Request $request)
    {
        $this->checkPermission($request, 'series');
        $chapter = Chapter::findOrFail($id);

        $user = $request->user();
        if ($user->role === 'translator' && $chapter->series->translator_id !== $user->id) {
            abort(403, 'Siz faqat o\'zingizning manhwalaringizdagi boblarni o\'chira olasiz.');
        }

        // Delete files from storage
        $this->deleteChapterFiles($chapter);

        $chapter->delete();

        return response()->json([
            'message' => 'Bob muvaffaqiyatli o\'chirildi.' // "Chapter successfully deleted."
        ]);
    }

    /* ==========================================
       GENRES CRUD
       ========================================== */

    private function deleteChapterFiles($chapter)
    {
        // Delete uploaded raw images
        $images = ChapterImage::where('chapter_id', $chapter->id)->get();
        foreach ($images as $img) {
            Storage::disk('public')->delete($img->image_path);
        }

        // Delete raw PDF
        if ($chapter->pdf_path) {
            Storage::disk('public')->delete($chapter->pdf_path);
        }

        // Delete WebP generated pages folder
        $pagesArray = is_string($chapter->pages) ? json_decode($chapter->pages, true) : $chapter->pages;
        if (!empty($pagesArray) && is_array($pagesArray) && count($pagesArray) > 0) {
            $firstPage = $pagesArray[0];
            $directory = dirname($firstPage);
            // Safety check to ensure we don't delete root directories
            if ($directory && $directory !== '.' && str_starts_with($directory, 'chapters/')) {
                Storage::disk('public')->deleteDirectory($directory);
            }
        }
    }

    public function storeGenre(Request $request)
    {
        $this->checkPermission($request, 'series');
        $request->validate([
            'name' => 'required|string|max:255|unique:genres,name',
            'slug' => 'nullable|string|unique:genres,slug',
        ]);

        $slug = $request->slug ? Str::slug($request->slug) : Str::slug($request->name);

        $genre = Genre::create([
            'name' => $request->name,
            'slug' => $slug,
        ]);

        return response()->json($genre, 201);
    }

    public function deleteGenre($id, Request $request)
    {
        $this->checkPermission($request, 'series');
        $genre = Genre::findOrFail($id);
        $genre->delete();

        return response()->json([
            'message' => 'Janr o\'chirildi.' // "Genre deleted."
        ]);
    }

    /* ==========================================
       USERS MANAGEMENT
       ========================================== */

    public function listUsers(Request $request)
    {
        $this->checkPermission($request, 'users');
        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    public function updateUser($id, Request $request)
    {
        $this->checkPermission($request, 'users');
        $user = User::findOrFail($id);

        $request->validate([
            'role' => 'required|in:user,admin,moderator,translator',
            'is_banned' => 'required|boolean',
            'adjust_balance' => 'nullable|integer', // positive or negative to add/deduct
            'instagram_url' => 'nullable|string|max:255',
            'telegram_url' => 'nullable|string|max:255',
        ]);

        $user->role = $request->role;
        $user->is_banned = $request->is_banned;
        $user->instagram_url = $request->instagram_url;
        $user->telegram_url = $request->telegram_url;

        $adjust = (int)$request->adjust_balance;
        if ($adjust !== 0) {
            $oldBalance = $user->diamond_balance;
            $newBalance = max(0, $oldBalance + $adjust);
            $user->diamond_balance = $newBalance;

            // Log transaction
            DiamondTransaction::create([
                'user_id' => $user->id,
                'type' => 'admin_adjustment',
                'amount' => $adjust,
                'reference_type' => 'AdminUser',
                'reference_id' => $request->user()->id, // Admin who did it
                'balance_after' => $newBalance,
            ]);

            // Notify user
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Hisobingiz administrator tomonidan o\'zgartirildi',
                'body' => 'Sizning hisobingizga ' . ($adjust > 0 ? "+{$adjust}" : "{$adjust}") . ' olmos kiritildi. Yangi balans: ' . $newBalance . ' olmos.',
                'is_read' => false,
            ]);
        }

        $user->save();

        return response()->json([
            'message' => 'Foydalanuvchi ma\'lumotlari yangilandi.', // "User info updated."
            'user' => $user
        ]);
    }

    /* ==========================================
       DIAMOND PACKAGES & CARDS CRUD
       ========================================== */

    public function storePackage(Request $request)
    {
        $this->checkPermission($request, 'packages');
        $request->validate([
            'name' => 'required|string|max:255',
            'diamond_amount' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer',
        ]);

        $pkg = DiamondPackage::create($request->all());

        return response()->json($pkg, 201);
    }

    public function updatePackage($id, Request $request)
    {
        $this->checkPermission($request, 'packages');
        $pkg = DiamondPackage::findOrFail($id);
        $pkg->update($request->all());

        return response()->json($pkg);
    }

    public function deletePackage($id, Request $request)
    {
        $this->checkPermission($request, 'packages');
        DiamondPackage::findOrFail($id)->delete();
        return response()->json(['message' => 'Paket o\'chirildi.']);
    }

    public function storePaymentMethod(Request $request)
    {
        $this->checkPermission($request, 'packages');
        $request->validate([
            'card_number' => 'required|string|max:20',
            'card_holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $card = PaymentMethod::create($request->all());

        return response()->json($card, 201);
    }

    public function updatePaymentMethod($id, Request $request)
    {
        $this->checkPermission($request, 'packages');
        $card = PaymentMethod::findOrFail($id);
        $card->update($request->all());

        return response()->json($card);
    }

    public function deletePaymentMethod($id, Request $request)
    {
        $this->checkPermission($request, 'packages');
        PaymentMethod::findOrFail($id)->delete();
        return response()->json(['message' => 'Karta o\'chirildi.']);
    }

    /* ==========================================
       TOPUP REQUESTS REVIEW (APPROVE / REJECT)
       ========================================== */

    public function listTopupRequests(Request $request)
    {
        $this->checkPermission($request, 'topup_requests');
        
        $query = TopupRequest::with(['user', 'package']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->get()->map(function($req) {
            return [
                'id' => $req->id,
                'user_name' => $req->user->name,
                'user_email' => $req->user->email,
                'package_name' => $req->package->name,
                'diamond_amount' => $req->package->diamond_amount,
                'amount' => $req->amount,
                'receipt_url' => asset('storage/' . $req->receipt_image_path),
                'user_note' => $req->user_note,
                'status' => $req->status,
                'admin_note' => $req->admin_note,
                'reviewed_by' => $req->reviewer ? $req->reviewer->name : null,
                'reviewed_at' => $req->reviewed_at,
                'created_at' => $req->created_at,
            ];
        });

        return response()->json($requests);
    }

    public function approveTopup($id, Request $request)
    {
        $this->checkPermission($request, 'topup_requests');
        $topup = TopupRequest::findOrFail($id);

        if ($topup->status === 'approved') {
            return response()->json([
                'message' => 'Ushbu so\'rov allaqachon tasdiqlangan (to\'langan).'
            ], 400);
        }

        DB::transaction(function () use ($topup, $request) {
            $topup->status = 'approved';
            $topup->reviewed_by = $request->user()->id;
            $topup->reviewed_at = now();
            $topup->save();

            // Credit user diamonds
            $user = $topup->user;
            $diamondsCredited = $topup->package->diamond_amount;
            $user->diamond_balance += $diamondsCredited;
            $user->save();

            // Log transaction
            DiamondTransaction::create([
                'user_id' => $user->id,
                'type' => 'topup',
                'amount' => $diamondsCredited,
                'reference_type' => 'TopupRequest',
                'reference_id' => $topup->id,
                'balance_after' => $user->diamond_balance,
            ]);

            // Notify user in Uzbek
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Hisobingiz to\'ldirildi! 🎉',
                'body' => "Sizning {$topup->package->name} paketi uchun qilgan to'lovingiz tasdiqlandi. Balansingizga {$diamondsCredited} ta olmos qo'shildi.",
                'is_read' => false,
            ]);
        });

        return response()->json([
            'message' => 'To\'lov muvaffaqiyatli tasdiqlandi. Olmoslar foydalanuvchiga taqdim etildi.' // "Payment successfully approved. Diamonds given to the user."
        ]);
    }

    public function rejectTopup($id, Request $request)
    {
        $this->checkPermission($request, 'topup_requests');
        $topup = TopupRequest::findOrFail($id);

        $request->validate([
            'admin_note' => 'required|string|max:1000',
        ]);

        if ($topup->status !== 'pending') {
            return response()->json([
                'message' => 'Ushbu so\'rov allaqachon ko\'rib chiqilgan.'
            ], 400);
        }

        $topup->status = 'rejected';
        $topup->admin_note = $request->admin_note;
        $topup->reviewed_by = $request->user()->id;
        $topup->reviewed_at = now();
        $topup->save();

        // Notify user in Uzbek
        Notification::create([
            'user_id' => $topup->user_id,
            'title' => 'To\'lov rad etildi ❌',
            'body' => "Sizning {$topup->package->name} paketi uchun qilgan to'lovingiz rad etildi. Sabab: {$request->admin_note}. Iltimos, qaytadan urinib ko'ring.",
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'To\'lov rad etildi. Foydalanuvchiga xabar yuborildi.' // "Payment rejected. Notification sent to the user."
        ]);
    }

    /* ==========================================
       REPORTS MANAGEMENT
       ========================================== */

    public function listReports(Request $request)
    {
        $this->checkPermission($request, 'dashboard');

        $reports = Report::with(['user', 'series', 'chapter'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($rep) {
                return [
                    'id' => $rep->id,
                    'user_name' => $rep->user ? $rep->user->name : 'Mehmon (Guest)',
                    'user_email' => $rep->user ? $rep->user->email : null,
                    'series_title' => $rep->series ? $rep->series->title : null,
                    'chapter_number' => $rep->chapter ? $rep->chapter->chapter_number : null,
                    'message' => $rep->message,
                    'status' => $rep->status,
                    'created_at' => $rep->created_at,
                ];
            });

        return response()->json($reports);
    }

    public function resolveReport($id, Request $request)
    {
        $this->checkPermission($request, 'dashboard');
        $report = Report::findOrFail($id);
        $report->status = 'resolved';
        $report->save();

        return response()->json([
            'message' => 'Muammo bartaraf etildi deb belgilandi.' // "Problem marked as resolved."
        ]);
    }

    /**
     * Admin: Get all permissions mappings for role matrix editor.
     */
    public function getPermissions(Request $request)
    {
        $this->checkAdmin($request);

        $roles = ['moderator']; // config roles list
        $permissionsList = [
            ['key' => 'dashboard', 'label' => 'Boshqaruv paneli analitikasi (Dashboard Stats)'],
            ['key' => 'topup_requests', 'label' => 'To\'lov arizalarini ko\'rib chiqish (Top-up Requests)'],
            ['key' => 'series', 'label' => 'Manhwa va boblar boshqaruvi (Series & Chapters)'],
            ['key' => 'packages', 'label' => 'Olmos paketlari va kartalar (Packages & Cards)'],
            ['key' => 'users', 'label' => 'Foydalanuvchilar va balans (Users & Balances)'],
            ['key' => 'coupons', 'label' => 'Kuponlar boshqaruvi (Coupons)'],
            ['key' => 'sponsors', 'label' => 'Homiy hamkorlar (Sponsors)'],
            ['key' => 'books', 'label' => 'Kitoblar do\'koni (Book Shop)'],
            ['key' => 'orders', 'label' => 'Buyurtmalar boshqaruvi (Orders)'],
        ];

        $activePermissions = \App\Models\RolePermission::all()->groupBy('role')->map(function($items) {
            return $items->pluck('permission');
        });

        return response()->json([
            'roles' => $roles,
            'permissions_list' => $permissionsList,
            'active_permissions' => $activePermissions
        ]);
    }

    /**
     * Admin: Update permissions for a specific role.
     */
    public function updatePermissions(Request $request)
    {
        $this->checkAdmin($request);

        $request->validate([
            'role' => 'required|string|in:moderator',
            'permissions' => 'present|array',
            'permissions.*' => 'string'
        ]);

        $role = $request->role;

        DB::transaction(function() use ($role, $request) {
            \App\Models\RolePermission::where('role', $role)->delete();

            $data = [];
            foreach ($request->permissions as $p) {
                $data[] = [
                    'role' => $role,
                    'permission' => $p,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($data)) {
                \App\Models\RolePermission::insert($data);
            }
        });

        return response()->json([
            'message' => 'Rollar huquqlari muvaffaqiyatli yangilandi.'
        ]);
    }

    // ==========================================
    // TRANSLATOR APPLICATIONS
    // ==========================================

    public function getTranslatorApplications(Request $request)
    {
        // Require users permission or a new specific permission for apps
        $this->checkPermission($request, 'users');

        $applications = TranslatorApplication::with('user:id,name,email,avatar_url,created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($applications);
    }

    public function updateTranslatorApplication($id, Request $request)
    {
        $this->checkPermission($request, 'users');

        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        $application = TranslatorApplication::findOrFail($id);
        
        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Bu ariza allaqachon ko\'rib chiqilgan.'], 400);
        }

        $application->status = $request->status;
        $application->save();

        if ($request->status === 'approved') {
            $user = $application->user;
            if ($user && $user->role !== 'admin') {
                $user->role = 'translator';
                $user->save();
            }
        }

        return response()->json([
            'message' => 'Ariza holati yangilandi.',
            'application' => $application
        ]);
    }
}

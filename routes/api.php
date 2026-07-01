<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\TopupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\RatingLikeController;
use App\Http\Controllers\NovelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register-request', [AuthController::class, 'registerRequest']);
    Route::post('/register-verify', [AuthController::class, 'registerVerify']);
    Route::post('/forgot-password-request', [AuthController::class, 'forgotPasswordRequest']);
    Route::post('/forgot-password-verify', [AuthController::class, 'forgotPasswordVerify']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/user/avatar', [AuthController::class, 'updateAvatar']);
        Route::post('/user/change-password', [AuthController::class, 'changePassword']);
        Route::put('/user', [AuthController::class, 'update']);

        // Cart Actions
        Route::get('/cart', [\App\Http\Controllers\CartController::class, 'index']);
        Route::post('/cart/add', [\App\Http\Controllers\CartController::class, 'add']);
        Route::put('/cart/update/{id}', [\App\Http\Controllers\CartController::class, 'update']);
        Route::delete('/cart/remove/{id}', [\App\Http\Controllers\CartController::class, 'remove']);
        Route::delete('/cart/clear', [\App\Http\Controllers\CartController::class, 'clear']);
        Route::post('/cart/sync', [\App\Http\Controllers\CartController::class, 'sync']);
    });
});

// 2. Catalog (Public) routes
Route::get('/series', [CatalogController::class, 'series']);
Route::get('/series/{slug}', [CatalogController::class, 'showSeries']);
Route::get('/series/{slug}/chapters', [CatalogController::class, 'seriesChapters']);
Route::get('/chapters/{id}', [ChapterController::class, 'show']); // handles guest/auth checks
Route::get('/genres', [CatalogController::class, 'genres']);
Route::get('/trending', [CatalogController::class, 'trending']);
Route::get('/slider', [CatalogController::class, 'slider']);
Route::get('/latest-updates', [CatalogController::class, 'latestUpdates']);
Route::get('/completed', [CatalogController::class, 'completed']);
Route::get('/sponsors', [SponsorController::class, 'publicIndex']);
Route::get('/chapters/{chapterId}/comments', [\App\Http\Controllers\ChapterCommentController::class, 'index']);
Route::get('/novel-chapters/{id}', [ChapterController::class, 'showNovelChapter']);
Route::get('/novel-chapters/{chapterId}/comments', [\App\Http\Controllers\ChapterCommentController::class, 'novelComments']);
Route::get('/leaderboard', [CatalogController::class, 'leaderboard']);
Route::get('/books', [\App\Http\Controllers\BookController::class, 'index']);
Route::get('/topup/payment-methods', [TopupController::class, 'paymentMethods']);

// News & Translators (Public)
Route::get('/news', [\App\Http\Controllers\NewsController::class, 'index']);
Route::get('/news/{id}', [\App\Http\Controllers\NewsController::class, 'show']);
Route::get('/translators', [\App\Http\Controllers\TranslatorController::class, 'index']);
Route::get('/translators/{id}', [\App\Http\Controllers\TranslatorController::class, 'show']);

// 3. User Dashboard routes (Auth required)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/wallet', [TopupController::class, 'wallet']);
    Route::get('/user/transactions', [TopupController::class, 'transactions']);

    Route::get('/bookmarks', [DashboardController::class, 'bookmarks']);
    Route::post('/bookmarks', [DashboardController::class, 'addBookmark']);
    Route::delete('/bookmarks/{id}', [DashboardController::class, 'removeBookmark']);

    Route::post('/chapters/{id}/purchase', [ChapterController::class, 'purchase']);

    Route::get('/notifications', [DashboardController::class, 'notifications']);
    Route::put('/notifications/{id}/read', [DashboardController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [DashboardController::class, 'markAllAsRead']);

    Route::get('/topup/packages', [TopupController::class, 'packages']);
    Route::get('/topup/payment-methods', [TopupController::class, 'paymentMethods']);
    Route::post('/topup/topup-requests', [TopupController::class, 'storeRequest']);
    Route::get('/topup/topup-requests', [TopupController::class, 'requestHistory']);

    // Coupons & Ratings/Likes
    Route::post('/user/claim-coupon', [CouponController::class, 'claim']);
    Route::post('/series/{id}/rate', [RatingLikeController::class, 'rate']);
    Route::post('/series/{id}/like', [RatingLikeController::class, 'toggleLike']);
    Route::get('/series/{id}/rating-like-status', [RatingLikeController::class, 'checkStatus']);
    Route::post('/novels/{id}/rate', [RatingLikeController::class, 'rateNovel']);
    Route::post('/novels/{id}/like', [RatingLikeController::class, 'toggleLikeNovel']);
    Route::get('/novels/{id}/rating-like-status', [RatingLikeController::class, 'checkNovelStatus']);
    Route::post('/chapters/{chapterId}/comments', [\App\Http\Controllers\ChapterCommentController::class, 'store']);
    Route::post('/novel-chapters/{chapterId}/comments', [\App\Http\Controllers\ChapterCommentController::class, 'storeNovelComment']);
    Route::delete('/comments/{id}', [\App\Http\Controllers\ChapterCommentController::class, 'destroy']);

    // Book orders
    Route::post('/orders', [\App\Http\Controllers\BookController::class, 'placeOrder']);
    Route::get('/user/orders', [\App\Http\Controllers\BookController::class, 'userOrders']);

    // Translators Auth Actions
    Route::post('/translators/{id}/follow', [\App\Http\Controllers\TranslatorController::class, 'toggleFollow']);
    Route::post('/translators/apply', [\App\Http\Controllers\TranslatorController::class, 'apply']);

    // Novel Creator Applications
    Route::post('/novel-creators/apply', [NovelController::class, 'apply']);
    Route::get('/novel-creators/application-status', [NovelController::class, 'applicationStatus']);

    // Novel Purchases & Subscriptions
    Route::post('/novels/{id}/purchase', [NovelController::class, 'purchaseNovelOrChapter']);
    Route::get('/novels/{id}/purchase-status', [NovelController::class, 'getPurchaseStatus']);
    Route::get('/novels/{id}/payment-methods', [NovelController::class, 'getNovelPaymentMethods']);
});

// 4. Reports (Guest or Auth)
Route::post('/reports', [ReportController::class, 'store']);

// Telegram Bot Webhook
Route::post('/telegram/webhook', [\App\Http\Controllers\TelegramBotController::class, 'webhook']);

// 5. Admin Panel routes (Auth + Admin check in Controller)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);
    Route::get('/settings', [NovelController::class, 'getSettings']);
    Route::post('/settings', [NovelController::class, 'updateSettings']);

    // Role Permissions Management (admin only)
    Route::get('/permissions', [AdminController::class, 'getPermissions']);
    Route::post('/permissions', [AdminController::class, 'updatePermissions']);

    // Series CRUD
    Route::get('/series', [AdminController::class, 'listSeries']);
    Route::post('/series', [AdminController::class, 'storeSeries']);
    Route::post('/series/{id}', [AdminController::class, 'updateSeries']); // POST to handle multipart data
    Route::delete('/series/{id}', [AdminController::class, 'deleteSeries']);

    // Chapters CRUD + Images
    Route::post('/series/{seriesId}/chapters', [AdminController::class, 'storeChapter']);
    Route::put('/chapters/{id}', [AdminController::class, 'updateChapter']);
    Route::post('/chapters/{chapterId}/images', [AdminController::class, 'uploadChapterImages']);
    Route::delete('/chapters/{id}', [AdminController::class, 'deleteChapter']);

    // Genres CRUD
    Route::post('/genres', [AdminController::class, 'storeGenre']);
    Route::delete('/genres/{id}', [AdminController::class, 'deleteGenre']);

    // Users Management
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);

    // Packages CRUD
    Route::post('/packages', [AdminController::class, 'storePackage']);
    Route::put('/packages/{id}', [AdminController::class, 'updatePackage']);
    Route::delete('/packages/{id}', [AdminController::class, 'deletePackage']);

    // Payment Methods CRUD
    Route::post('/payment-methods', [AdminController::class, 'storePaymentMethod']);
    Route::put('/payment-methods/{id}', [AdminController::class, 'updatePaymentMethod']);
    Route::delete('/payment-methods/{id}', [AdminController::class, 'deletePaymentMethod']);

    // Topup Requests Management
    Route::get('/topup-requests', [AdminController::class, 'listTopupRequests']);
    Route::post('/topup-requests/{id}/approve', [AdminController::class, 'approveTopup']);
    Route::post('/topup-requests/{id}/reject', [AdminController::class, 'rejectTopup']);

    // Reports Management
    Route::get('/reports', [AdminController::class, 'listReports']);
    Route::put('/reports/{id}', [AdminController::class, 'resolveReport']);

    // Coupon admin CRUD
    Route::get('/coupons', [CouponController::class, 'index']);
    Route::post('/coupons', [CouponController::class, 'store']);
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

    // Sponsor admin CRUD
    Route::get('/sponsors', [SponsorController::class, 'index']);
    Route::post('/sponsors', [SponsorController::class, 'store']);
    Route::delete('/sponsors/{id}', [SponsorController::class, 'destroy']);

    // Books & Orders admin CRUD
    Route::get('/books', [\App\Http\Controllers\BookController::class, 'adminIndex']);
    Route::post('/books', [\App\Http\Controllers\BookController::class, 'store']);
    Route::post('/books/{id}', [\App\Http\Controllers\BookController::class, 'update']);
    Route::delete('/books/{id}', [\App\Http\Controllers\BookController::class, 'destroy']);
    Route::get('/orders', [\App\Http\Controllers\BookController::class, 'adminOrders']);
    Route::put('/orders/{id}/status', [\App\Http\Controllers\BookController::class, 'updateOrderStatus']);

    // News admin CRUD
    Route::post('/news', [\App\Http\Controllers\NewsController::class, 'store']);
    Route::post('/news/{id}', [\App\Http\Controllers\NewsController::class, 'update']);
    Route::delete('/news/{id}', [\App\Http\Controllers\NewsController::class, 'destroy']);

    // Translator Applications admin CRUD
    Route::get('/translator-applications', [AdminController::class, 'getTranslatorApplications']);
    Route::post('/translator-applications/{id}/status', [AdminController::class, 'updateTranslatorApplication']);

    // Novel Creator Applications admin CRUD (Superadmin)
    Route::get('/novel-creator-applications', [NovelController::class, 'listApplications']);
    Route::post('/novel-creator-applications/{id}/approve', [NovelController::class, 'approveApplication']);
    Route::post('/novel-creator-applications/{id}/reject', [NovelController::class, 'rejectApplication']);
});

// 6. Novel Creator Dashboard routes (Auth required + creator check)
Route::middleware(['auth:sanctum'])->prefix('creator')->group(function () {
    Route::get('/stats', [NovelController::class, 'creatorStats']);
    
    // Creator Novels CRUD
    Route::get('/novels', [NovelController::class, 'listCreatorNovels']);
    Route::post('/novels', [NovelController::class, 'storeCreatorNovel']);
    Route::post('/novels/{id}', [NovelController::class, 'updateCreatorNovel']);
    Route::delete('/novels/{id}', [NovelController::class, 'deleteCreatorNovel']);
    
    // Creator Chapters CRUD
    Route::post('/novels/{novelId}/chapters', [NovelController::class, 'storeCreatorChapter']);
    Route::put('/chapters/{id}', [NovelController::class, 'updateCreatorChapter']);
    Route::delete('/chapters/{id}', [NovelController::class, 'deleteCreatorChapter']);
    
    // Creator Purchases verification
    Route::get('/purchases', [NovelController::class, 'listCreatorPurchases']);
    Route::post('/purchases/{id}/approve', [NovelController::class, 'approvePurchase']);
    Route::post('/purchases/{id}/reject', [NovelController::class, 'rejectPurchase']);
    
    // Creator Card Management
    Route::get('/payment-methods', [NovelController::class, 'listCreatorPaymentMethods']);
    Route::post('/payment-methods', [NovelController::class, 'storeCreatorPaymentMethod']);
    Route::put('/payment-methods/{id}', [NovelController::class, 'updateCreatorPaymentMethod']);
    Route::delete('/payment-methods/{id}', [NovelController::class, 'deleteCreatorPaymentMethod']);
});

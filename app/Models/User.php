<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'avatar', 'avatar_url', 'diamond_balance', 'role', 'is_banned', 'referred_by', 'instagram_url', 'telegram_url', 'novel_creator_expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = ['permissions'];

    /**
     * The user who referred this user.
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Users referred by this user.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions);
    }

    // A translator can have many translated series
    public function translatedSeries(): HasMany
    {
        return $this->hasMany(Series::class, 'translator_id');
    }

    // A translator has many followers
    public function followers()
    {
        return $this->belongsToMany(User::class, 'translator_followers', 'translator_id', 'user_id');
    }

    // A normal user can follow many translators
    public function followingTranslators()
    {
        return $this->belongsToMany(User::class, 'translator_followers', 'user_id', 'translator_id');
    }

    // A user has one application
    public function translatorApplication()
    {
        return $this->hasOne(TranslatorApplication::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function novelPurchases(): HasMany
    {
        return $this->hasMany(NovelPurchase::class);
    }

    public function creatorApplications(): HasMany
    {
        return $this->hasMany(NovelCreatorApplication::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'diamond_balance' => 'integer',
            'novel_creator_expires_at' => 'datetime',
        ];
    }

    /**
     * Bookmarks saved by this user.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * Topup requests submitted by this user.
     */
    public function topupRequests(): HasMany
    {
        return $this->hasMany(TopupRequest::class);
    }

    /**
     * Diamond transactions of this user.
     */
    public function diamondTransactions(): HasMany
    {
        return $this->hasMany(DiamondTransaction::class);
    }

    /**
     * Chapters purchased by this user.
     */
    public function chapterPurchases(): HasMany
    {
        return $this->hasMany(ChapterPurchase::class);
    }

    /**
     * Notifications sent to this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Orders placed by this user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the permissions allowed for the user's role.
     */
    public function getPermissionsAttribute(): array
    {
        if ($this->role === 'admin') {
            return ['dashboard', 'topup_requests', 'series', 'packages', 'users', 'coupons', 'sponsors', 'books', 'orders', 'permissions', 'creator_dashboard', 'novels_management', 'creator_topups', 'creator_payment_methods'];
        }

        if ($this->role === 'novel_creator') {
            return ['creator_dashboard', 'novels_management', 'creator_topups', 'creator_payment_methods'];
        }

        try {
            return \App\Models\RolePermission::where('role', $this->role)
                ->pluck('permission')
                ->toArray();
        } catch (\Exception $e) {
            // Fallback during migrations/seeding or if database is not ready
            if ($this->role === 'moderator') {
                return ['series', 'sponsors'];
            }
            if ($this->role === 'translator') {
                return ['series'];
            }
            return [];
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register Request - sends verification code.
     */
    public function registerRequest(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'ref' => 'nullable|integer|exists:users,id',
        ]);

        $code = sprintf("%06d", mt_rand(0, 999999));
        $expiresAt = now()->addMinutes(15);

        \App\Models\PendingUser::updateOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'code' => $code,
                'expires_at' => $expiresAt,
                'referred_by' => $request->ref,
            ]
        );

        // Send verification code using Laravel Mail (logs to storage/logs/laravel.log)
        \Illuminate\Support\Facades\Mail::raw("Sizning Ecliptoon platformasida ro'yxatdan o'tish uchun tasdiqlash kodingiz: {$code}. Kod 15 daqiqa davomida faol.", function ($message) use ($request) {
            $message->to($request->email)->subject("Ecliptoon: Email tasdiqlash kodi");
        });

        return response()->json([
            'message' => 'Tasdiqlash kodi emailingizga yuborildi.' // "Verification code sent to your email."
        ]);
    }

    /**
     * Verify code and complete registration.
     */
    public function registerVerify(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string|size:6',
        ]);

        $pending = \App\Models\PendingUser::where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$pending) {
            throw ValidationException::withMessages([
                'code' => ['Kiritilgan tasdiqlash kodi noto\'g\'ri.'], // "Verification code is incorrect."
            ]);
        }

        if ($pending->expires_at->isPast()) {
            $pending->delete();
            throw ValidationException::withMessages([
                'code' => ['Tasdiqlash kodining muddati tugagan.'], // "Verification code has expired."
            ]);
        }

        // Create the user and distribute referral bonuses inside a transaction
        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($pending) {
            $hasReferrer = $pending->referred_by && User::where('id', $pending->referred_by)->exists();

            $user = User::create([
                'name' => $pending->name,
                'email' => $pending->email,
                'password' => $pending->password, // Already hashed
                'role' => 'user',
                'diamond_balance' => $hasReferrer ? 2 : 0,
                'is_banned' => false,
                'referred_by' => $hasReferrer ? $pending->referred_by : null,
            ]);

            if ($hasReferrer) {
                $referrer = User::find($pending->referred_by);
                $referrer->diamond_balance += 2;
                $referrer->save();

                // Log transaction for referrer
                \App\Models\DiamondTransaction::create([
                    'user_id' => $referrer->id,
                    'type' => 'referral',
                    'amount' => 2,
                    'reference_type' => 'User',
                    'reference_id' => $user->id,
                    'balance_after' => $referrer->diamond_balance,
                ]);

                // Log transaction for referred user
                \App\Models\DiamondTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'referral',
                    'amount' => 2,
                    'reference_type' => 'User',
                    'reference_id' => $referrer->id,
                    'balance_after' => $user->diamond_balance,
                ]);

                // Notify referrer
                \App\Models\Notification::create([
                    'user_id' => $referrer->id,
                    'title' => 'Taklifnoma bonusi! 🎁',
                    'body' => "Siz taklif qilgan do'stingiz ({$user->name}) ro'yxatdan o'tdi. Sizga 2 ta olmos taqdim etildi!",
                    'is_read' => false,
                ]);

                // Notify referred user
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Taklifnoma bonusi! 🎁',
                    'body' => "Siz do'stingiz taklif havolasi orqali ro'yxatdan o'tganingiz uchun 2 ta olmos bonus oldingiz!",
                    'is_read' => false,
                ]);
            }

            // Clean up
            $pending->delete();

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function forgotPasswordRequest(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $code = sprintf("%06d", mt_rand(0, 999999));

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $code, 'created_at' => now()]
        );

        \Illuminate\Support\Facades\Mail::raw("Sizning Ecliptoon platformasida parolni tiklash kodingiz: {$code}. Kod 15 daqiqa davomida faol.", function ($message) use ($request) {
            $message->to($request->email)->subject("Ecliptoon: Parolni tiklash kodi");
        });

        return response()->json([
            'message' => 'Parolni tiklash kodi emailingizga yuborildi.'
        ]);
    }

    public function forgotPasswordVerify(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6',
        ]);

        $tokenRecord = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$tokenRecord || $tokenRecord->token !== $request->code) {
            throw ValidationException::withMessages([
                'code' => ['Kiritilgan tasdiqlash kodi noto\'g\'ri.'],
            ]);
        }

        if (\Carbon\Carbon::parse($tokenRecord->created_at)->addMinutes(15)->isPast()) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'code' => ['Tasdiqlash kodining muddati tugagan.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Parolingiz muvaffaqiyatli o\'zgartirildi.'
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Joriy parol noto\'g\'ri kiritildi.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Parolingiz muvaffaqiyatli yangilandi.'
        ]);
    }

    /**
     * Login user and return token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kiritilgan ma\'lumotlar noto\'g\'ri.'], // "The credentials entered are incorrect."
            ]);
        }

        if ($user->is_banned) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan. Tizimga kira olmaysiz.' // "Your account has been banned. You cannot login."
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan muvaffaqiyatli chiqdingiz.' // "Successfully logged out."
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Upload Avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar_url) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar_url);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = $path;
        $user->save();

        return response()->json([
            'message' => 'Avatar muvaffaqiyatli yangilandi.',
            'user' => $user
        ]);
    }

    /**
     * Update authenticated user's profile (name, email, social links).
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'instagram_url' => 'nullable|url|max:1024',
            'telegram_url' => 'nullable|url|max:1024',
        ]);

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }

        if ($request->has('email')) {
            $user->email = $request->input('email');
        }

        if ($request->has('instagram_url')) {
            $user->instagram_url = $request->input('instagram_url');
        }

        if ($request->has('telegram_url')) {
            $user->telegram_url = $request->input('telegram_url');
        }

        $user->save();

        return response()->json([
            'message' => 'Profil muvaffaqiyatli yangilandi.',
            'user' => $user,
        ]);
    }
}

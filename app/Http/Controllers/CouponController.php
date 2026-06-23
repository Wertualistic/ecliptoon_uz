<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponClaim;
use App\Models\DiamondTransaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    /**
     * Helper to verify if user has a specific permission.
     */
    private function checkPermission(Request $request, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
        if ($user->role === 'admin') {
            return;
        }
        $hasPermission = \App\Models\RolePermission::where('role', $user->role)
            ->where('permission', $permission)
            ->exists();
        if (!$hasPermission) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
    }

    /**
     * Admin: List all coupons.
     */
    public function index(Request $request)
    {
        $this->checkPermission($request, 'coupons');
        $coupons = Coupon::orderBy('created_at', 'desc')->get();
        return response()->json($coupons);
    }

    /**
     * Admin: Create a new coupon.
     */
    public function store(Request $request)
    {
        $this->checkPermission($request, 'coupons');
        $request->validate([
            'code' => 'required|string|unique:coupons,code|max:50',
            'diamond_amount' => 'required|integer|min:1',
            'max_uses' => 'required|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $coupon = Coupon::create([
            'code' => strtoupper($request->code),
            'diamond_amount' => $request->diamond_amount,
            'max_uses' => $request->max_uses,
            'uses_count' => 0,
            'expires_at' => $request->expires_at,
            'is_active' => true,
        ]);

        return response()->json($coupon, 201);
    }

    /**
     * Admin: Delete/Deactivate a coupon.
     */
    public function destroy($id, Request $request)
    {
        $this->checkPermission($request, 'coupons');
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json(['message' => 'Kupon muvaffaqiyatli o\'chirildi.']);
    }

    /**
     * User: Claim coupon code.
     */
    public function claim(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $user = $request->user();
        $code = strtoupper($request->code);

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon || !$coupon->is_active) {
            return response()->json([
                'message' => 'Ushbu kupon kodi mavjud emas yoki faol emas.' // "This coupon code does not exist or is not active."
            ], 400);
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json([
                'message' => 'Kupon kodining amal qilish muddati tugagan.' // "This coupon code has expired."
            ], 400);
        }

        if ($coupon->uses_count >= $coupon->max_uses) {
            return response()->json([
                'message' => 'Ushbu kupon kodi to\'liq ishlatib bo\'lingan.' // "This coupon code is fully used."
            ], 400);
        }

        // Check if user already claimed
        $alreadyClaimed = CouponClaim::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->exists();

        if ($alreadyClaimed) {
            return response()->json([
                'message' => 'Siz ushbu kuponni allaqachon faollashtirgansiz.' // "You have already claimed this coupon."
            ], 400);
        }

        // Process claim transaction
        DB::transaction(function () use ($user, $coupon) {
            // Increment uses
            $coupon->increment('uses_count');

            // Log Claim
            CouponClaim::create([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
            ]);

            // Add diamonds to user
            $user->diamond_balance += $coupon->diamond_amount;
            $user->save();

            // Log Transaction
            DiamondTransaction::create([
                'user_id' => $user->id,
                'type' => 'admin_adjustment',
                'amount' => $coupon->diamond_amount,
                'reference_type' => 'Coupon',
                'reference_id' => $coupon->id,
                'balance_after' => $user->diamond_balance,
            ]);

            // Create notification in Uzbek
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Kupon muvaffaqiyatli ishlatildi', // "Coupon successfully used"
                'body' => "Siz {$coupon->code} kuponini muvaffaqiyatli ishlatdingiz. Hisobingizga {$coupon->diamond_amount} ta olmos qo'shildi.",
                'type' => 'coupon'
            ]);
        });

        return response()->json([
            'message' => "Kupon faollashtirildi! Balansingizga {$coupon->diamond_amount} ta olmos taqdim etildi.", // "Coupon activated! You received {X} diamonds."
            'diamond_amount' => $coupon->diamond_amount,
            'new_balance' => $user->diamond_balance
        ]);
    }
}

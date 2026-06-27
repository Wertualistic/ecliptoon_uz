<?php

namespace App\Http\Controllers;

use App\Models\DiamondPackage;
use App\Models\PaymentMethod;
use App\Models\TopupRequest;
use App\Models\DiamondTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TopupController extends Controller
{
    /**
     * Get active diamond packages.
     */
    public function packages()
    {
        $packages = DiamondPackage::where('is_active', true)->orderBy('sort_order', 'asc')->get();
        return response()->json($packages);
    }

    public function paymentMethods()
    {
        $cards = PaymentMethod::where('is_active', true)->whereNull('user_id')->get();
        return response()->json($cards);
    }

    /**
     * Get user's wallet info (balance, packages, cards).
     */
    public function wallet(Request $request)
    {
        $user = $request->user();
        $packages = DiamondPackage::where('is_active', true)->orderBy('sort_order', 'asc')->get();
        $cards = PaymentMethod::where('is_active', true)->whereNull('user_id')->get();

        return response()->json([
            'diamond_balance' => $user->diamond_balance,
            'packages' => $packages,
            'payment_methods' => $cards,
        ]);
    }

    /**
     * Get user's diamond transaction history.
     */
    public function transactions(Request $request)
    {
        $transactions = $request->user()
            ->diamondTransactions()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    /**
     * Submit a new top-up request with payment receipt upload.
     */
    public function storeRequest(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:diamond_packages,id',
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // max 5MB
            'user_note' => 'nullable|string|max:1000',
        ]);

        $package = DiamondPackage::findOrFail($request->package_id);
        $user = $request->user();

        // Check if there is already a pending request for the same package to prevent spam (optional, let's allow multiple but warn if needed)
        // Store receipt image
        $path = $request->file('receipt_image')->store('receipts', 'public');

        $topupRequest = TopupRequest::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'receipt_image_path' => $path,
            'user_note' => $request->user_note,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'To\'lov kvitansiyasi muvaffaqiyatli yuklandi. Administrator tasdiqlashini kuting.', // "Receipt successfully uploaded. Wait for admin approval."
            'request' => $topupRequest
        ], 201);
    }

    /**
     * Get user's own topup request history.
     */
    public function requestHistory(Request $request)
    {
        $history = TopupRequest::with(['package'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($req) {
                return [
                    'id' => $req->id,
                    'package_name' => $req->package->name,
                    'diamond_amount' => $req->package->diamond_amount,
                    'amount' => $req->amount,
                    'receipt_url' => asset('storage/' . $req->receipt_image_path),
                    'user_note' => $req->user_note,
                    'status' => $req->status,
                    'admin_note' => $req->admin_note,
                    'created_at' => $req->created_at,
                ];
            });

        return response()->json($history);
    }
}

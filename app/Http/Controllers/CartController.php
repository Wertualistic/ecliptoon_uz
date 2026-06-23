<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CartItem;
use App\Models\Book;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    // Get user's cart
    public function index()
    {
        $user = Auth::user();
        $cartItems = $user->cartItems()->with('book')->get();
        return response()->json($cartItems);
    }

    // Add item to cart
    public function add(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'quantity' => 'integer|min:1'
        ]);

        $user = Auth::user();
        $book_id = $request->book_id;
        $quantity = $request->input('quantity', 1);

        $cartItem = $user->cartItems()->where('book_id', $book_id)->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            $cartItem = $user->cartItems()->create([
                'book_id' => $book_id,
                'quantity' => $quantity
            ]);
        }

        $cartItem->load('book');
        return response()->json(['message' => 'Qo\'shildi', 'item' => $cartItem]);
    }

    // Update quantity
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = Auth::user()->cartItems()->findOrFail($id);
        $cartItem->update(['quantity' => $request->quantity]);

        $cartItem->load('book');
        return response()->json(['message' => 'Yangilandi', 'item' => $cartItem]);
    }

    // Remove item
    public function remove($id)
    {
        $cartItem = Auth::user()->cartItems()->findOrFail($id);
        $cartItem->delete();

        return response()->json(['message' => 'O\'chirildi']);
    }

    // Clear cart
    public function clear()
    {
        Auth::user()->cartItems()->delete();
        return response()->json(['message' => 'Savat tozalandi']);
    }

    // Sync from local storage
    public function sync(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:books,id',
            'items.*.quantity' => 'required|integer|min:1'
        ]);

        $user = Auth::user();

        foreach ($request->items as $item) {
            $cartItem = $user->cartItems()->where('book_id', $item['id'])->first();

            if ($cartItem) {
                // Optionally add quantities or just keep the max/current. Let's add them.
                $cartItem->quantity += $item['quantity'];
                $cartItem->save();
            } else {
                $user->cartItems()->create([
                    'book_id' => $item['id'],
                    'quantity' => $item['quantity']
                ]);
            }
        }

        $cartItems = $user->cartItems()->with('book')->get();
        return response()->json(['message' => 'Sinxronlashtirildi', 'cart' => $cartItems]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\DiamondTransaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BookController extends Controller
{
    /**
     * Get list of books for the public store.
     */
    public function index()
    {
        $books = Book::orderBy('created_at', 'desc')->get()->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'description' => $book->description,
                'price' => $book->price,
                'cover_url' => $book->cover_path ? asset('storage/' . $book->cover_path) : null,
                'stock' => $book->stock,
                'created_at' => $book->created_at->toISOString(),
            ];
        });

        return response()->json($books);
    }

    /**
     * Place a book order.
     */
    public function placeOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.book_id' => 'required|integer|exists:books,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        if ($user->is_banned) {
            return response()->json([
                'message' => 'Sizning hisobingiz bloklangan, buyurtma bera olmaysiz.'
            ], 403);
        }

        $order = DB::transaction(function () use ($request, $user) {
            $totalPrice = 0;
            $itemsToProcess = [];

            // 1. Validate stocks and calculate price
            foreach ($request->items as $item) {
                $book = Book::lockForUpdate()->find($item['book_id']);
                
                if ($book->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["{$book->title} kitobi uchun zaxira yetarli emas. Qolgan zaxira: {$book->stock}"]
                    ]);
                }

                $itemCost = $book->price * $item['quantity'];
                $totalPrice += $itemCost;

                $itemsToProcess[] = [
                    'book' => $book,
                    'quantity' => $item['quantity'],
                    'price' => $book->price,
                ];
            }

            // 2. Check strawberry balance (stored in diamond_balance)
            if ($user->diamond_balance < $totalPrice) {
                throw ValidationException::withMessages([
                    'balance' => ["Hisobingizda yetarli mablag' (olmos) mavjud emas. Buyurtma jami: {$totalPrice} olmos. Balansingiz: {$user->diamond_balance} olmos."]
                ]);
            }

            // 3. Deduct balance from user
            $user->diamond_balance -= $totalPrice;
            $user->save();

            // 4. Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);

            // 5. Update book stock & create order items
            foreach ($itemsToProcess as $proc) {
                $book = $proc['book'];
                $book->stock -= $proc['quantity'];
                $book->save();

                OrderItem::create([
                    'order_id' => $order->id,
                    'book_id' => $book->id,
                    'quantity' => $proc['quantity'],
                    'price' => $proc['price'],
                ]);
            }

            // 6. Log transaction
            DiamondTransaction::create([
                'user_id' => $user->id,
                'type' => 'book_purchase',
                'amount' => -$totalPrice,
                'reference_type' => 'Order',
                'reference_id' => $order->id,
                'balance_after' => $user->diamond_balance,
            ]);

            // 7. Send notification
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Yangi buyurtma qabul qilindi! 🍓',
                'body' => "Sizning #{$order->id} sonli kitob buyurtmangiz muvaffaqiyatli rasmiylashtirildi. Jami: {$totalPrice} olmos.",
                'is_read' => false,
            ]);

            return $order;
        });

        // 8. Return response with Telegram link
        $telegramText = "Assalomu alaykum! Ecliptoon do'konida yangi kitob buyurtma qildim. Buyurtma ID: #{$order->id}, Jami summa: {$order->total_price} olmos.";
        $telegramUrl = "https://t.me/yourtoxa?text=" . urlencode($telegramText);

        return response()->json([
            'message' => 'Buyurtmangiz muvaffaqiyatli rasmiylashtirildi.',
            'order_id' => $order->id,
            'total_price' => $order->total_price,
            'telegram_url' => $telegramUrl,
            'diamond_balance' => $user->fresh()->diamond_balance,
        ], 201);
    }

    /**
     * Get user's order history.
     */
    public function userOrders(Request $request)
    {
        $orders = $request->user()->orders()
            ->with('items.book:id,title,cover_path')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toISOString(),
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->book ? $item->book->title : 'O\'chirilgan kitob',
                            'cover_url' => ($item->book && $item->book->cover_path) ? asset('storage/' . $item->book->cover_path) : null,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }

    /**
     * Admin: Get all books.
     */
    public function adminIndex(Request $request)
    {
        $this->checkPermission($request, 'books');

        $books = Book::orderBy('created_at', 'desc')->get()->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'description' => $book->description,
                'price' => $book->price,
                'cover_url' => $book->cover_path ? asset('storage/' . $book->cover_path) : null,
                'cover_path' => $book->cover_path,
                'stock' => $book->stock,
                'created_at' => $book->created_at->toISOString(),
            ];
        });

        return response()->json($books);
    }

    /**
     * Admin: Store a new book.
     */
    public function store(Request $request)
    {
        $this->checkPermission($request, 'books');

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'cover' => 'nullable|image|max:5120',
        ]);

        $coverPath = null;
        if ($request->hasFile('cover')) {
            $coverPath = $request->file('cover')->store('books', 'public');
        }

        $book = Book::create([
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'cover_path' => $coverPath,
        ]);

        return response()->json([
            'message' => 'Kitob do\'konga muvaffaqiyatli qo\'shildi.',
            'book' => $book,
        ], 201);
    }

    /**
     * Admin: Update a book.
     */
    public function update($id, Request $request)
    {
        $this->checkPermission($request, 'books');
        $book = Book::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'cover' => 'nullable|image|max:5120',
        ]);

        $coverPath = $book->cover_path;
        if ($request->hasFile('cover')) {
            // Delete old file if exists
            if ($book->cover_path) {
                Storage::disk('public')->delete($book->cover_path);
            }
            $coverPath = $request->file('cover')->store('books', 'public');
        }

        $book->update([
            'title' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'cover_path' => $coverPath,
        ]);

        return response()->json([
            'message' => 'Kitob ma\'lumotlari yangilandi.',
            'book' => $book,
        ]);
    }

    /**
     * Admin: Delete a book.
     */
    public function destroy($id, Request $request)
    {
        $this->checkPermission($request, 'books');
        $book = Book::findOrFail($id);

        // Delete cover image
        if ($book->cover_path) {
            Storage::disk('public')->delete($book->cover_path);
        }

        $book->delete();

        return response()->json([
            'message' => 'Kitob do\'kondan o\'chirildi.'
        ]);
    }

    /**
     * Admin: Get all orders.
     */
    public function adminOrders(Request $request)
    {
        $this->checkPermission($request, 'orders');

        $orders = Order::with(['user:id,name,email', 'items.book:id,title,cover_path'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toISOString(),
                    'user' => $order->user ? [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                    ] : null,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'title' => $item->book ? $item->book->title : 'O\'chirilgan kitob',
                            'cover_url' => ($item->book && $item->book->cover_path) ? asset('storage/' . $item->book->cover_path) : null,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ];
                    }),
                ];
            });

        return response()->json($orders);
    }

    /**
     * Admin: Update order status.
     */
    public function updateOrderStatus($id, Request $request)
    {
        $this->checkPermission($request, 'orders');
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => 'required|string|in:pending,completed,cancelled',
        ]);

        $order->status = $request->status;
        $order->save();

        // Notify user if completed or cancelled
        if ($order->user_id) {
            $statusText = $order->status === 'completed' ? 'yakunlandi (yetkazildi) ✅' : 'bekor qilindi ❌';
            Notification::create([
                'user_id' => $order->user_id,
                'title' => "Buyurtma holati o'zgardi",
                'body' => "Sizning #{$order->id} sonli kitob buyurtmangiz holati: {$statusText}.",
                'is_read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Buyurtma holati muvaffaqiyatli yangilandi.',
            'order' => $order,
        ]);
    }

    /**
     * Helper to verify if user has a specific permission.
     */
    private function checkPermission(Request $request, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            abort(response()->json(['message' => 'Ushbu amalni bajarish uchun sizda huquq yo\'q.'], 403));
        }
        if ($user->role === 'admin') {
            return;
        }
        $hasPermission = \App\Models\RolePermission::where('role', $user->role)
            ->where('permission', $permission)
            ->exists();
        if (!$hasPermission) {
            abort(response()->json(['message' => 'Ushbu amalni bajarish uchun sizda huquq yo\'q.'], 403));
        }
    }
}

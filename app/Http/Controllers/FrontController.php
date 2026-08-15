<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FoodItem;
use App\Models\FoodCategory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Review; // Review model import kora holo
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class FrontController extends Controller
{
    // table_id ডিকোড করার জন্য একটি প্রাইভেট মেথড
    private function decodeTableId($id) {
        return base64_decode($id);
    }

    public function index($table_id)
    {
        $decoded_id = $this->decodeTableId($table_id);

        $featuredFoods = FoodItem::where('is_available', 1)
                                 ->where('is_featured', 1)
                                 ->orderBy('id', 'desc')
                                 ->paginate(12);

        return view('index', [
            'table_id' => $table_id,
            'decoded_table_id' => $decoded_id,
            'featuredFoods' => $featuredFoods
        ]);
    }

    public function menu(Request $request, $table_id)
    {
        $decoded_id = $this->decodeTableId($table_id);

        $categories = FoodCategory::with(['foodItems' => function($query) {
            $query->where('is_available', 1);
        }])->whereNull('parent_category_id')
          ->where('status', 1)
          ->paginate(3); // ক্যাটাগরি পেজিনেশন

        return view('menu', [
            'table_id' => $table_id,
            'decoded_table_id' => $decoded_id,
            'categories' => $categories
        ]);
    }

    public function categoryWiseProducts(Request $request, $category_id, $table_id)
    {
        $decoded_id = $this->decodeTableId($table_id);

        $category = FoodCategory::where('id', $category_id)->where('status', 1)->firstOrFail();

        $foodItems = $category->foodItems()->where('is_available', 1)->paginate(12);

        return view('category-wise-products', compact('category', 'foodItems', 'table_id', 'decoded_id'));
    }

    private function getCartKey($decoded_id)
    {
        return 'web_cart_table_' . $decoded_id;
    }

    public function cart($table_id)
{
    $decoded_id = $this->decodeTableId($table_id);
    $cartKey = $this->getCartKey($decoded_id);
    $cart = Session::get($cartKey, []);

    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }

    $taxSetting = DB::table('tax_settings')->first();
    $vat_rate = $taxSetting->vat_rate ?? 0;

    // সার্ভিস চার্জ শুধু Dine-In এর জন্য (QR অর্ডার সাধারণত Dine-In হয়)
    $service_charge_rate = $taxSetting->service_charge ?? 0;

    // নতুন লজিক: সার্ভিস চার্জ ও ভ্যাট ক্যালকুলেশন (রাউন্ড ফিগার)
    $service_amount = round(($subtotal * $service_charge_rate) / 100);
    $vat_amount = round((($subtotal + $service_amount) * $vat_rate) / 100);
    $grand_total = round($subtotal + $vat_amount + $service_amount);

    return view('cart', compact('table_id', 'decoded_id', 'cart', 'subtotal', 'vat_rate', 'vat_amount', 'service_charge_rate', 'service_amount', 'grand_total'));
}

    public function checkout(Request $request)
{
    $decoded_id = $this->decodeTableId($request->table_id);
    $cartKey = $this->getCartKey($decoded_id);
    $cart = Session::get($cartKey, []);

    if (count($cart) == 0) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Cart is empty!'
        ]);
    }

    DB::beginTransaction();

    try {
        // একই table এ আগে থেকে Pending / pending / QR / QR_Pending order থাকলে
        // নতুন order submit হবে না
        $pendingOrQrOrder = Order::where('table_id', $decoded_id)
            ->where(function ($query) {
                $query->whereRaw(
                    "REPLACE(REPLACE(LOWER(TRIM(status)), '-', '_'), ' ', '_') IN (?, ?, ?, ?, ?)",
                    ['pending', 'qr', 'qr_pending', 'qr_hold', 'hold']
                );
            })
            ->lockForUpdate()
            ->first();

        if ($pendingOrQrOrder) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'এই টেবিলে আগে থেকেই Pending/QR order আছে। আগের order complete না হওয়া পর্যন্ত নতুন order submit করা যাবে না।'
            ]);
        }

        $subtotal = 0;

        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }

        $taxSetting = DB::table('tax_settings')->first();

        $vat_rate = $taxSetting->vat_rate ?? 0;
        $service_charge_rate = $taxSetting->service_charge ?? 0;

        $service_charge = round(($subtotal * $service_charge_rate) / 100);
        $tax = round((($subtotal + $service_charge) * $vat_rate) / 100);
        $grand_total = round($subtotal + $tax + $service_charge);

        $order = Order::withoutEvents(function () use (
            $decoded_id,
            $subtotal,
            $tax,
            $service_charge,
            $grand_total,
            $request
        ) {
            return Order::create([
                'order_number'   => 'QR-' . time(),
                'table_id'       => $decoded_id,
                'order_type'     => 'Dine-In',
                'subtotal'       => $subtotal,
                'vat_tax'        => $tax,
                'service_charge' => $service_charge,
                'grand_total'    => $grand_total,
                'due'            => $grand_total,
                'status'         => 'QR_Pending',
                'notes'          => $request->notes,
                'order_time'     => now()
            ]);
        });

        foreach ($cart as $item) {
            DB::table('order_details')->insert([
                'order_id'     => $order->id,
                'product_id'   => $item['food_id'],
                'product_name' => $item['name'],
                'quantity'     => $item['qty'],
                'price'        => $item['price'],
                'subtotal'     => $item['price'] * $item['qty'],
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        Session::forget($cartKey);

        DB::commit();

        return response()->json([
            'status'  => 'success',
            'message' => 'Order placed successfully!'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

    public function addToCart(Request $request)
    {
        $decoded_id = $this->decodeTableId($request->table_id);
        $cartKey = $this->getCartKey($decoded_id);
        $cart = Session::get($cartKey, []);

        $food = FoodItem::find($request->food_id);
        if (!$food) return response()->json(['status' => 'error', 'message' => 'Food not found']);

        $price = $food->discount_price > 0 ? $food->discount_price : $food->base_price;

        if (isset($cart[$food->id])) {
            $cart[$food->id]['qty'] += 1;
        } else {
            $cart[$food->id] = [
                'food_id' => $food->id,
                'name' => $food->name,
                'qty' => 1,
                'price' => $price,
                'image' => $food->main_image
            ];
        }

        Session::put($cartKey, $cart);
        return response()->json(['status' => 'success', 'cart_count' => count($cart)]);
    }

    public function updateCartQty(Request $request)
    {
        $decoded_id = $this->decodeTableId($request->table_id);
        $cartKey = $this->getCartKey($decoded_id);
        $cart = Session::get($cartKey, []);

        if (isset($cart[$request->food_id])) {
            if ($request->action == 'plus') {
                $cart[$request->food_id]['qty'] += 1;
            } elseif ($request->action == 'minus') {
                if ($cart[$request->food_id]['qty'] > 1) {
                    $cart[$request->food_id]['qty'] -= 1;
                } else {
                    unset($cart[$request->food_id]);
                }
            }
            Session::put($cartKey, $cart);
        }
        return response()->json(['status' => 'success']);
    }

    public function order($table_id)
    {
        $decoded_id = $this->decodeTableId($table_id);

        // Updated Logic: Order Completed hobar 5 minutes por hide hoye jabe
        $orders = Order::with(['orderDetails', 'review'])
                    ->where('table_id', $decoded_id)
                    ->where('order_number', 'like', 'QR-%')
                    ->where(function ($query) {
                        $query->where('status', '!=', 'Completed')
                              ->orWhere(function ($q) {
                                  $q->where('status', 'Completed')
                                    ->where('updated_at', '>=', now()->subMinutes(5));
                              });
                    })
                    ->orderBy('id', 'desc')
                    ->get();

        return view('order', compact('table_id', 'decoded_id', 'orders'));
    }

    public function callWaiter(Request $request)
    {
        $decoded_id = $this->decodeTableId($request->table_id);

        $waiterCall = [
            'table_id' => $decoded_id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('waiter_calls', 'branch_id')) {
            $waiterCall['branch_id'] = \App\Models\Table::query()
                ->whereKey($decoded_id)
                ->value('branch_id');
        }

        DB::table('waiter_calls')->insert($waiterCall);

        return response()->json(['status' => 'success']);
    }

    // New Method: Review details submit handling via AJAX
   // New Method: Review details submit handling via AJAX
    public function submitReview(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating'   => 'required|integer|min:1|max:5', // রেটিং ভ্যালিডেশন
            'review'   => 'required|string|max:1000'
        ]);

        $reviewData = [
            'order_id' => $request->order_id,
            'rating'   => $request->rating,  // রেটিং সেভ হচ্ছে
            'review'   => $request->review
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('reviews', 'branch_id')) {
            $reviewData['branch_id'] = \App\Models\Order::query()
                ->whereKey($request->order_id)
                ->value('branch_id');
        }

        Review::create($reviewData);

        return response()->json(['status' => 'success', 'message' => 'Thank you for your review!']);
    }
}

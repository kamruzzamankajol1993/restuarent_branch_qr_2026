<?php

namespace App\Http\Controllers;

use App\Models\FoodCategory;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\Table;
use App\Services\QrTableContext;
use App\Services\QrBranchSettingResolver;
use App\Models\TaxSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class FrontController extends Controller
{
    private function tableFromReference(string $reference): Table
    {
        return app(QrTableContext::class)->resolve($reference);
    }

    // Kept for existing view variable names. It now resolves token-first and legacy Base64 second.
    private function decodeTableId($reference)
    {
        return $this->tableFromReference((string) $reference)->id;
    }

    private function branchId(Table $table): ?int
    {
        return Schema::hasColumn('tables', 'branch_id') ? (int) $table->branch_id : null;
    }

    private function makeQrReference(?int $branchId): string
    {
        $branchPart = $branchId ?: 'MAIN';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $reference = 'QR-' . $branchPart . '-' . strtoupper(bin2hex(random_bytes(4)));

            $query = Order::withoutGlobalScopes()->where('order_number', $reference);
            if (Schema::hasColumn('orders', 'qr_reference')) {
                $query->orWhere('qr_reference', $reference);
            }

            if (!$query->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException('Unable to allocate a unique QR order reference. Please retry.');
    }

    private function branchFoodQuery(?int $branchId)
    {
        $query = FoodItem::query();
        if (Schema::hasColumn('food_items', 'branch_id') && $branchId) {
            $query->where('branch_id', $branchId);
        }
        return $query;
    }

    private function branchCategoryQuery(?int $branchId)
    {
        $query = FoodCategory::query();
        if (Schema::hasColumn('food_categories', 'branch_id') && $branchId) {
            $query->where('branch_id', $branchId);
        }
        return $query;
    }

    private function branchTaxSetting(?int $branchId): TaxSetting
    {
        $setting = app(QrBranchSettingResolver::class)->get(TaxSetting::class, $branchId, false);
        if (!$setting) {
            abort(503, 'Tax/settings are not configured for this branch.');
        }

        return $setting;
    }

    private function canonicalReference(Table $table): string
    {
        if (Schema::hasColumn('tables', 'qr_token') && filled($table->qr_token)) {
            return (string) $table->qr_token;
        }

        return base64_encode((string) $table->id);
    }

    private function canonicalRedirect(string $routeName, string $reference, Table $table, array $parameters = [])
    {
        $canonical = $this->canonicalReference($table);
        if (hash_equals($canonical, $reference)) {
            return null;
        }

        $parameters['table_id'] = $canonical;
        // Temporary redirect is intentional. If a table token is regenerated later, an old
        // printed Base64 QR will resolve again and redirect to the newest token instead of a
        // browser permanently caching an obsolete destination.
        return redirect()->route($routeName, $parameters);
    }

    private function refreshedCart(Table $table): array
    {
        $cartKey = $this->getCartKey($table->id);
        $cart = Session::get($cartKey, []);
        if (!$cart) {
            return [];
        }

        $branchId = $this->branchId($table);
        $validFoodIds = collect($cart)->pluck('food_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $foods = $this->branchFoodQuery($branchId)
            ->whereIn('id', $validFoodIds)
            ->where('is_available', 1)
            ->get()
            ->keyBy('id');

        $refreshed = [];
        foreach ($cart as $key => $item) {
            $food = $foods->get((int) ($item['food_id'] ?? $key));
            if (!$food) {
                continue;
            }

            $price = (float) ($food->discount_price > 0 ? $food->discount_price : $food->base_price);
            $refreshed[$food->id] = [
                'food_id' => $food->id,
                'name' => $food->name,
                'qty' => max(1, (int) ($item['qty'] ?? 1)),
                'price' => $price,
                'image' => $food->main_image,
            ];
        }

        Session::put($cartKey, $refreshed);
        return $refreshed;
    }

    public function index($table_id)
    {
        $table = $this->tableFromReference((string) $table_id);
        if ($redirect = $this->canonicalRedirect('qr.home', (string) $table_id, $table)) {
            return $redirect;
        }
        $table_id = $this->canonicalReference($table);
        $decoded_id = $table->id;
        $branchId = $this->branchId($table);

        $featuredFoods = $this->branchFoodQuery($branchId)
            ->where('is_available', 1)
            ->where('is_featured', 1)
            ->orderBy('id', 'desc')
            ->paginate(12);

        return view('index', compact('table_id', 'decoded_id', 'featuredFoods'))
            ->with('decoded_table_id', $decoded_id);
    }

    public function menu(Request $request, $table_id)
    {
        $table = $this->tableFromReference((string) $table_id);
        if ($redirect = $this->canonicalRedirect('qr.menu', (string) $table_id, $table)) {
            return $redirect;
        }
        $table_id = $this->canonicalReference($table);
        $decoded_id = $table->id;
        $branchId = $this->branchId($table);

        $categories = $this->branchCategoryQuery($branchId)
            ->with(['foodItems' => function ($query) use ($branchId) {
                if (Schema::hasColumn('food_items', 'branch_id')) {
                    $query->where('branch_id', $branchId);
                }
                $query->where('is_available', 1);
            }])
            ->whereNull('parent_category_id')
            ->where('status', 1)
            ->paginate(3);

        return view('menu', [
            'table_id' => $table_id,
            'decoded_table_id' => $decoded_id,
            'categories' => $categories,
        ]);
    }

    public function categoryWiseProducts(Request $request, $category_id, $table_id)
    {
        $table = $this->tableFromReference((string) $table_id);
        if ($redirect = $this->canonicalRedirect('qr.category', (string) $table_id, $table, ['category_id' => $category_id])) {
            return $redirect;
        }
        $table_id = $this->canonicalReference($table);
        $decoded_id = $table->id;
        $branchId = $this->branchId($table);

        $category = $this->branchCategoryQuery($branchId)
            ->whereKey($category_id)
            ->where('status', 1)
            ->firstOrFail();

        $foodItems = $category->foodItems()
            ->when(Schema::hasColumn('food_items', 'branch_id'), fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_available', 1)
            ->paginate(12);

        return view('category-wise-products', compact('category', 'foodItems', 'table_id', 'decoded_id'))->with('decoded_table_id', $decoded_id);
    }

    private function getCartKey($decoded_id)
    {
        return 'web_cart_table_' . $decoded_id;
    }

    public function cart($table_id)
    {
        $table = $this->tableFromReference((string) $table_id);
        if ($redirect = $this->canonicalRedirect('qr.cart', (string) $table_id, $table)) {
            return $redirect;
        }
        $table_id = $this->canonicalReference($table);
        $decoded_id = $table->id;
        $cart = $this->refreshedCart($table);

        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }

        $taxSetting = $this->branchTaxSetting($this->branchId($table));
        $vat_rate = $taxSetting->vat_rate ?? 0;
        $service_charge_rate = $taxSetting->service_charge ?? 0;
        $service_amount = round(($subtotal * $service_charge_rate) / 100);
        $vat_amount = round((($subtotal + $service_amount) * $vat_rate) / 100);
        $grand_total = round($subtotal + $vat_amount + $service_amount);

        return view('cart', compact('table_id', 'decoded_id', 'cart', 'subtotal', 'vat_rate', 'vat_amount', 'service_charge_rate', 'service_amount', 'grand_total'))->with('decoded_table_id', $decoded_id);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'table_id' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $table = $this->tableFromReference((string) $request->table_id);
        $decoded_id = $table->id;
        $branchId = $this->branchId($table);
        $cartKey = $this->getCartKey($decoded_id);
        $cart = $this->refreshedCart($table);

        if (count($cart) === 0) {
            return response()->json(['status' => 'error', 'message' => 'Cart is empty!']);
        }

        DB::beginTransaction();
        try {
            $pendingOrQrOrder = Order::where('table_id', $decoded_id)
                ->when(Schema::hasColumn('orders', 'branch_id') && $branchId, fn ($query) => $query->where('branch_id', $branchId))
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
                    'status' => 'error',
                    'message' => 'এই টেবিলে আগে থেকেই Pending/QR order আছে। আগের order complete না হওয়া পর্যন্ত নতুন order submit করা যাবে না।',
                ]);
            }

            $subtotal = 0;
            foreach ($cart as $item) {
                $subtotal += $item['price'] * $item['qty'];
            }

            $taxSetting = $this->branchTaxSetting($branchId);
            $vat_rate = $taxSetting->vat_rate ?? 0;
            $service_charge_rate = $taxSetting->service_charge ?? 0;
            $service_charge = round(($subtotal * $service_charge_rate) / 100);
            $tax = round((($subtotal + $service_charge) * $vat_rate) / 100);
            $grand_total = round($subtotal + $tax + $service_charge);

            $qrReference = $this->makeQrReference($branchId);
            $order = Order::withoutEvents(function () use ($decoded_id, $branchId, $subtotal, $tax, $service_charge, $grand_total, $request, $qrReference) {
                $data = [
                    'order_number' => $qrReference,
                    'table_id' => $decoded_id,
                    'order_type' => 'Dine-In',
                    'subtotal' => $subtotal,
                    'vat_tax' => $tax,
                    'service_charge' => $service_charge,
                    'grand_total' => $grand_total,
                    'due' => $grand_total,
                    'status' => 'QR_Pending',
                    'notes' => $request->notes,
                    'order_time' => now(),
                ];

                // Order::withoutEvents bypasses the Phase-1 model hook, so branch ownership must be explicit here.
                if (Schema::hasColumn('orders', 'branch_id')) {
                    $data['branch_id'] = $branchId;
                }
                if (Schema::hasColumn('orders', 'qr_reference')) {
                    $data['qr_reference'] = $qrReference;
                }

                return Order::create($data);
            });

            // withoutEvents() skips the Order creating hook, so create the public feedback token explicitly.
            $order->ensureFeedbackToken();

            app(AuditLogger::class)->log(
                'qr.order_created',
                $branchId,
                'QR order created from Table #' . $decoded_id,
                Order::class,
                (int) $order->id,
                ['qr_reference' => $qrReference, 'table_id' => $decoded_id]
            );

            foreach ($cart as $item) {
                DB::table('order_details')->insert([
                    'order_id' => $order->id,
                    'product_id' => $item['food_id'],
                    'product_name' => $item['name'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['price'] * $item['qty'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Session::forget($cartKey);
            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Order placed successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'table_id' => ['required', 'string'],
            'food_id' => ['required', 'integer'],
        ]);

        $table = $this->tableFromReference((string) $request->table_id);
        $decoded_id = $table->id;
        $branchId = $this->branchId($table);
        $cartKey = $this->getCartKey($decoded_id);
        $cart = Session::get($cartKey, []);

        $food = $this->branchFoodQuery($branchId)->whereKey($request->food_id)->where('is_available', 1)->first();
        if (!$food) {
            return response()->json(['status' => 'error', 'message' => 'Food not found for this branch'], 404);
        }

        $price = $food->discount_price > 0 ? $food->discount_price : $food->base_price;
        if (isset($cart[$food->id])) {
            $cart[$food->id]['qty'] += 1;
        } else {
            $cart[$food->id] = [
                'food_id' => $food->id,
                'name' => $food->name,
                'qty' => 1,
                'price' => $price,
                'image' => $food->main_image,
            ];
        }

        Session::put($cartKey, $cart);
        return response()->json(['status' => 'success', 'cart_count' => count($cart)]);
    }

    public function updateCartQty(Request $request)
    {
        $request->validate([
            'table_id' => ['required', 'string'],
            'food_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['plus', 'minus'])],
        ]);

        $table = $this->tableFromReference((string) $request->table_id);
        $branchId = $this->branchId($table);
        $food = $this->branchFoodQuery($branchId)
            ->whereKey($request->food_id)
            ->where('is_available', 1)
            ->first();

        if (!$food) {
            return response()->json(['status' => 'error', 'message' => 'Food is no longer available for this branch.'], 404);
        }

        $cartKey = $this->getCartKey($table->id);
        $cart = $this->refreshedCart($table);

        if (isset($cart[$food->id])) {
            if ($request->action === 'plus') {
                $cart[$food->id]['qty'] += 1;
            } elseif ($cart[$food->id]['qty'] > 1) {
                $cart[$food->id]['qty'] -= 1;
            } else {
                unset($cart[$food->id]);
            }
            Session::put($cartKey, $cart);
        }

        return response()->json(['status' => 'success', 'cart_count' => count($cart)]);
    }

    public function order($table_id)
    {
        $table = $this->tableFromReference((string) $table_id);
        if ($redirect = $this->canonicalRedirect('qr.orders', (string) $table_id, $table)) {
            return $redirect;
        }
        $table_id = $this->canonicalReference($table);
        $decoded_id = $table->id;

        $orders = Order::with(['orderDetails', 'review'])
            ->where('table_id', $decoded_id)
            ->when(Schema::hasColumn('orders', 'branch_id') && $this->branchId($table), fn ($query) => $query->where('branch_id', $this->branchId($table)))
            ->where(function ($referenceQuery) {
                $referenceQuery->where('order_number', 'like', 'QR-%');
                if (Schema::hasColumn('orders', 'qr_reference')) {
                    $referenceQuery->orWhere('qr_reference', 'like', 'QR-%');
                }
            })
            ->where(function ($query) {
                $query->where('status', '!=', 'Completed')
                    ->orWhere(function ($q) {
                        $q->where('status', 'Completed')->where('updated_at', '>=', now()->subMinutes(5));
                    });
            })
            ->orderBy('id', 'desc')
            ->get();

        $orders->each(function (Order $order) {
            $order->ensureFeedbackToken();
        });

        return view('order', compact('table_id', 'decoded_id', 'orders'))->with('decoded_table_id', $decoded_id);
    }

    public function feedbackForm(string $token)
    {
        // Public feedback deliberately does not eager-load the User relation/model.
        $order = Order::with('waiter')
            ->where('feedback_token', $token)
            ->firstOrFail();

        // Prefer the assigned waiter. For older/non-waiter orders, read only the
        // name value directly from the users table; App\Models\User is never loaded.
        $serverName = optional($order->waiter)->name;
        if (blank($serverName) && !empty($order->user_id) && Schema::hasTable('users')) {
            $serverName = DB::table('users')->where('id', $order->user_id)->value('name');
        }

        $existingFeedback = Review::query()->where('order_id', $order->id)->first();
        $feedbackSubmitted = $existingFeedback
            && !is_null($existingFeedback->overall_experience_rating);

        $branchId = Schema::hasColumn('orders', 'branch_id') ? (int) $order->branch_id : null;
        $restaurant = app(QrBranchSettingResolver::class)->get(\App\Models\RestaurantSetting::class, $branchId, true);
        $restaurantSettingName = $restaurant?->name ?? 'Restaurant';

        return view('feedback', compact(
            'order',
            'feedbackSubmitted',
            'serverName',
            'restaurantSettingName'
        ));
    }

    public function submitFeedback(Request $request, string $token)
    {
        // The token resolves to exactly one order. Feedback is persisted against order_id.
        $order = Order::query()->where('feedback_token', $token)->firstOrFail();

        $existingFeedback = Review::query()->where('order_id', $order->id)->first();
        if ($existingFeedback && !is_null($existingFeedback->overall_experience_rating)) {
            return redirect()->route('feedback.form', ['token' => $token])
                ->with('feedback_info', 'Feedback for this order has already been submitted.');
        }

        $validated = $request->validate([
            'server_name'               => ['nullable', 'string', 'max:255'],
            'food_rating'               => ['required', 'integer', 'between:1,4'],
            'service_rating'            => ['required', 'integer', 'between:1,4'],
            'cleanliness_rating'        => ['required', 'integer', 'between:1,4'],
            'atmosphere_rating'         => ['required', 'integer', 'between:1,4'],
            'value_rating'              => ['required', 'integer', 'between:1,4'],
            'overall_experience_rating' => ['required', 'integer', 'between:1,4'],
            'comment'                   => ['nullable', 'string', 'max:2000'],
            'heard_from'                => ['nullable', 'array'],
            'heard_from.*'              => [Rule::in(['family_friends', 'social_media', 'passing_by'])],
            'guest_name'                => ['nullable', 'string', 'max:255'],
            'guest_email'               => ['nullable', 'email', 'max:255'],
            'guest_phone'               => ['nullable', 'string', 'max:30'],
        ]);

        $ratingLabels = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great'];
        $summaryParts = [
            'Food: ' . $ratingLabels[$validated['food_rating']],
            'Service: ' . $ratingLabels[$validated['service_rating']],
            'Cleanliness: ' . $ratingLabels[$validated['cleanliness_rating']],
            'Atmosphere: ' . $ratingLabels[$validated['atmosphere_rating']],
            'Value: ' . $ratingLabels[$validated['value_rating']],
            'Overall Experience: ' . $ratingLabels[$validated['overall_experience_rating']],
        ];

        if (!empty($validated['server_name'])) {
            $summaryParts[] = 'Server: ' . $validated['server_name'];
        }

        $reviewData = [
            'rating'                    => $validated['overall_experience_rating'],
            'food_rating'               => $validated['food_rating'],
            'service_rating'            => $validated['service_rating'],
            'cleanliness_rating'        => $validated['cleanliness_rating'],
            'atmosphere_rating'         => $validated['atmosphere_rating'],
            'value_rating'              => $validated['value_rating'],
            'overall_experience_rating' => $validated['overall_experience_rating'],
            'review'                    => implode(' | ', $summaryParts),
            'comment'                   => $validated['comment'] ?? null,
            'heard_from'                => json_encode(array_values($validated['heard_from'] ?? [])),
            'guest_name'                => $validated['guest_name'] ?? null,
            'guest_email'               => $validated['guest_email'] ?? null,
            'guest_phone'               => $validated['guest_phone'] ?? null,
        ];

        $branchId = Schema::hasColumn('orders', 'branch_id') ? (int) $order->branch_id : null;
        if (Schema::hasColumn('reviews', 'branch_id')) {
            $reviewData['branch_id'] = $branchId;
        }

        $review = Review::query()->updateOrCreate(
            ['order_id' => $order->id],
            $reviewData
        );

        app(AuditLogger::class)->log(
            'qr.feedback_submitted',
            $branchId,
            'Customer submitted structured feedback for a QR order.',
            Review::class,
            (int) $review->id,
            ['order_id' => (int) $order->id]
        );

        return redirect()->route('feedback.form', ['token' => $token])
            ->with('feedback_success', 'Thank you for your time and input. We hope to see you soon again!');
    }

    public function callWaiter(Request $request)
    {
        $request->validate(['table_id' => ['required', 'string']]);
        $table = $this->tableFromReference((string) $request->table_id);

        $waiterCall = [
            'table_id' => $table->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('waiter_calls', 'branch_id')) {
            $waiterCall['branch_id'] = $this->branchId($table);
        }

        $waiterCallId = DB::table('waiter_calls')->insertGetId($waiterCall);
        app(AuditLogger::class)->log(
            'qr.waiter_called',
            $this->branchId($table),
            'Customer called waiter from Table #' . $table->id,
            'waiter_calls',
            (int) $waiterCallId,
            ['table_id' => (int) $table->id]
        );
        return response()->json(['status' => 'success']);
    }

    public function submitReview(Request $request)
    {
        $validated = $request->validate([
            'table_id' => ['required', 'string'],
            'order_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['required', 'string', 'max:1000'],
        ]);

        $table = $this->tableFromReference((string) $validated['table_id']);
        $branchId = $this->branchId($table);

        $order = Order::query()
            ->whereKey($validated['order_id'])
            ->where('table_id', $table->id)
            ->when(Schema::hasColumn('orders', 'branch_id') && $branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'Completed')
            ->firstOrFail();

        if (Review::query()->where('order_id', $order->id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'A review has already been submitted for this order.'], 422);
        }

        $reviewData = [
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'review' => $validated['review'],
        ];

        if (Schema::hasColumn('reviews', 'branch_id')) {
            $reviewData['branch_id'] = $branchId;
        }

        $review = Review::create($reviewData);
        app(AuditLogger::class)->log(
            'qr.review_created',
            $branchId,
            'Customer submitted a QR order review.',
            Review::class,
            (int) $review->id,
            ['order_id' => (int) $order->id, 'table_id' => (int) $table->id, 'rating' => (int) $validated['rating']]
        );
        return response()->json(['status' => 'success', 'message' => 'Thank you for your review!']);
    }
}

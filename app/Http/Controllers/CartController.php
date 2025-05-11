<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CartService $cartService)
    {
        return Inertia::render('Cart/Index', [
            'cartItems' => $cartService->getCartItemsGrouped(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Product $product, CartService $cartService)
    {
        // Merge missing quantity if not provided
        $request->mergeIfMissing([
            'quantity' => 1
        ]);

        // Validate input data
        $data = $request->validate([
            'option_ids' => ['nullable', 'array'], // Validate option_ids as array
            'quantity' => ['required', 'integer', 'min:1'], // Ensure quantity is a positive integer
        ]);

        // Add item to the cart
        $cartService->addItemToCart(
            $product, 
            $data['quantity'], 
            $data['option_ids'] ?: [] // Default to empty array if no options
        );

        // Return back with success message
        return back()->with('success', 'Product added to cart successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product, CartService $cartService)
    {
        // Validate input data for quantity
        $request->validate([
            'quantity' => ['integer', 'min:1'], // Ensure quantity is a positive integer
        ]);

        $optionIds = $request->input('option_ids') ?: []; // Default to empty array if no options
        $quantity = $request->input('quantity');

        // Update item quantity in the cart
        $cartService->updateItemQuantity($product->id, $quantity, $optionIds);

        // Return back with success message
        return back()->with('success', 'Quantity was updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Product $product, CartService $cartService)
    {
        $optionIds = $request->input('option_ids'); // Get option_ids to identify product variation

        // Remove item from the cart
        $cartService->removeItemFromCart($product->id, $optionIds);

        // Return back with success message
        return back()->with('success', 'Product was removed from the cart.');
    }

    /**
     * Handle the checkout process.
     */
    public function checkout(Request $request, CartService $cartService)
    {
        // Set Stripe API key
        \Stripe\Stripe::setApiKey(config('app.stripe_secret_key'));

        // Get vendor ID (if provided)
        $vendorId = $request->input('vendor_id');

        // Retrieve cart items grouped by vendor (using the CartService)
        $allCartItems = $cartService->getCartItemsGrouped();

        // Start a database transaction
        DB::beginTransaction();
        try {
            // If vendor_id is provided, checkout only that vendor's cart items
            $checkoutCartItems = $vendorId ? [$allCartItems[$vendorId]] : $allCartItems;
            $orders = [];
            $lineItems =[];

            // Iterate through cart items to create orders and prepare Stripe line items
            foreach ($checkoutCartItems as $item) {
                $user = $item['user'];
                $cartItems = $item['items'];

                // Create an order
                $order = Order::create([
                    'stripe_session_id' => null, // Placeholder for Stripe session ID
                    'user_id' => $request->user()->id,
                    'vendor_user_id' => $user['id'],
                    'total_price' => $item['totalPrice'],
                    'status' => OrderStatusEnum::Draft->value,
                ]);
                $orders[] = $order;

                // Iterate through cart items to create order items and Stripe line items
                foreach ($cartItems as $cartItem) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem['product_id'],
                        'quantity' => $cartItem['quantity'],
                        'price' => $cartItem['price'],
                        'variation_type_option_ids' => $cartItem['option_ids'], // Store option IDs for product variations
                    ]);

                    // Generate product description for line item (optional)
                    $description = collect($cartItem['options'])->map(function ($item) {
                        return "{$item['type']['name']}: {$item['name']}";
                    })->implode(', ');

                    // Add product to Stripe line items
                    $lineItem = [
                        'price_data' => [
                            'currency' => config('app.currency'),
                            'product_data' => [
                                'name' => $cartItem['title'],
                                'images' => [$cartItem['image']],
                            ],
                            'unit_amount' => $cartItem['price'] * 100, // Convert price to cents
                        ],
                        'quantity' => $cartItem['quantity'],
                    ];
                    if ($description) {
                        $lineItem['price_data']['product_data']['description'] = $description;
                    }
                    $lineItems[] = $lineItem;
                }
            }

            // Create Stripe checkout session
            $session = \Stripe\Checkout\Session::create([
                'customer_email' => $request->user()->email,
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => route('stripe.success', []) . "?session_id={CHECKOUT_SESSION_ID}",
                'cancel_url' => route('stripe.failure', []),
            ]);

            // Save Stripe session ID for each order
            foreach ($orders as $order) {
                $order->stripe_session_id = $session->id;
                $order->save();
            }

            // Commit the transaction
            DB::commit();

            // Redirect to Stripe checkout page
            return redirect($session->url);

        } catch (\Exception $e) {
            // Handle error: log the exception and rollback transaction
            Log::error($e);
            DB::rollBack();

            return back()->with('error', $e->getMessage() ?: 'Something went wrong');
        }
    }
}

<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\VariationTypeOption;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartService
{
    private ?array $cachedCartItems = null;

    protected const COOKIE_NAME = 'cartItems';
    protected const COOKIE_LIFETIME = 60 * 24 * 365; // 1 year

    public function addItemToCart(Product $product, int $quantity = 1, $optionIds = [null])
    {
        if (!$optionIds) {
            $optionIds = $product->getFirstOptionsMap();
        }

        $price = $product->getPriceForOptions($optionIds);

        if(Auth::check()) {
            $this->saveItemToDatabase($product->id, $quantity, $price, $optionIds);
        } else {
            $this->saveItemToCookies($product->id, $quantity, $price, $optionIds);
        }
    }

    public function updateItemQuantity(int $productId, int $quantity, $optionIds = null)
    {
        if (\Auth::check()) {
            $this->updateItemQuantityInDatabase($productId, $quantity, $optionIds);
        } else {
            $this->updateItemQuantityInCookies($productId, $quantity, $optionIds);
        }
    }

    public function removeItemFromCart(int $productId, $optionIds = null)
    {
        if (\Auth::check()) {
            $this->removeItemFromDatabase($productId, $optionIds);
        } else {
            $this->removeItemFromCookies($productId, $optionIds);
        }
    }

    public function getCartItems(): array
    {
       try {
            if ($this->cachedCartItems === null) {
                if (Auth::check()) {
                    $cartItems = $this->getCartItemsFromDatabase();
                } else {
                    $cartItems = $this->getCartItemsFromCookies();
                }

                $productIds = collect($cartItems)->map(fn($item) => $item['product_id']);
                $products = Product::whereIn('id', $productIds)
                    ->with('user.vendor')
                    ->forWebsite()
                    ->get()
                    ->keyBy('id');

                $cartItemData = [];
                foreach ($cartItems as $cartItem) {
                    $product = data_get($products, $cartItem['product_id']);
                    if (!$product) continue;

                    $optionInfo = [];
                    $options = VariationTypeOption::with('variationType')
                        ->whereIn('id', $cartItem['option_ids'])
                        ->get()
                        ->keyBy('id');

                    $imageUrl = null;

                    foreach ($cartItem['option_ids'] as $option_id) {
                        $option = data_get($options, $option_id);
                        if (!$imageUrl) {
                            $imageUrl = $option->getFirstMediaUrl('images', 'small');
                        }
                        $optionInfo[] = [
                            'id' => $option_id,
                            'name' => $option->name,
                            'type' => [
                                'id' => $option->variationType->id,
                                'name' => $option->variationType->name,
                            ],
                        ];
                    }

                    $cartItemData[] = [
                        'id' => $cartItem['id'],
                        'product_id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'price' => $cartItem['price'],
                        'quantity' => $cartItem['quantity'],
                        'option_ids' => $cartItem['option_ids'],
                        'options' => $optionInfo,
                        'image' => $imageUrl ?: $product->getFirstMediaUrl('images', 'small'),
                        'user' => [
                            'id' => $product->created_by,
                            'name' => $product->user->vendor->store_name,
                        ],
                    ];
                }

                $this->cachedCartItems = $cartItemData;
            }

            return $this->cachedCartItems;
       } catch (\Exception $e) {
            Log::error($e->getMessage() . PHP_EOL . $e->getTraceAsString());
       }

        return [];
    }

    public function getTotalQuantity(): int
    {
        return array_sum(array_column($this->getCartItems(), 'quantity'));
    }

    public function getTotalPrice(): float
    {
        return array_sum(array_map(fn($item) => $item['quantity'] * $item['price'], $this->getCartItems()));
    }

    protected function updateItemQuantityInDatabase(int $productId, int $quantity, array $optionIds)
    {
        $userId = Auth::id();
        ksort($optionIds); // Ensure consistent ordering

        // ✅ FIX: use whereJsonContains for PostgreSQL JSONB
        $cartItem = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereJsonContains('variation_type_option_ids', $optionIds)
            ->first();

        if ($cartItem) {
            $cartItem->update(['quantity' => $quantity]);
        }
    }

    protected function updateItemQuantityInCookies(int $productId, int $quantity, array $optionIds)
    {
        $cartItems = $this->getCartItemsFromCookies();
        ksort($optionIds);

        $itemKey = $productId . '_' . json_encode($optionIds);

        if (isset($cartItems[$itemKey])) {
            $cartItems[$itemKey]['quantity'] = $quantity;
        }

        Cookie::queue(self::COOKIE_NAME, json_encode($cartItems), self::COOKIE_LIFETIME);
    }

    protected function saveItemToDatabase(int $productId, int $quantity, $price, array $optionIds)
    {
        $userId = Auth::id();
        ksort($optionIds); // Ensure consistent ordering

        // ✅ FIX: use whereJsonContains instead of raw JSONB comparison
        $cartItem = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereJsonContains('variation_type_option_ids', $optionIds)
            ->first();

        if ($cartItem) {
            $cartItem->update([
                'quantity' => DB::raw('quantity + ' . $quantity),
            ]);
        } else {
            CartItem::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'variation_type_option_ids' => $optionIds,
            ]);
        }
    }

    protected function saveItemToCookies(int $productId, int $quantity, $price, array $optionIds)
    {
        $cartItems = $this->getCartItemsFromCookies();
        ksort($optionIds);

        $itemKey = $productId . '_' . json_encode($optionIds);

        if (isset($cartItems[$itemKey])) {
            $cartItems[$itemKey]['quantity'] += $quantity;
            $cartItems[$itemKey]['price'] = $price;
        } else {
            $cartItems[$itemKey] = [
                'id' => \Str::uuid(),
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
                'option_ids' => $optionIds,
            ];
        }

        Cookie::queue(self::COOKIE_NAME, json_encode($cartItems), self::COOKIE_LIFETIME);
    }

    protected function removeItemFromDatabase(int $productId, array $optionIds)
    {
        $userId = Auth::id();
        ksort($optionIds); // Keep order consistent

        // ✅ FIX: use whereJsonContains for safe JSONB matching
        CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereJsonContains('variation_type_option_ids', $optionIds)
            ->delete();
    }

    protected function removeItemFromCookies(int $productId, array $optionIds)
    {
        $cartItems = $this->getCartItemsFromCookies();
        ksort($optionIds);

        $cartKey = $productId . '_' . json_encode($optionIds);
        unset($cartItems[$cartKey]);

        Cookie::queue(self::COOKIE_NAME, json_encode($cartItems), self::COOKIE_LIFETIME);
    }

    protected function getCartItemsFromDatabase()
    {
        $userId = Auth::id();

        return CartItem::where('user_id', $userId)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'option_ids' => $item->variation_type_option_ids,
            ])
            ->toArray();
    }

    protected function getCartItemsFromCookies()
    {
        return json_decode(Cookie::get(self::COOKIE_NAME, '[]'), true);
    }

    public function getCartItemsGrouped(): array
    {
        return collect($this->getCartItems())
            ->groupBy(fn($item) => $item['user']['id'])
            ->map(fn($items, $userId) => [
                'user' => $items->first()['user'],
                'items' => $items->toArray(),
                'totalQuantity' => $items->sum('quantity'),
                'totalPrice' => $items->sum(fn ($item) => $item['price'] * $item['quantity']),
            ])
            ->toArray();
    }

    public function moveCartItemsToDatabase($userId)
    {
        $cartItems = $this->getCartItemsFromCookies();

        foreach ($cartItems as $cartItem) {
            ksort($cartItem['option_ids']); // Ensure ordering before comparing

            // ✅ FIX: use whereJsonContains for merging
            $existingItem = CartItem::where('user_id', $userId)
                ->where('product_id', $cartItem['product_id'])
                ->whereJsonContains('variation_type_option_ids', $cartItem['option_ids'])
                ->first();

            if ($existingItem) {
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $cartItem['quantity'],
                    'price' => $cartItem['price'],
                ]);
            } else {
                CartItem::create([
                    'user_id' => $userId,
                    'product_id' => $cartItem['product_id'],
                    'quantity' => $cartItem['quantity'],
                    'price' => $cartItem['price'],
                    'variation_type_option_ids' => $cartItem['option_ids'],
                ]);
            }
        }

        // Clear cookie after merging
        Cookie::queue(self::COOKIE_NAME, '', -1);
    }
}

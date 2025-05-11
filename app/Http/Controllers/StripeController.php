<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatusEnum;
use App\Http\Resources\OrderViewResource;
use App\Mail\CheckoutCompleted;
use App\Mail\NewOrderMail;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class StripeController extends Controller
{
    public function success(Request $request)
    {
        $user = auth()->user();
        $session_id = $request->get('session_id');
        $orders = Order::where('stripe_session_id', $session_id)->get();

        if ($orders->count() === 0) {
            abort(404);
        }

        foreach ($orders as $order) {
            if ($order->user_id !== $user->id) {
                abort(403);
            }
        }

        return Inertia::render('Stripe/Success', [
            'orders' => OrderViewResource::collection($orders)->collection->toArray(),
        ]);
    }

    public function failure(Request $request)
    {
        $user = auth()->user();
        $session_id = $request->get('session_id');
        $orders = Order::where('stripe_session_id', $session_id)->get();

        foreach ($orders as $order) {
            if ($order->user_id !== $user->id) {
                abort(403);
            }
        }

        return Inertia::render('Stripe/Failure', [
            'orders' => OrderViewResource::collection($orders)->collection->toArray(),
        ]);
    }

    public function webhook(Request $request)
    {
        $stripe = new \Stripe\StripeClient(config('app.stripe_secret_key'));
        $endpoint_secret = config('app.stripe_webhook_secret');

        $payload = $request->getContent();
        $sig_header = request()->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            Log::error($e);
            return response('Invalid Payload', 400);
        }

        switch ($event->type) {
            case 'charge.updated':
                // ✅ FIX: Added break to prevent falling into next case
                $charge = $event->data->object;
                $transactionId = $charge['balance_transaction'];
                $paymentIntent = $charge['payment_intent'];
                $balanceTransaction = $stripe->balanceTransactions->retrieve($transactionId);

                $orders = Order::where('payment_intent', $paymentIntent)->get();
                $totalAmount = $balanceTransaction['amount'];

                $stripeFee = 0;
                foreach ($balanceTransaction['fee_details'] as $fee_detail) {
                    if ($fee_detail['type'] === 'stripe_fee') {
                        $stripeFee = $fee_detail['amount'];
                    }
                }

                $platformFreePercent = config('app.platform_fee_pct');

                foreach ($orders as $order) {
                    $vendorShare = $order->total_price / $totalAmount;

                    $order->online_payment_commission = $vendorShare * $stripeFee;
                    $order->website_commission = ($order->total_price - $order->online_payment_commission) / 100;
                    $order->vendor_subtotal = $order->total_price - $order->online_payment_commission - $order->website_commission;

                    $order->save();

                    Mail::to($order->vendorUser)->send(new NewOrderMail($order));
                }

                if ($orders->count() > 0) {
                    Mail::to($orders[0]->user)->send(new CheckoutCompleted($orders));
                }

                break; // ✅ Prevent fallthrough

            case 'checkout.session.completed':
                $session = $event->data->object;
                $pi = $session['payment_intent'];

                $orders = Order::query()
                    ->with(['orderItems'])
                    ->where(['stripe_session_id' => $session['id']])
                    ->get();

                $productsToDelete = [];

                foreach ($orders as $order) {
                    $order->payment_intent = $pi;
                    $order->status = OrderStatusEnum::Paid;
                    $order->save();

                    $productsToDelete = [
                        ...$productsToDelete,
                        ...$order->orderItems->map(fn($item) => $item->product_id)->toArray()
                    ];

                    foreach ($order->orderItems as $orderItem) {
                        /** @var \App\Models\OrderItem $orderItem */
                        $options = $orderItem->variation_type_option_ids;
                        $product = $orderItem->product;

                        if ($options) {
                            sort($options);
                            $variation = $product->variations()
                                ->whereJsonContains('variation_type_option_ids', $options) // ✅ Fix for JSON comparison
                                ->first();

                            if ($variation && $variation->quantity !== null) {
                                $variation->quantity -= $orderItem->quantity;
                                $variation->save();
                            }
                        } elseif ($product->quantity !== null) {
                            $product->quantity -= $orderItem->quantity;
                            $product->save();
                        }
                    }
                }

                // ✅ FIX: Safer deletion, or clear all non-saved items for user
                if (isset($order)) {
                    CartItem::query()
                        ->where('user_id', $order->user_id)
                        ->where('saved_for_later', false)
                        ->delete();
                }

                break; // ✅ Important to prevent default

            default:
                Log::info('Received unknown event type ' . $event->type);
                break;
        }

        return response('', 200);
    }

    public function connect()
    {
        if (!auth()->user()->getStripeAccountId()) {
            auth()->user()->createStripeAccount(['type' => 'express']);
        }

        if (!auth()->user()->isStripeAccountActive()) {
            return redirect(auth()->user()->getStripeAccountLink());
        }

        return back()->with('success', 'Your account is already connected.');
    }
}

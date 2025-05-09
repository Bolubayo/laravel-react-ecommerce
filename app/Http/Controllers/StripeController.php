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

        if ($orders->isEmpty()) {
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
        $stripeSecret = config('app.stripe_secret_key', env('STRIPE_SECRET'));
        $webhookSecret = config('app.stripe_webhook_secret', env('STRIPE_WEBHOOK_SECRET'));

        $stripe = new \Stripe\StripeClient($stripeSecret);

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe payload error: ' . $e->getMessage());
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Stripe signature error: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        switch ($event->type) {
            case 'charge.updated':
                $charge = $event->data->object;
                $transactionId = $charge['balance_transaction'];
                $paymentIntent = $charge['payment_intent'];
                $balanceTransaction = $stripe->balanceTransactions->retrieve($transactionId);

                $orders = Order::where('payment_intent', $paymentIntent)->get();
                if ($orders->isEmpty()) break;

                $totalAmount = $balanceTransaction['amount'];
                $stripeFee = collect($balanceTransaction['fee_details'])
                    ->firstWhere('type', 'stripe_fee')['amount'] ?? 0;

                foreach ($orders as $order) {
                    $vendorShare = $order->total_price / $totalAmount;
                    $order->online_payment_commission = $vendorShare * $stripeFee;
                    $order->website_commission = ($order->total_price - $order->online_payment_commission) / 100;
                    $order->vendor_subtotal = $order->total_price - $order->online_payment_commission - $order->website_commission;
                    $order->save();

                    Mail::to($order->vendorUser)->send(new NewOrderMail($order));
                }

                Mail::to($orders->first()->user)->send(new CheckoutCompleted($orders));
                break;

            case 'checkout.session.completed':
                $session = $event->data->object;
                $paymentIntent = $session['payment_intent'];

                $orders = Order::with(['orderItems'])->where('stripe_session_id', $session['id'])->get();
                if ($orders->isEmpty()) break;

                $productIdsToRemove = [];

                foreach ($orders as $order) {
                    $order->payment_intent = $paymentIntent;
                    $order->status = OrderStatusEnum::Paid;
                    $order->save();

                    foreach ($order->orderItems as $item) {
                        $product = $item->product;
                        $variationOptionIds = $item->variation_type_option_ids;

                        if ($variationOptionIds) {
                            sort($variationOptionIds);
                            $variation = $product->variations()
                                ->where('variation_type_option_ids', $variationOptionIds)
                                ->first();

                            if ($variation && $variation->quantity !== null) {
                                $variation->quantity -= $item->quantity;
                                $variation->save();
                            }
                        } elseif ($product->quantity !== null) {
                            $product->quantity -= $item->quantity;
                            $product->save();
                        }

                        $productIdsToRemove[] = $item->product_id;
                    }
                }

                // Clear cart items after order is successful
                CartItem::where('user_id', $orders->first()->user_id)
                    ->whereIn('product_id', $productIdsToRemove)
                    ->where('saved_for_later', false)
                    ->delete();

                break;

            default:
                Log::info("Unhandled Stripe event: {$event->type}");
                break;
        }

        return response('', 200);
    }

    public function connect()
    {
        $user = auth()->user();

        if (!$user->getStripeAccountId()) {
            $user->createStripeAccount(['type' => 'express']);
        }

        if (!$user->isStripeAccountActive()) {
            return redirect($user->getStripeAccountLink());
        }

        return back()->with('success', 'Your account is already connected.');
    }
}

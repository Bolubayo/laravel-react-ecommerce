<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class FlutterwaveController extends Controller
{
    public function flutterwave()
    {
        return view('flutterwave.payment', [
            'flutterwavePublicKey' => env('FLUTTERWAVE_PUBLIC_KEY'),
        ]);
    }

    public function initialize(Request $request)
    {
        $tx_ref = 'RX_' . Str::random(10);

        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $tx_ref,
                'amount' => $request->amount,
                'currency' => $request->currency ?? 'NGN',
                'redirect_url' => route('flutterwave.callback'),
                'customer' => [
                    'email' => $request->email,
                    'name' => $request->name,
                ],
                'customizations' => [
                    'title' => 'Daniel Store',
                    'description' => 'Payment for an awesome cruise',
                ],
            ]);

        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'link' => $response->json('data.link'),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Payment initialization failed.',
        ], 400);
    }

    public function verify(Request $request)
    {
        $transactionId = $request->transaction_id;

        if (!$transactionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction ID is required',
            ], 400);
        }

        $response = Http::withToken(env('FLUTTERWAVE_SECRET_KEY'))
            ->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not verify transaction. Please try again.',
            ], 400);
        }

        $paymentData = $response->json();

        if (
            isset($paymentData['status']) && $paymentData['status'] === 'success' &&
            isset($paymentData['data']['status']) && $paymentData['data']['status'] === 'successful'
        ) {
            $paymentInfo = $paymentData['data'];

            // Example: Save payment record
            // Payment::create([
            //     'transaction_id' => $paymentInfo['id'],
            //     'tx_ref' => $paymentInfo['tx_ref'],
            //     'amount' => $paymentInfo['amount'],
            //     'currency' => $paymentInfo['currency'],
            //     'status' => 'successful',
            //     'customer_email' => $paymentInfo['customer']['email'],
            // ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified successfully!',
                'data' => $paymentInfo,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Payment not successful',
        ], 400);
    }

    public function callback(Request $request)
    {
        // Flutterwave will redirect here after payment
        $transaction_id = $request->query('transaction_id');

        // You can now call verify($transaction_id) logic from here, or redirect to your frontend to verify
        return redirect()->route('flutterwave.payment.verify', ['transaction_id' => $transaction_id]);
    }
}

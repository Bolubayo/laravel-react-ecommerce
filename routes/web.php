<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\FlutterwaveController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\VendorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// })->name('dashboard');

Route::controller(SocialiteController::class)->group(function () {
    Route::get('auth/redirect/{provider}', 'authProviderRedirect')->name('auth.redirection');
    Route::get('auth/{provider}/callback', 'socialAuthentication')->name('auth.callback');
});

// Guest Routes
Route::get('/', [ProductController::class, 'home'])->name('dashboard');
Route::get('/products/{product:slug}', [ProductController::class, 'show'])
    ->name('product.show');

Route::get('/d/{department:slug}', [ProductController::class, 'byDepartment'])
    ->name('product.byDepartment');
    
Route::get('/s/{vendor:store_name}', [VendorController::class, 'profile'])
    ->name('vendor.profile');

Route::controller(CartController::class)->group(function () {
    Route::get('/cart', 'index')->name('cart.index');
    Route::post('/cart/add/{product}', 'store')
        ->name('cart.store');
    Route::put('/cart/{product}', 'update')
        ->name('cart.update');
    Route::delete('/cart/{product}', 'destroy')
        ->name('cart.destroy');
});


Route::post('/stripe/webhook', [StripeController::class, 'webhook'])
->name('stripe.webhook');

// Auth Routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    Route::middleware(['verified'])->group(function () {
        Route::post('/cart/checkout', [CartController::class, 'checkout'])
        ->name('cart.checkout');
        
        Route::get('/stripe/success', [StripeController::class, 'success'])
        ->name('stripe.success');
        
        Route::get('/stripe/failure', [StripeController::class, 'failure'])
        ->name('stripe.failure');
        
        Route::get('/paystack', [PaystackController::class, 'paystack'])->name('paystack.paystack');
        Route::post('pay', [PaystackController::class, 'make_payment'])->name('pay');
        Route::get('/paystack/callback', [PaystackController::class, 'payment_callback'])->name('paystack.callback');

        // Route::any('/flutterwave', [FlutterwaveController::class, 'flutterwave'])->name('flutterwave.flutterwave');

        Route::controller(FlutterwaveController::class)->group(function () {
            Route::get('/flutterwave', 'flutterwave')->name('flutterwave.payment');
            Route::post('/flutterwave/initialize', 'initialize')->name('flutterwave.initialize');
            Route::post('/flutterwave/verify', 'verify')->name('flutterwave.verify');
            Route::get('/flutterwave/callback', 'callback')->name('flutterwave.callback');
        });

        Route::post('/become-a-vendor', [VendorController::class, 'store'])
            ->name('vendor.store');

        Route::post('/stripe/connect', [StripeController::class, 'connect'])
            ->name('stripe.connect')
            ->middleware(['role:' . \App\RolesEnum::Vendor->value]);
    });
});

require __DIR__.'/auth.php';

// 👇 PLACE IT RIGHT HERE — AT THE END
Route::get('/{any}', function () {
    return Inertia::render('App');
})->where('any', '^(?!storage).*$');

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log; // For logging errors instead of dd()

class SocialiteController extends Controller
{
    /**
     * Redirect to Given Provider for authentication.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authProviderRedirect($provider)
    {
        if ($provider) {
            return Socialite::driver($provider)->redirect();
        }
        abort(404);
    }

    /**
     * For authentication using the provider.
     *
     * @param string $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function socialAuthentication($provider)
    {
        try {
            if ($provider) {
                // Get the user information from the provider
                $socialUser = Socialite::driver($provider)->user();

                // Check if the user already exists in the database by auth_provider_id
                $user = User::where('auth_provider_id', $socialUser->id)->first(); // Use first() to get a single user

                if ($user) {
                    // If the user exists, log them in
                    Auth::login($user);
                } else {
                    // If the user doesn't exist, create a new user
                    $userData = User::create([
                        'name' => $socialUser->getName(),
                        'email' => $socialUser->getEmail(),
                        'password' => Hash::make('Password@1234'), // Set a default password (or prompt user to change it)
                        'auth_provider_id' => $socialUser->getId(),
                        'auth_provider' => $provider,
                    ]);

                    // If the user was successfully created, log them in
                    if ($userData) {
                        Auth::login($userData);
                    }
                }

                // Redirect to the dashboard after successful login
                return redirect()->route('dashboard');
            }

            abort(404); // If provider is not found, abort with 404 error
        } catch (Exception $e) {
            // Log the error and return a user-friendly error response
            Log::error('Error during social authentication', ['exception' => $e]);

            // Optionally, you can redirect to an error page or show a message to the user
            return redirect()->route('login')->withErrors(['msg' => 'An error occurred during authentication. Please try again later.']);
        }
    }
}

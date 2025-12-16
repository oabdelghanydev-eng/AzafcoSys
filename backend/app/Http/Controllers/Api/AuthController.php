<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

/**
 * @tags Auth
 */
class AuthController extends Controller
{
    /**
     * Login with email and password.
     *
     * Authenticates a user with email and password credentials.
     * Returns user info and Bearer token on success.
     * Account lockout after 5 failed attempts.
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني غير مسجل'],
            ]);
        }

        // Check if locked
        if ($user->is_locked) {
            throw ValidationException::withMessages([
                'email' => ['الحساب مُغلق. تواصل مع المسؤول'],
            ]);
        }

        // Check password
        if (! $user->password || ! Hash::check($request->password, $user->password)) {
            $user->incrementFailedAttempts();

            throw ValidationException::withMessages([
                'password' => ['كلمة المرور غير صحيحة'],
            ]);
        }

        // Reset failed attempts
        $user->update(['failed_login_attempts' => 0]);

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'permissions' => $user->permissions,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Redirect to Google OAuth.
     *
     * Initiates Google OAuth flow for authentication.
     * Redirects user to Google login page.
     *
     * @unauthenticated
     */
    public function googleRedirect()
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback.
     *
     * Processes the OAuth callback from Google.
     * Creates or updates user account and returns token.
     * Only pre-registered users can login.
     *
     * @unauthenticated
     */
    public function googleCallback()
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $driver */
            $driver = Socialite::driver('google');
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = $driver->stateless()->user();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (! $user) {
                return response()->json([
                    'message' => 'هذا الحساب غير مسجل في النظام',
                ], 403);
            }

            // Check if locked
            if ($user->is_locked) {
                return response()->json([
                    'message' => 'الحساب مُغلق',
                ], 403);
            }

            // Update Google info
            $user->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            $token = $user->createToken('google-auth')->plainTextToken;

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'permissions' => $user->permissions,
                ],
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل تسجيل الدخول بـ Google',
            ], 500);
        }
    }

    /**
     * Logout current user.
     *
     * Revokes the current access token.
     * User must re-authenticate to access protected routes.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج']);
    }

    /**
     * Get current authenticated user.
     *
     * Returns profile information for the currently authenticated user
     * including permissions and admin status.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'permissions' => $user->permissions,
            'avatar' => $user->avatar,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'consent_given' => 'required|accepted',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'consent_given' => true,
            'consent_date' => now(),
            'data_retention_agreed' => true,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token,
            ],
            'message' => 'Registration successful',
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token,
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'data' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Format user response with computed properties
     */
    private function formatUserResponse(User $user): array
    {
        return [
            ...$user->toArray(),
            'is_premium' => $user->isPremium(),
            'is_admin' => $user->isAdmin(),
            'persona' => $user->getPersonaFeatures(),
            'onboarding_completed' => $user->onboarding_completed_at !== null,
        ];
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . Auth::id(),
        ]);

        $request->user()->update($validated);

        $user = $request->user()->fresh();

        return response()->json([
            'data' => $this->formatUserResponse($user),
            'message' => 'Profile updated successfully',
        ]);
    }

    /**
     * Update income settings (income type and net monthly income)
     */
    public function updateIncomeSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'income_type' => 'required|in:self_employed,salaried',
            'net_monthly_income' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'data' => $this->formatUserResponse($user->fresh()),
            'message' => 'Income settings updated successfully',
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Update subscription tier (admin only)
     */
    public function updateSubscription(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admins can change their subscription
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $validated = $request->validate([
            'subscription_tier' => 'required|in:free,premium',
        ]);

        $user->update([
            'subscription_tier' => $validated['subscription_tier'],
        ]);

        return response()->json([
            'data' => $this->formatUserResponse($user->fresh()),
            'message' => 'Subscription updated successfully',
        ]);
    }
}

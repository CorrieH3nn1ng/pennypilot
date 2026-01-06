<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BusinessProfileController extends Controller
{
    /**
     * Get the user's business profile
     */
    public function show(): JsonResponse
    {
        $profile = BusinessProfile::getOrCreateForUser(Auth::id());

        return response()->json([
            'data' => $profile,
            'has_banking_details' => $profile->hasBankingDetails(),
        ]);
    }

    /**
     * Update the business profile
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Business Info
            'business_name' => 'nullable|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:20',
            'vat_number' => 'nullable|string|max:20',
            // Contact Info
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            // Banking Details
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'bank_branch_code' => 'nullable|string|max:20',
            'account_holder' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'nullable|string|in:cheque,savings,transmission,current',
            // Invoice Settings
            'invoice_prefix' => 'nullable|string|max:20',
            'invoice_notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $profile = BusinessProfile::getOrCreateForUser(Auth::id());
        $profile->update($validated);

        return response()->json([
            'data' => $profile->fresh(),
            'message' => 'Business profile updated successfully',
        ]);
    }

    /**
     * Upload business logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,gif|max:2048', // 2MB max
        ]);

        $profile = BusinessProfile::getOrCreateForUser(Auth::id());

        // Delete old logo if exists
        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        // Store new logo
        $path = $request->file('logo')->store('logos/' . Auth::id(), 'public');
        $profile->update(['logo_path' => $path]);

        return response()->json([
            'data' => $profile->fresh(),
            'logo_url' => Storage::disk('public')->url($path),
            'message' => 'Logo uploaded successfully',
        ]);
    }

    /**
     * Remove business logo
     */
    public function removeLogo(): JsonResponse
    {
        $profile = BusinessProfile::getOrCreateForUser(Auth::id());

        if ($profile->logo_path) {
            Storage::disk('public')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
        }

        return response()->json([
            'data' => $profile->fresh(),
            'message' => 'Logo removed successfully',
        ]);
    }
}

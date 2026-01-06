<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ResetController - User Data Reset API
 *
 * Provides endpoints for users to manage their data lifecycle,
 * including complete account reset functionality.
 */
class ResetController extends Controller
{
    public function __construct(
        private UserResetService $resetService
    ) {}

    /**
     * Preview what data will be deleted.
     *
     * GET /api/reset/preview
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function preview(Request $request): JsonResponse
    {
        $user = $request->user();

        $preview = $this->resetService->getResetPreview($user);

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Execute a complete user data reset.
     *
     * POST /api/reset/execute
     *
     * This endpoint:
     * 1. Deletes ALL user transactional data (invoices, transactions, budgets, etc.)
     * 2. Resets onboarding status to false
     * 3. Returns success ONLY after DB transaction is committed
     *
     * The frontend should:
     * 1. Wait for the 200 OK response
     * 2. Clear all localStorage and IndexedDB
     * 3. Redirect to /wizard
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function execute(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->resetService->resetUserData($user);

            // Refresh user to get updated onboarding status
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'deleted' => $result['deleted'],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'onboarding_completed' => $user->onboarding_completed_at !== null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset user data. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

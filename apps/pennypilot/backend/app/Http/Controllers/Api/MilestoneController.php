<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class MilestoneController extends Controller
{
    /**
     * Get all milestones for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $query = Milestone::where('user_id', $request->user()->id)
            ->orderBy('due_date', 'asc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter upcoming (within N days)
        if ($request->has('upcoming')) {
            $days = (int) $request->upcoming;
            $query->where('due_date', '<=', Carbon::now()->addDays($days))
                  ->where('due_date', '>=', Carbon::now())
                  ->where('status', 'pending');
        }

        $milestones = $query->get();

        return response()->json([
            'success' => true,
            'data' => $milestones,
        ]);
    }

    /**
     * Get upcoming milestones (alerts for dashboard)
     */
    public function upcoming(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);

        $milestones = Milestone::where('user_id', $request->user()->id)
            ->pending()
            ->where('due_date', '<=', Carbon::now()->addDays($days))
            ->orderBy('due_date', 'asc')
            ->get();

        // Also include overdue
        $overdue = Milestone::where('user_id', $request->user()->id)
            ->overdue()
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming' => $milestones,
                'overdue' => $overdue,
            ],
        ]);
    }

    /**
     * Create a new milestone
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'type' => 'nullable|string|in:reminder,review,payment,goal',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'category' => 'nullable|string|max:100',
            'meta' => 'nullable|array',
        ]);

        $milestone = Milestone::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'],
            'type' => $validated['type'] ?? 'reminder',
            'priority' => $validated['priority'] ?? 'medium',
            'category' => $validated['category'] ?? null,
            'meta' => $validated['meta'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $milestone,
            'message' => 'Milestone created successfully',
        ], 201);
    }

    /**
     * Get a specific milestone
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $milestone,
        ]);
    }

    /**
     * Update a milestone
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'type' => 'nullable|string|in:reminder,review,payment,goal',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'category' => 'nullable|string|max:100',
            'meta' => 'nullable|array',
            'status' => 'nullable|string|in:pending,completed,dismissed',
        ]);

        $milestone->update(array_filter($validated, fn($v) => $v !== null));

        return response()->json([
            'success' => true,
            'data' => $milestone->fresh(),
            'message' => 'Milestone updated successfully',
        ]);
    }

    /**
     * Delete a milestone
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $milestone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Milestone deleted successfully',
        ]);
    }

    /**
     * Mark milestone as complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $milestone->complete();

        return response()->json([
            'success' => true,
            'data' => $milestone,
            'message' => 'Milestone marked as complete',
        ]);
    }

    /**
     * Dismiss a milestone
     */
    public function dismiss(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $milestone->dismiss();

        return response()->json([
            'success' => true,
            'data' => $milestone,
            'message' => 'Milestone dismissed',
        ]);
    }

    /**
     * Snooze a milestone
     */
    public function snooze(Request $request, string $id): JsonResponse
    {
        $milestone = Milestone::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $days = $request->get('days', 7);
        $milestone->snooze($days);

        return response()->json([
            'success' => true,
            'data' => $milestone,
            'message' => "Milestone snoozed for {$days} days",
        ]);
    }
}

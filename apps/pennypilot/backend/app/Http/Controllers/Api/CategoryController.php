<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * List all categories (system + user's custom)
     */
    public function index(): JsonResponse
    {
        $categories = Category::forUser(Auth::id())
            ->orderBy('is_income')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * Store a new custom category
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'parent_id' => 'nullable|uuid|exists:categories,id',
            'is_income' => 'nullable|boolean',
            'local_id' => 'nullable|string|max:100',
        ]);

        $category = Category::create([
            ...$validated,
            'user_id' => Auth::id(),
            'is_system' => false,
            'sync_status' => 'synced',
        ]);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully',
        ], 201);
    }

    /**
     * Update a category
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
        ]);

        $category->update($validated);

        return response()->json([
            'data' => $category->fresh(),
            'message' => 'Category updated successfully',
        ]);
    }

    /**
     * Delete a custom category
     */
    public function destroy(string $id): JsonResponse
    {
        $category = Category::where('user_id', Auth::id())
            ->where('is_system', false)
            ->findOrFail($id);

        // Reset transactions using this category
        $category->transactions()->update(['category_id' => null, 'is_categorized' => false]);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    /**
     * List all clients for the user
     */
    public function index(): JsonResponse
    {
        $clients = Client::where('user_id', Auth::id())
            ->withCount(['invoices', 'incomeSources'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        // Calculate total revenue (rolling 12-month from paid invoices)
        $oneYearAgo = now()->subYear();
        foreach ($clients as $client) {
            $totalRevenue = $client->invoices()
                ->where('status', 'paid')
                ->where('paid_date', '>=', $oneYearAgo)
                ->sum('total');
            $client->total_revenue = round($totalRevenue, 2);
        }

        return response()->json([
            'data' => $clients,
            'summary' => [
                'total_count' => $clients->count(),
                'active_count' => $clients->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Create a new client
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'billing_period_start' => 'nullable|integer|min:1|max:31',
            'billing_period_end' => 'nullable|integer|min:1|max:31',
            'payment_day' => 'nullable|integer|min:1|max:31',
            'payment_terms' => 'nullable|integer|min:1|max:365',
            'default_rate' => 'nullable|numeric|min:0',
            'rate_type' => 'nullable|in:hourly,daily,fixed',
            'is_active' => 'boolean',
        ]);

        // Generate next client code
        $clientCode = Client::generateNextCode(Auth::id());

        $client = Client::create([
            ...$validated,
            'user_id' => Auth::id(),
            'client_code' => $clientCode,
        ]);

        return response()->json([
            'data' => $client,
            'message' => 'Client created successfully',
        ], 201);
    }

    /**
     * Get a single client
     */
    public function show(string $id): JsonResponse
    {
        $client = Client::where('user_id', Auth::id())
            ->withCount(['invoices', 'incomeSources'])
            ->with(['invoices' => function ($query) {
                $query->orderBy('invoice_date', 'desc')->limit(10);
            }])
            ->findOrFail($id);

        // Calculate total revenue (rolling 12-month)
        $oneYearAgo = now()->subYear();
        $client->total_revenue = round(
            $client->invoices()
                ->where('status', 'paid')
                ->where('paid_date', '>=', $oneYearAgo)
                ->sum('total'),
            2
        );

        return response()->json(['data' => $client]);
    }

    /**
     * Update a client
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $client = Client::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'billing_period_start' => 'nullable|integer|min:1|max:31',
            'billing_period_end' => 'nullable|integer|min:1|max:31',
            'payment_day' => 'nullable|integer|min:1|max:31',
            'payment_terms' => 'nullable|integer|min:1|max:365',
            'default_rate' => 'nullable|numeric|min:0',
            'rate_type' => 'nullable|in:hourly,daily,fixed',
            'is_active' => 'boolean',
        ]);

        $client->update($validated);

        return response()->json([
            'data' => $client->fresh(),
            'message' => 'Client updated successfully',
        ]);
    }

    /**
     * Delete a client
     * Unlinks all invoices and income sources (sets client_id to null), then deletes
     */
    public function destroy(string $id): JsonResponse
    {
        $client = Client::where('user_id', Auth::id())
            ->withCount(['invoices', 'incomeSources'])
            ->findOrFail($id);

        $linkedCount = $client->invoices_count + $client->income_sources_count;

        // Unlink all related records (set client_id to null instead of deleting)
        if ($client->invoices_count > 0) {
            $client->invoices()->update(['client_id' => null]);
        }
        if ($client->income_sources_count > 0) {
            $client->incomeSources()->update(['client_id' => null]);
        }

        $client->delete();

        $message = 'Client deleted successfully';
        if ($linkedCount > 0) {
            $message .= ". {$linkedCount} linked record(s) have been unlinked.";
        }

        return response()->json([
            'message' => $message,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeSource;
use App\Services\TaxCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomeController extends Controller
{
    public function __construct(
        private TaxCalculatorService $taxCalculator
    ) {}

    /**
     * List all income sources for the user
     * Hierarchy: Client → Income Source → Invoices
     */
    public function index(): JsonResponse
    {
        $incomeSources = IncomeSource::where('user_id', Auth::id())
            ->with('client:id,name,client_code')
            ->withCount('invoices')
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Append computed attributes
        $incomeSources->each(function ($source) {
            $source->append(['monthly_amount', 'annual_amount', 'tax_estimate']);
        });

        // Calculate totals
        $activeIncome = $incomeSources->where('is_active', true);
        $totalMonthlyGross = $activeIncome->sum(fn($i) => $i->monthly_amount);
        $totalAnnualGross = $totalMonthlyGross * 12;

        // Calculate aggregate tax estimate using combined income
        $totalMonthlyTax = $activeIncome->sum(fn($i) => $i->tax_estimate['monthly_tax']);

        return response()->json([
            'data' => $incomeSources,
            'summary' => [
                'total_monthly_gross' => round($totalMonthlyGross, 2),
                'total_annual_gross' => round($totalAnnualGross, 2),
                'total_monthly_tax' => round($totalMonthlyTax, 2),
                'total_annual_tax' => round($totalMonthlyTax * 12, 2),
                'active_count' => $activeIncome->count(),
            ],
        ]);
    }

    /**
     * Create a new income source
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:salary,freelance,investment,rental,side_hustle,consulting,retainer,project,royalties,other',
            'client_id' => 'nullable|uuid|exists:clients,id',
            'gross_amount' => 'required|numeric|min:0',
            'net_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'frequency' => 'required|in:monthly,weekly,bi_weekly,annual,irregular',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Verify client belongs to user if provided
        if (!empty($validated['client_id'])) {
            $clientExists = \App\Models\Client::where('id', $validated['client_id'])
                ->where('user_id', Auth::id())
                ->exists();

            if (!$clientExists) {
                return response()->json(['message' => 'Client not found'], 422);
            }
        }

        $incomeSource = IncomeSource::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        $incomeSource->load('client:id,name,client_code');

        return response()->json([
            'data' => $incomeSource,
            'message' => 'Income source created successfully',
        ], 201);
    }

    /**
     * Get a single income source
     */
    public function show(string $id): JsonResponse
    {
        $incomeSource = IncomeSource::where('user_id', Auth::id())
            ->with('client:id,name,client_code')
            ->findOrFail($id);

        return response()->json(['data' => $incomeSource]);
    }

    /**
     * Update an income source
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $incomeSource = IncomeSource::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:salary,freelance,investment,rental,side_hustle,consulting,retainer,project,royalties,other',
            'client_id' => 'nullable|uuid',
            'gross_amount' => 'sometimes|numeric|min:0',
            'net_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'frequency' => 'sometimes|in:monthly,weekly,bi_weekly,annual,irregular',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Verify client belongs to user if provided
        if (array_key_exists('client_id', $validated) && !empty($validated['client_id'])) {
            $clientExists = \App\Models\Client::where('id', $validated['client_id'])
                ->where('user_id', Auth::id())
                ->exists();

            if (!$clientExists) {
                return response()->json(['message' => 'Client not found'], 422);
            }
        }

        $incomeSource->update($validated);

        return response()->json([
            'data' => $incomeSource->fresh()->load('client:id,name,client_code'),
            'message' => 'Income source updated successfully',
        ]);
    }

    /**
     * Delete an income source
     */
    public function destroy(string $id): JsonResponse
    {
        $incomeSource = IncomeSource::where('user_id', Auth::id())
            ->findOrFail($id);

        $incomeSource->delete();

        return response()->json([
            'message' => 'Income source deleted successfully',
        ]);
    }

    /**
     * Get income summary with tax estimates
     */
    public function summary(): JsonResponse
    {
        $incomeSources = IncomeSource::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        $totalMonthlyGross = $incomeSources->sum(fn($i) => $i->monthly_amount);
        $annualGross = $totalMonthlyGross * 12;

        $taxEstimate = $this->taxCalculator->calculateAnnualTax($annualGross);
        $monthlyProvision = $this->taxCalculator->calculateMonthlyProvision($totalMonthlyGross);

        return response()->json([
            'data' => [
                'monthly_gross' => round($totalMonthlyGross, 2),
                'annual_gross' => round($annualGross, 2),
                'tax_estimate' => $taxEstimate,
                'monthly_provision' => $monthlyProvision,
                'net_monthly' => $monthlyProvision['net_after_provision'],
            ],
        ]);
    }
}

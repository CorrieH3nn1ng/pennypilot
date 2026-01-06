<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\BusinessProfileController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\FixedExpenseController;
use App\Http\Controllers\Api\IncomeController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TaxController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\AiExtractionController;
use App\Http\Controllers\Api\InvoiceIngestionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\DevController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\ResetController;
use App\Http\Controllers\Api\BlueprintController;
use App\Http\Controllers\Api\BudgetCardController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\LiabilityController;
use App\Http\Controllers\Api\AccountantController;
use App\Http\Controllers\Api\MilestoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn() => response()->json(['status' => 'ok', 'app' => 'PennyPilot']));

// Global Configuration (public - for login/register pages)
Route::get('/config/countries', [ConfigController::class, 'countries']);
Route::get('/config/{countryCode}', [ConfigController::class, 'showByCountry']);

// Public AI endpoint (for n8n pipeline - no auth required)
Route::get('/ai/extraction-prompt', [AiExtractionController::class, 'getExtractionPrompt']);

// Public ingestion endpoint (for n8n pipeline - no auth required)
Route::post('/invoice-ingestion/ingest', [\App\Http\Controllers\Api\InvoiceIngestionController::class, 'ingest']);

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Global Configuration (authenticated - user's country config)
    Route::get('/config', [ConfigController::class, 'show']);

    // Auth
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    Route::put('/auth/subscription', [AuthController::class, 'updateSubscription']);
    Route::put('/auth/income-settings', [AuthController::class, 'updateIncomeSettings']);

    // Onboarding
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
    Route::post('/onboarding/extract', [OnboardingController::class, 'extractDocument']);

    // Categories
    Route::apiResource('categories', CategoryController::class)->except(['show']);

    // Transactions
    Route::get('/transactions/summary', [TransactionController::class, 'summary']);
    Route::post('/transactions/bulk', [TransactionController::class, 'bulkStore']);
    Route::put('/transactions/bulk', [TransactionController::class, 'bulkUpdate']);
    Route::apiResource('transactions', TransactionController::class);

    // Accounts
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/accounts/default', [AccountController::class, 'getDefault']);
    Route::post('/accounts/set-balance', [AccountController::class, 'setBalance']);
    Route::put('/accounts/{id}', [AccountController::class, 'update']);

    // Income Sources
    Route::get('/income-sources/summary', [IncomeController::class, 'summary']);
    Route::apiResource('income-sources', IncomeController::class);

    // Fixed Expenses
    Route::get('/fixed-expenses/due-soon', [FixedExpenseController::class, 'dueSoon']);
    Route::apiResource('fixed-expenses', FixedExpenseController::class);

    // Liabilities (Debt Recovery)
    Route::prefix('liabilities')->group(function () {
        Route::get('/recently-paid-off', [LiabilityController::class, 'recentlyPaidOff']);
        Route::post('/{id}/payment', [LiabilityController::class, 'recordPayment']);
        Route::post('/{id}/link-blueprint', [LiabilityController::class, 'linkBlueprint']);
        Route::post('/{id}/create-blueprint', [LiabilityController::class, 'createBlueprint']);
    });
    Route::apiResource('liabilities', LiabilityController::class);

    // Budgets
    Route::get('/budgets/current', [BudgetController::class, 'current']);
    Route::get('/budgets/{id}/items', [BudgetController::class, 'items']);
    Route::put('/budgets/{id}/items', [BudgetController::class, 'updateItems']);
    Route::get('/budgets/{id}/summary', [BudgetController::class, 'summary']);
    Route::apiResource('budgets', BudgetController::class);

    // Business Profile
    Route::prefix('business-profile')->group(function () {
        Route::get('/', [BusinessProfileController::class, 'show']);
        Route::put('/', [BusinessProfileController::class, 'update']);
        Route::post('/logo', [BusinessProfileController::class, 'uploadLogo']);
        Route::delete('/logo', [BusinessProfileController::class, 'removeLogo']);
    });

    // Clients
    Route::apiResource('clients', ClientController::class);

    // Invoices
    Route::prefix('invoices')->group(function () {
        Route::get('/preview-number', [InvoiceController::class, 'previewNumber']);
        Route::post('/update-overdue', [InvoiceController::class, 'updateOverdueStatus']);
        Route::post('/upload-historical', [InvoiceController::class, 'uploadHistorical']);
        Route::post('/auto-match-all', [InvoiceController::class, 'autoMatchAll']);
        Route::post('/unmatch-all', [InvoiceController::class, 'unmatchAll']);
        Route::post('/bulk-delete-before', [InvoiceController::class, 'bulkDeleteBefore']);
        Route::get('/match-stats', [InvoiceController::class, 'matchStats']);
        Route::put('/{id}/send', [InvoiceController::class, 'markSent']);
        Route::put('/{id}/pay', [InvoiceController::class, 'markPaid']);
        Route::put('/{id}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/{id}/download', [InvoiceController::class, 'download']);
        Route::get('/{id}/find-matches', [InvoiceController::class, 'findMatches']);
        Route::post('/{id}/match', [InvoiceController::class, 'match']);
        Route::post('/{id}/unmatch', [InvoiceController::class, 'unmatch']);
        Route::get('/{id}/tax-set-aside', [InvoiceIngestionController::class, 'getTaxSetAside']);
    });
    Route::apiResource('invoices', InvoiceController::class);

    // Invoice Ingestion (n8n/Gemini Pipeline)
    Route::prefix('invoice-ingestion')->group(function () {
        Route::post('/ingest', [InvoiceIngestionController::class, 'ingest']);
        Route::get('/staged', [InvoiceIngestionController::class, 'staged']);
        Route::post('/staged/{id}/approve', [InvoiceIngestionController::class, 'approveStaged']);
        Route::post('/staged/{id}/reject', [InvoiceIngestionController::class, 'rejectStaged']);
    });

    // Tax
    Route::prefix('tax')->group(function () {
        Route::get('/settings', [TaxController::class, 'getSettings']);
        Route::put('/settings', [TaxController::class, 'updateSettings']);
        Route::get('/provisions', [TaxController::class, 'getProvisions']);
        Route::get('/provisions/current', [TaxController::class, 'getCurrentSummary']);
        Route::post('/provisions', [TaxController::class, 'storeProvision']);
        Route::put('/provisions/{id}/paid', [TaxController::class, 'markPaid']);
        Route::post('/calculate', [TaxController::class, 'calculate']);
        Route::get('/brackets', [TaxController::class, 'getBrackets']);
        Route::post('/suggest-bucket', [TaxController::class, 'suggestBucket']);
        Route::get('/vat-threshold', [TaxController::class, 'getVatThresholdStatus']);
        // Centralized tax calculation - SINGLE SOURCE OF TRUTH
        Route::get('/effective-rate', [TaxController::class, 'getEffectiveRate']);
        Route::get('/verify-benchmark', [TaxController::class, 'verifyBenchmark']);
    });

    // AI Extraction
    Route::prefix("ai")->group(function () {
        Route::get("/status", [AiExtractionController::class, "status"]);
        Route::post("/extract-invoice", [AiExtractionController::class, "extractInvoice"]);
    });

    // Sync (Oplog-based)
    Route::prefix('sync')->group(function () {
        Route::post('/apply', [SyncController::class, 'apply']);
        Route::get('/status', [SyncController::class, 'status']);
    });

    // Discovery (Penny Interrogator - bucket assignment)
    Route::prefix('discovery')->group(function () {
        Route::get('/unassigned', [DiscoveryController::class, 'getUnassigned']);
        Route::post('/assign-bucket', [DiscoveryController::class, 'assignBucket']);
        Route::get('/rules', [DiscoveryController::class, 'getRules']);
        Route::delete('/rules/{id}', [DiscoveryController::class, 'deleteRule']);
        Route::post('/apply-rules', [DiscoveryController::class, 'applyRules']);
        Route::get('/stats', [DiscoveryController::class, 'getStats']);
    });

    // User Reset (Start Fresh - atomic server wipe)
    Route::prefix('reset')->group(function () {
        Route::get('/preview', [ResetController::class, 'preview']);
        Route::post('/execute', [ResetController::class, 'execute']);
    });

    // Dev utilities (local/testing only)
    Route::prefix('dev')->group(function () {
        Route::post('/clear-slate', [DevController::class, 'clearSlate']);
        Route::post('/seed-test-data', [DevController::class, 'seedTestData']);
    });

    // ========================================
    // BUDGET CARD SYSTEM (Strategic Audit)
    // ========================================

    // Blueprints (Master Template)
    Route::prefix('blueprints')->group(function () {
        Route::get('/', [BlueprintController::class, 'index']);
        Route::post('/', [BlueprintController::class, 'store']);
        Route::post('/bulk', [BlueprintController::class, 'bulkStore']);
        Route::get('/preview', [BlueprintController::class, 'preview']);
        Route::post('/import-from-fixed', [BlueprintController::class, 'importFromFixed']);
        Route::get('/{id}', [BlueprintController::class, 'show']);
        Route::put('/{id}', [BlueprintController::class, 'update']);
        Route::delete('/{id}', [BlueprintController::class, 'destroy']);
        Route::post('/{id}/toggle', [BlueprintController::class, 'toggle']);
    });

    // Budget Cards (Monthly Instances)
    Route::prefix('budget-cards')->group(function () {
        Route::get('/', [BudgetCardController::class, 'index']);
        Route::get('/current', [BudgetCardController::class, 'current']);
        Route::post('/generate', [BudgetCardController::class, 'generate']);
        Route::post('/renew', [BudgetCardController::class, 'renew']);
        Route::get('/{id}', [BudgetCardController::class, 'show']);
        Route::get('/{id}/grade', [BudgetCardController::class, 'grade']);
        Route::post('/{id}/activate', [BudgetCardController::class, 'activate']);
        Route::post('/{id}/finalize', [BudgetCardController::class, 'finalize']);
        Route::post('/{id}/items', [BudgetCardController::class, 'addItem']);
        Route::put('/{cardId}/items/{itemId}', [BudgetCardController::class, 'updateItem']);
        Route::delete('/{cardId}/items/{itemId}', [BudgetCardController::class, 'removeItem']);
    });

    // Audit Engine
    Route::prefix('audit')->group(function () {
        // Budget Card Audit (existing)
        Route::post('/run', [AuditController::class, 'run']);
        Route::post('/run-current', [AuditController::class, 'runCurrent']);
        Route::post('/match', [AuditController::class, 'match']);
        Route::post('/unmatch', [AuditController::class, 'unmatch']);
        Route::post('/silence', [AuditController::class, 'silence']);
        Route::post('/unsilence', [AuditController::class, 'unsilence']);
        Route::post('/leak-to-blueprint', [AuditController::class, 'leakToBlueprint']);
        Route::get('/pending', [AuditController::class, 'pending']);
        Route::get('/leaks/{budget_card_id}', [AuditController::class, 'leaks']);
        Route::get('/windfalls/{budget_card_id}', [AuditController::class, 'windfalls']);
        Route::get('/suggestions/{budget_card_id}', [AuditController::class, 'suggestions']);
        Route::get('/stats/{budget_card_id}', [AuditController::class, 'stats']);

        // Blueprint Reconciliation (new)
        Route::post('/reconcile', [AuditController::class, 'reconcileBlueprints']);
        Route::get('/variance/{year}/{month}', [AuditController::class, 'variance']);
        Route::get('/zombies', [AuditController::class, 'zombies']);
        Route::get('/unmatched', [AuditController::class, 'unmatched']);
        Route::post('/blueprints/{id}/cancel', [AuditController::class, 'cancelBlueprint']);
        Route::post('/blueprints/{id}/pause', [AuditController::class, 'pauseBlueprint']);
        Route::post('/blueprints/{id}/resume', [AuditController::class, 'resumeBlueprint']);
        Route::post('/match-blueprint', [AuditController::class, 'matchBlueprint']);
        Route::post('/unmatch-blueprint', [AuditController::class, 'unmatchBlueprint']);
        Route::post('/assign-blueprint', [AuditController::class, 'assignBlueprint']);

        // Spendable Cash Projection (with payment holiday awareness)
        Route::get('/spendable-projection', [AuditController::class, 'spendableProjection']);

        // Resumption Alerts & Dashboard Impact
        Route::get('/resumption-alerts', [AuditController::class, 'resumptionAlerts']);
        Route::get('/next-month-impact', [AuditController::class, 'nextMonthImpact']);

        // Data Range & Tax Year
        Route::get('/data-range', [AuditController::class, 'dataRange']);
        Route::get('/tax-year/{year}', [AuditController::class, 'taxYearSummary']);

        // Global Transaction Rules (Pattern-Based Matching)
        Route::post('/apply-rules', [AuditController::class, 'applyRules']);
        Route::get('/rules', [AuditController::class, 'getRules']);
        Route::post('/rules', [AuditController::class, 'createRule']);
        Route::put('/rules/{id}', [AuditController::class, 'updateRule']);
        Route::delete('/rules/{id}', [AuditController::class, 'deleteRule']);
    });

    // ========================================
    // ACCOUNTANT INTELLIGENCE
    // ========================================
    Route::prefix('accountant')->group(function () {
        Route::get('/flagged', [AccountantController::class, 'flagged']);
        Route::get('/summary', [AccountantController::class, 'summary']);
        Route::get('/report/pdf', [AccountantController::class, 'generatePdfReport']);
        Route::post('/flag/{id}', [AccountantController::class, 'flag']);
        Route::post('/unflag/{id}', [AccountantController::class, 'unflag']);
        Route::post('/approve/{id}', [AccountantController::class, 'approve']);
        Route::post('/reject/{id}', [AccountantController::class, 'reject']);
        Route::put('/question/{id}', [AccountantController::class, 'updateQuestion']);
    });

    // ========================================
    // MILESTONES & REMINDERS
    // ========================================
    Route::prefix('milestones')->group(function () {
        Route::get('/', [MilestoneController::class, 'index']);
        Route::get('/upcoming', [MilestoneController::class, 'upcoming']);
        Route::post('/', [MilestoneController::class, 'store']);
        Route::get('/{id}', [MilestoneController::class, 'show']);
        Route::put('/{id}', [MilestoneController::class, 'update']);
        Route::delete('/{id}', [MilestoneController::class, 'destroy']);
        Route::post('/{id}/complete', [MilestoneController::class, 'complete']);
        Route::post('/{id}/dismiss', [MilestoneController::class, 'dismiss']);
        Route::post('/{id}/snooze', [MilestoneController::class, 'snooze']);
    });
});

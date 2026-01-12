<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PennyMemory;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PennyController extends Controller
{
    /**
     * Get user's Penny memory (creates if not exists)
     * Called when chat opens to fetch full context
     */
    public function getMemory(): JsonResponse
    {
        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'display_name' => 'Adventurer',
                'current_location' => 'Home Base',
                'target_realm' => 'Sandton',
                'target_realm_cost' => 500000,
            ]
        );

        return response()->json([
            'data' => $memory,
            'context' => $memory->getN8nContext(),
            'missing_fields' => $memory->getMissingFields(),
        ]);
    }

    /**
     * Update Penny memory with new data
     */
    public function updateMemory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Identity
            'display_name' => 'nullable|string|max:100',
            'age' => 'nullable|integer|min:1|max:150',
            'primary_client' => 'nullable|string|max:200',
            'occupation' => 'nullable|string|max:100',
            // Boss Goal
            'boss_goal_name' => 'nullable|string|max:200',
            'boss_goal_target' => 'nullable|numeric|min:0',
            'boss_goal_current' => 'nullable|numeric|min:0',
            'boss_goal_deadline' => 'nullable|date',
            // JSON Data
            'inventory_items' => 'nullable|array',
            'character_traits' => 'nullable|array',
            'daily_quests' => 'nullable|array',
            // Stats
            'quest_streak' => 'nullable|integer|min:0',
            'total_xp' => 'nullable|integer|min:0',
            // Realm
            'current_location' => 'nullable|string|max:200',
            'target_realm' => 'nullable|string|max:200',
            'target_realm_cost' => 'nullable|numeric|min:0',
            // Status
            'is_healthy' => 'nullable|boolean',
            'is_secure' => 'nullable|boolean',
            // Conversation
            'has_met_penny' => 'nullable|boolean',
            'last_briefing_date' => 'nullable|string|max:20',
        ]);

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        // Update only provided fields
        $memory->fill($validated);
        $memory->last_interaction = now();

        // Sync checksums based on data
        $memory->syncQuadrantChecksums();

        $memory->save();

        return response()->json([
            'data' => $memory,
            'context' => $memory->getN8nContext(),
            'message' => 'Memory updated',
        ]);
    }

    /**
     * Send message to Penny - HYBRID approach
     * Financial queries → Local (private, no internet)
     * General chat → n8n/Gemini (internet)
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'action' => 'nullable|string|max:50',
        ]);

        $message = $validated['message'];
        $action = $validated['action'] ?? null;

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        // Generate session ID if not exists
        if (empty($memory->n8n_session_id)) {
            $memory->n8n_session_id = Str::uuid()->toString();
            $memory->save();
        }

        // Handle specific actions locally (fast)
        if ($action && in_array($action, ['weekly', 'progress', 'quest', 'item', 'boss'])) {
            return $this->localChat($message, $action, $memory);
        }

        // HYBRID: Check if this is a financial query → handle locally (private)
        $financialResponse = $this->handleFinancialQuery($message, $memory);
        if ($financialResponse) {
            return $financialResponse;
        }

        // Check if this is an app help question → handle locally
        $helpResponse = $this->handleHelpQuery($message, $memory);
        if ($helpResponse) {
            return $helpResponse;
        }

        // General chat → send to n8n/Gemini
        return $this->sendToN8n($message, $action, $memory);
    }

    /**
     * Detect and handle financial queries locally (no data sent to internet)
     */
    private function handleFinancialQuery(string $message, PennyMemory $memory): ?JsonResponse
    {
        $msg = strtolower($message);
        $name = $memory->display_name ?: 'Boss';

        // Detect spending queries
        $spendingKeywords = ['spend', 'spent', 'spending', 'expense', 'cost', 'pay', 'paid', 'payment', 'how much', 'total'];
        $categoryKeywords = [
            'fuel' => ['fuel', 'petrol', 'gas', 'diesel', 'engen', 'sasol', 'shell', 'caltex', 'bp'],
            'groceries' => ['grocery', 'groceries', 'food', 'supermarket', 'pick n pay', 'checkers', 'woolworths', 'spar', 'shoprite'],
            'entertainment' => ['entertainment', 'movies', 'netflix', 'spotify', 'dstv', 'showmax', 'youtube'],
            'transport' => ['transport', 'uber', 'bolt', 'taxi', 'e-toll', 'etoll'],
            'utilities' => ['utilities', 'electricity', 'water', 'municipal', 'eskom', 'city power', 'prepaid'],
            'insurance' => ['insurance', 'outsurance', 'discovery', 'santam', 'old mutual', 'momentum'],
            'medical' => ['medical', 'doctor', 'pharmacy', 'medicine', 'clicks', 'dischem', 'dis-chem', 'hospital'],
            'shopping' => ['shopping', 'clothes', 'amazon', 'takealot', 'shein', 'mr price'],
            'bond' => ['bond', 'mortgage', 'home loan', 'homeloan', 'absa bond', 'fnb bond', 'nedbank bond', 'standard bank bond'],
            'rent' => ['rent', 'rental', 'lease', 'landlord'],
            'car' => ['car payment', 'vehicle finance', 'wesbank', 'mfc'],
            'levies' => ['levy', 'levies', 'body corporate', 'hoa'],
            'internet' => ['internet', 'fibre', 'wifi', 'data', 'telkom', 'vodacom', 'mtn', 'rain', 'vumatel'],
            'phone' => ['phone', 'cellphone', 'mobile', 'airtime', 'contract'],
            'subscriptions' => ['subscription', 'membership', 'gym', 'virgin active', 'planet fitness'],
            'education' => ['school', 'education', 'tuition', 'fees', 'university', 'college'],
            'domestic' => ['domestic', 'cleaner', 'gardener', 'helper'],
            'security' => ['security', 'adt', 'armed response', 'fidelity', 'chubb'],
            'sars' => ['sars', 'tax', 'provisional tax', 'income tax'],
            'alimony' => ['alimony', 'maintenance', 'child support'],
        ];

        $isSpendingQuery = false;
        foreach ($spendingKeywords as $keyword) {
            if (str_contains($msg, $keyword)) {
                $isSpendingQuery = true;
                break;
            }
        }

        if (!$isSpendingQuery) {
            // Check for balance/summary queries
            if (str_contains($msg, 'balance') || str_contains($msg, 'summary') || str_contains($msg, 'overview')) {
                return $this->getMonthSummary($memory);
            }
            return null; // Not a financial query
        }

        // Determine time period
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $periodLabel = 'this month';

        if (str_contains($msg, 'last month')) {
            $startDate = Carbon::now()->subMonth()->startOfMonth();
            $endDate = Carbon::now()->subMonth()->endOfMonth();
            $periodLabel = 'last month';
        } elseif (str_contains($msg, 'this year') || str_contains($msg, 'year')) {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
            $periodLabel = 'this year';
        }

        // Find which category they're asking about
        $targetCategory = null;
        $targetCategoryName = null;
        foreach ($categoryKeywords as $catName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($msg, $keyword)) {
                    $targetCategory = $catName;
                    $targetCategoryName = ucfirst($catName);
                    break 2;
                }
            }
        }

        // Query transactions locally (PRIVATE - never leaves your machine)
        $query = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('amount', '<', 0); // Expenses only

        if ($targetCategory) {
            // Find matching categories by name
            $categoryIds = Category::where('name', 'ILIKE', "%{$targetCategory}%")
                ->pluck('id');

            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('category_id', $categoryIds);
            } else {
                // Try matching by transaction description
                $query->where(function ($q) use ($categoryKeywords, $targetCategory) {
                    foreach ($categoryKeywords[$targetCategory] as $keyword) {
                        $q->orWhere('description', 'ILIKE', "%{$keyword}%");
                    }
                });
            }
        }

        $total = abs($query->sum('amount'));
        $count = $query->count();

        // Format response
        if ($targetCategoryName) {
            $reply = "**{$targetCategoryName}** {$periodLabel}: **R" . number_format($total, 2) . "**";
            if ($count > 0) {
                $reply .= " ({$count} transactions)";
            } else {
                $reply = "No **{$targetCategoryName}** spending found {$periodLabel}.";
            }
        } else {
            $reply = "Total spending {$periodLabel}: **R" . number_format($total, 2) . "** ({$count} transactions)";
        }

        $memory->last_interaction = now();
        $memory->save();

        return response()->json([
            'reply' => $reply,
            'action' => null,
            'memory_updated' => false,
        ]);
    }

    /**
     * Handle app help questions locally (no internet)
     */
    private function handleHelpQuery(string $message, PennyMemory $memory): ?JsonResponse
    {
        $msg = strtolower($message);

        // Statement/Import help
        if (str_contains($msg, 'upload') || str_contains($msg, 'import') || str_contains($msg, 'statement')) {
            return response()->json([
                'reply' => "I'll take you to **Import** where you can upload your bank statement.",
                'action' => ['type' => 'navigate', 'route' => '/import', 'label' => 'Go to Import', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Invoice help
        if (str_contains($msg, 'invoice') || str_contains($msg, 'create invoice') || str_contains($msg, 'send invoice')) {
            return response()->json([
                'reply' => "I'll take you to **Invoices** where you can create and manage invoices.",
                'action' => ['type' => 'navigate', 'route' => '/invoices', 'label' => 'Go to Invoices', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Budget help
        if (str_contains($msg, 'budget') && (str_contains($msg, 'set') || str_contains($msg, 'create') || str_contains($msg, 'how'))) {
            return response()->json([
                'reply' => "I'll take you to **Budget** where you can set up your monthly budget.",
                'action' => ['type' => 'navigate', 'route' => '/budget', 'label' => 'Go to Budget', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Category help
        if (str_contains($msg, 'categorize') || str_contains($msg, 'categorise') || str_contains($msg, 'category')) {
            return response()->json([
                'reply' => "I'll take you to **Audit** where you can categorize transactions.",
                'action' => ['type' => 'navigate', 'route' => '/audit', 'label' => 'Go to Audit', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Tax help
        if ((str_contains($msg, 'tax') && (str_contains($msg, 'how') || str_contains($msg, 'set') || str_contains($msg, 'help'))) || str_contains($msg, 'provisional')) {
            return response()->json([
                'reply' => "I'll take you to **Tax** where you can manage your tax provisions and SARS payments.",
                'action' => ['type' => 'navigate', 'route' => '/tax', 'label' => 'Go to Tax', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Client help
        if (str_contains($msg, 'client') && (str_contains($msg, 'add') || str_contains($msg, 'new') || str_contains($msg, 'create'))) {
            return response()->json([
                'reply' => "I'll take you to **Clients** where you can add and manage your clients.",
                'action' => ['type' => 'navigate', 'route' => '/clients', 'label' => 'Go to Clients', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        // Mileage/Trips help
        if (str_contains($msg, 'mileage') || str_contains($msg, 'trip') || str_contains($msg, 'travel claim')) {
            return response()->json([
                'reply' => "I'll take you to **Trips** where you can log business travel for SARS deductions (R4.64/km).",
                'action' => ['type' => 'navigate', 'route' => '/trips', 'label' => 'Go to Trips', 'color' => 'primary'],
                'memory_updated' => false,
            ]);
        }

        return null; // Not a help query
    }

    /**
     * Get month summary (local, private)
     */
    private function getMonthSummary(PennyMemory $memory): JsonResponse
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();
        $name = $memory->display_name ?: 'Boss';

        $income = Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('amount', '>', 0)
            ->where(function ($q) {
                $q->where('is_transfer', false)->orWhereNull('is_transfer');
            })
            ->sum('amount');

        $expenses = abs(Transaction::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->where(function ($q) {
                $q->where('is_transfer', false)->orWhereNull('is_transfer');
            })
            ->sum('amount'));

        $net = $income - $expenses;
        $netLabel = $net >= 0 ? 'surplus' : 'deficit';

        $reply = "{$name}, **" . Carbon::now()->format('F') . "** summary:\n\n";
        $reply .= "Income: **R" . number_format($income, 2) . "**\n";
        $reply .= "Expenses: **R" . number_format($expenses, 2) . "**\n";
        $reply .= "Net {$netLabel}: **R" . number_format(abs($net), 2) . "**";

        $memory->last_interaction = now();
        $memory->save();

        return response()->json([
            'reply' => $reply,
            'action' => null,
            'memory_updated' => false,
        ]);
    }

    /**
     * Send general chat to n8n/Gemini (internet)
     */
    private function sendToN8n(string $message, ?string $action, PennyMemory $memory): JsonResponse
    {
        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return $this->localChat($message, $action, $memory);
        }

        try {
            $payload = [
                'session_id' => $memory->n8n_session_id,
                'user_message' => $message,
                'action' => $action,
                'context' => $memory->getN8nContext(),
                'timestamp' => now()->toIso8601String(),
            ];

            $response = Http::timeout(30)->post($webhookUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['memory_updates'])) {
                    $memory->fill($data['memory_updates']);
                    $memory->syncQuadrantChecksums();
                    $memory->save();
                }

                $memory->last_interaction = now();
                $memory->save();

                return response()->json([
                    'reply' => $data['reply'] ?? 'No response from Penny.',
                    'action' => $data['action'] ?? null,
                    'memory_updated' => !empty($data['memory_updates']),
                ]);
            }

            Log::error('n8n webhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->localChat($message, $action, $memory);

        } catch (\Exception $e) {
            Log::error('n8n webhook error', ['error' => $e->getMessage()]);
            return $this->localChat($message, $action, $memory);
        }
    }

    /**
     * Local chat fallback when n8n is not available
     */
    private function localChat(string $message, ?string $action, PennyMemory $memory): JsonResponse
    {
        $name = $memory->display_name ?: 'Adventurer';
        $missing = $memory->getMissingFields();

        // Handle specific actions
        if ($action === 'weekly') {
            $level = floor($memory->total_xp / 500) + 1;
            $reply = "**Briefing** - " . now()->format('j M') . "\n\n" .
                     "Level **{$level}** | {$memory->total_xp}XP | {$memory->quest_streak}d streak";

            if (!empty($memory->boss_goal_name)) {
                $progress = $memory->boss_goal_target > 0
                    ? round(($memory->boss_goal_current / $memory->boss_goal_target) * 100)
                    : 0;
                $reply .= "\nBoss Goal: **{$memory->boss_goal_name}** - {$progress}%";
            }

            return response()->json(['reply' => $reply, 'action' => null]);
        }

        if ($action === 'progress') {
            $level = floor($memory->total_xp / 500) + 1;
            $xpInLevel = $memory->total_xp % 500;
            $xpToNext = 500 - $xpInLevel;

            return response()->json([
                'reply' => "Level **{$level}** | {$xpInLevel}/500 XP | {$xpToNext} to next | {$memory->quest_streak}d streak",
                'action' => null,
            ]);
        }

        if ($action === 'quest') {
            return response()->json([
                'reply' => 'Quest name? (walk, gym, guitar, paint, read)',
                'action' => null,
            ]);
        }

        if ($action === 'item') {
            return response()->json([
                'reply' => 'Item? (car, guitar, emergency fund, laptop)',
                'action' => null,
            ]);
        }

        if ($action === 'boss') {
            if (!empty($memory->boss_goal_name)) {
                $progress = $memory->boss_goal_target > 0
                    ? round(($memory->boss_goal_current / $memory->boss_goal_target) * 100)
                    : 0;
                $remaining = max(0, $memory->boss_goal_target - $memory->boss_goal_current);

                return response()->json([
                    'reply' => "**{$memory->boss_goal_name}**: {$progress}% | R" . number_format($remaining) . " to go",
                    'action' => null,
                ]);
            }

            return response()->json([
                'reply' => "{$name}, **LIVE** has no boss. What's the mission?",
                'action' => null,
            ]);
        }

        // Smart intro based on missing fields
        if (!$memory->has_met_penny) {
            $memory->has_met_penny = true;
            $memory->save();

            $reply = "{$name}. Penny here. Let's get to work.";

            if (count($missing) > 0) {
                $prompts = [
                    'quests' => '**DO** quadrant is empty. What\'s your first daily quest?',
                    'inventory' => '**HAVE** quadrant needs items. What are you saving for?',
                    'traits' => '**BE** quadrant is blank. What trait are you building?',
                    'destination' => '**LIVE** needs a destination. What\'s the boss goal?',
                    'boss_goal' => 'No boss goal set. What\'s the R target?',
                ];

                foreach ($missing as $field) {
                    if (isset($prompts[$field])) {
                        $reply .= "\n\n" . $prompts[$field];
                        break;
                    }
                }
            }

            return response()->json(['reply' => $reply, 'action' => null]);
        }

        // Returning user - check missing fields
        if (count($missing) > 0) {
            $prompts = [
                'quests' => "{$name}, **DO** is empty. First quest?",
                'inventory' => "{$name}, no items in **HAVE**. What's the target?",
                'traits' => "{$name}, **BE** needs a trait. What are you becoming?",
                'destination' => "{$name}, **LIVE** has no boss. What's the mission?",
                'boss_goal' => "{$name}, no boss goal. What's the R target?",
            ];

            foreach ($missing as $field) {
                if (isset($prompts[$field])) {
                    return response()->json(['reply' => $prompts[$field], 'action' => null]);
                }
            }
        }

        // Default response
        return response()->json([
            'reply' => 'Quadrants set. What\'s next?',
            'action' => null,
        ]);
    }

    /**
     * Add a quest to memory
     */
    public function addQuest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'xp' => 'required|integer|min:1|max:1000',
        ]);

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        $quests = $memory->daily_quests ?? [];
        $quests[] = [
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'xp' => $validated['xp'],
            'completed' => false,
        ];

        $memory->daily_quests = $quests;
        $memory->has_quests = true;
        $memory->save();

        return response()->json([
            'data' => $memory,
            'message' => "Quest '{$validated['name']}' added.",
        ]);
    }

    /**
     * Add an item to inventory
     */
    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'alias' => 'nullable|string|max:200',
            'icon' => 'nullable|string|max:50',
            'target' => 'required|numeric|min:0',
            'current' => 'nullable|numeric|min:0',
        ]);

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        $items = $memory->inventory_items ?? [];
        $items[] = [
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'alias' => $validated['alias'] ?? $validated['name'],
            'icon' => $validated['icon'] ?? 'savings',
            'target' => $validated['target'],
            'current' => $validated['current'] ?? 0,
        ];

        $memory->inventory_items = $items;
        $memory->has_inventory = true;
        $memory->save();

        return response()->json([
            'data' => $memory,
            'message' => "Item '{$validated['name']}' added to inventory.",
        ]);
    }

    /**
     * Set boss goal
     */
    public function setBossGoal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'target' => 'required|numeric|min:0',
            'current' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
        ]);

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        $memory->boss_goal_name = $validated['name'];
        $memory->boss_goal_target = $validated['target'];
        $memory->boss_goal_current = $validated['current'] ?? 0;
        $memory->boss_goal_deadline = $validated['deadline'] ?? null;
        $memory->has_destination = true;
        $memory->save();

        return response()->json([
            'data' => $memory,
            'message' => "Boss goal '{$validated['name']}' set. R" . number_format($validated['target']) . " target.",
        ]);
    }

    /**
     * Sync avatar data from frontend to memory
     */
    public function syncFromAvatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'totalXP' => 'nullable|integer|min:0',
            'questStreak' => 'nullable|integer|min:0',
            'quests' => 'nullable|array',
            'inventory' => 'nullable|array',
            'attributes' => 'nullable|array',
            'targetRealm' => 'nullable|array',
            'currentLocation' => 'nullable|string|max:200',
            'healthy' => 'nullable|boolean',
            'secure' => 'nullable|boolean',
        ]);

        $memory = PennyMemory::firstOrCreate(
            ['user_id' => Auth::id()],
            ['display_name' => 'Adventurer']
        );

        if (isset($validated['name'])) $memory->display_name = $validated['name'];
        if (isset($validated['totalXP'])) $memory->total_xp = $validated['totalXP'];
        if (isset($validated['questStreak'])) $memory->quest_streak = $validated['questStreak'];
        if (isset($validated['quests'])) $memory->daily_quests = $validated['quests'];
        if (isset($validated['inventory'])) $memory->inventory_items = $validated['inventory'];
        if (isset($validated['attributes'])) $memory->character_traits = $validated['attributes'];
        if (isset($validated['currentLocation'])) $memory->current_location = $validated['currentLocation'];
        if (isset($validated['healthy'])) $memory->is_healthy = $validated['healthy'];
        if (isset($validated['secure'])) $memory->is_secure = $validated['secure'];

        if (isset($validated['targetRealm'])) {
            $memory->target_realm = $validated['targetRealm']['location'] ?? null;
            $memory->target_realm_cost = $validated['targetRealm']['cost'] ?? null;
        }

        $memory->last_interaction = now();
        $memory->syncQuadrantChecksums();
        $memory->save();

        return response()->json([
            'data' => $memory,
            'context' => $memory->getN8nContext(),
            'message' => 'Avatar synced to memory.',
        ]);
    }
}

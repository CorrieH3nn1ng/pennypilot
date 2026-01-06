<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\IncomeSource;
use App\Models\FixedExpense;
use App\Models\Oplog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * Apply oplog operations from client
     *
     * This endpoint receives a batch of operations from the client's oplog
     * and applies them to the database in order. It handles conflicts by
     * checking version numbers and returning appropriate error messages.
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'operations' => 'required|array',
            'operations.*.id' => 'required|string',
            'operations.*.entity_type' => 'required|string|in:transaction,category,invoice,client,income_source,fixed_expense',
            'operations.*.entity_id' => 'required|string',
            'operations.*.operation' => 'required|string|in:create,update,delete',
            'operations.*.data' => 'required|array',
            'operations.*.timestamp' => 'required|integer',
            'operations.*.checksum' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Check if user has sync permission (cruiser or captain tier)
        if (!in_array($user->subscription_tier, ['cruiser', 'captain'])) {
            return response()->json([
                'message' => 'Sync requires Cruiser or Captain subscription',
                'applied' => [],
                'failed' => array_map(fn($op) => [
                    'id' => $op['id'],
                    'error' => 'Upgrade required for sync',
                ], $request->operations),
            ], 403);
        }

        $applied = [];
        $failed = [];

        DB::beginTransaction();

        try {
            foreach ($request->operations as $operation) {
                $result = $this->applyOperation($user->id, $operation);

                if ($result['success']) {
                    $applied[] = $operation['id'];

                    // Log the operation for audit trail
                    Oplog::create([
                        'user_id' => $user->id,
                        'client_timestamp' => $operation['timestamp'],
                        'entity_type' => $operation['entity_type'],
                        'entity_id' => $operation['entity_id'],
                        'operation' => $operation['operation'],
                        'data' => $operation['data'],
                        'checksum' => $operation['checksum'] ?? null,
                        'status' => 'applied',
                    ]);
                } else {
                    $failed[] = [
                        'id' => $operation['id'],
                        'error' => $result['error'],
                        'server_version' => $result['server_version'] ?? null,
                    ];

                    // Log failed operation
                    Oplog::create([
                        'user_id' => $user->id,
                        'client_timestamp' => $operation['timestamp'],
                        'entity_type' => $operation['entity_type'],
                        'entity_id' => $operation['entity_id'],
                        'operation' => $operation['operation'],
                        'data' => $operation['data'],
                        'checksum' => $operation['checksum'] ?? null,
                        'status' => 'rejected',
                        'error_message' => $result['error'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'applied' => $applied,
                'failed' => $failed,
                'message' => sprintf(
                    'Applied %d operations, %d failed',
                    count($applied),
                    count($failed)
                ),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync apply failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Sync failed: ' . $e->getMessage(),
                'applied' => [],
                'failed' => array_map(fn($op) => [
                    'id' => $op['id'],
                    'error' => 'Server error during sync',
                ], $request->operations),
            ], 500);
        }
    }

    /**
     * Apply a single operation
     */
    private function applyOperation(string $userId, array $operation): array
    {
        $entityType = $operation['entity_type'];
        $entityId = $operation['entity_id'];
        $op = $operation['operation'];
        $data = $operation['data'];

        try {
            switch ($entityType) {
                case 'transaction':
                    return $this->applyTransactionOperation($userId, $entityId, $op, $data);
                case 'category':
                    return $this->applyCategoryOperation($userId, $entityId, $op, $data);
                case 'invoice':
                    return $this->applyInvoiceOperation($userId, $entityId, $op, $data);
                case 'client':
                    return $this->applyClientOperation($userId, $entityId, $op, $data);
                case 'income_source':
                    return $this->applyIncomeSourceOperation($userId, $entityId, $op, $data);
                case 'fixed_expense':
                    return $this->applyFixedExpenseOperation($userId, $entityId, $op, $data);
                default:
                    return ['success' => false, 'error' => 'Unknown entity type'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply transaction operation
     */
    private function applyTransactionOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                // Check for duplicate
                $existing = Transaction::where('user_id', $userId)
                    ->where(function ($q) use ($entityId, $data) {
                        $q->where('id', $entityId)
                            ->orWhere('local_id', $entityId)
                            ->orWhere('local_id', $data['local_id'] ?? null);
                    })
                    ->first();

                if ($existing) {
                    return ['success' => false, 'error' => 'Transaction already exists', 'server_version' => $existing->version];
                }

                $transaction = Transaction::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                ]);

                // Compute metadata hash for blockchain prep
                $transaction->updateMetadataHash();

                return ['success' => true];

            case 'update':
                $transaction = Transaction::where('user_id', $userId)
                    ->where(function ($q) use ($entityId) {
                        $q->where('id', $entityId)->orWhere('local_id', $entityId);
                    })
                    ->first();

                if (!$transaction) {
                    return ['success' => false, 'error' => 'Transaction not found'];
                }

                // Version check for conflict detection
                $clientVersion = $data['version'] ?? 1;
                if ($transaction->version > $clientVersion) {
                    return [
                        'success' => false,
                        'error' => 'Version conflict - server has newer version',
                        'server_version' => $transaction->version,
                    ];
                }

                $transaction->update([
                    ...$data,
                    'sync_status' => 'synced',
                    'last_synced_at' => now(),
                    'version' => $transaction->version + 1,
                ]);

                return ['success' => true];

            case 'delete':
                $transaction = Transaction::where('user_id', $userId)
                    ->where(function ($q) use ($entityId) {
                        $q->where('id', $entityId)->orWhere('local_id', $entityId);
                    })
                    ->first();

                if ($transaction) {
                    $transaction->delete();
                }

                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Apply category operation
     */
    private function applyCategoryOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                $existing = Category::where('id', $entityId)->first();
                if ($existing) {
                    return ['success' => false, 'error' => 'Category already exists'];
                }

                Category::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                ]);
                return ['success' => true];

            case 'update':
                $category = Category::where('user_id', $userId)->where('id', $entityId)->first();
                if (!$category) {
                    return ['success' => false, 'error' => 'Category not found'];
                }
                $category->update($data);
                return ['success' => true];

            case 'delete':
                Category::where('user_id', $userId)->where('id', $entityId)->delete();
                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Apply invoice operation
     */
    private function applyInvoiceOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                $existing = Invoice::where('id', $entityId)->first();
                if ($existing) {
                    return ['success' => false, 'error' => 'Invoice already exists'];
                }

                Invoice::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                ]);
                return ['success' => true];

            case 'update':
                $invoice = Invoice::where('user_id', $userId)->where('id', $entityId)->first();
                if (!$invoice) {
                    return ['success' => false, 'error' => 'Invoice not found'];
                }
                $invoice->update($data);
                return ['success' => true];

            case 'delete':
                Invoice::where('user_id', $userId)->where('id', $entityId)->delete();
                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Apply client operation
     */
    private function applyClientOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                $existing = Client::where('id', $entityId)->first();
                if ($existing) {
                    return ['success' => false, 'error' => 'Client already exists'];
                }

                Client::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                ]);
                return ['success' => true];

            case 'update':
                $client = Client::where('user_id', $userId)->where('id', $entityId)->first();
                if (!$client) {
                    return ['success' => false, 'error' => 'Client not found'];
                }
                $client->update($data);
                return ['success' => true];

            case 'delete':
                Client::where('user_id', $userId)->where('id', $entityId)->delete();
                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Apply income source operation
     */
    private function applyIncomeSourceOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                $existing = IncomeSource::where('id', $entityId)->first();
                if ($existing) {
                    return ['success' => false, 'error' => 'Income source already exists'];
                }

                IncomeSource::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                ]);
                return ['success' => true];

            case 'update':
                $income = IncomeSource::where('user_id', $userId)->where('id', $entityId)->first();
                if (!$income) {
                    return ['success' => false, 'error' => 'Income source not found'];
                }
                $income->update($data);
                return ['success' => true];

            case 'delete':
                IncomeSource::where('user_id', $userId)->where('id', $entityId)->delete();
                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Apply fixed expense operation
     */
    private function applyFixedExpenseOperation(string $userId, string $entityId, string $op, array $data): array
    {
        switch ($op) {
            case 'create':
                $existing = FixedExpense::where('id', $entityId)->first();
                if ($existing) {
                    return ['success' => false, 'error' => 'Fixed expense already exists'];
                }

                FixedExpense::create([
                    ...$data,
                    'id' => $entityId,
                    'user_id' => $userId,
                ]);
                return ['success' => true];

            case 'update':
                $expense = FixedExpense::where('user_id', $userId)->where('id', $entityId)->first();
                if (!$expense) {
                    return ['success' => false, 'error' => 'Fixed expense not found'];
                }
                $expense->update($data);
                return ['success' => true];

            case 'delete':
                FixedExpense::where('user_id', $userId)->where('id', $entityId)->delete();
                return ['success' => true];

            default:
                return ['success' => false, 'error' => 'Unknown operation'];
        }
    }

    /**
     * Get sync status for the user
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();

        $pendingCount = Oplog::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $lastSync = Oplog::where('user_id', $user->id)
            ->where('status', 'applied')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'sync_enabled' => in_array($user->subscription_tier, ['cruiser', 'captain']),
            'tier' => $user->subscription_tier,
            'pending_count' => $pendingCount,
            'last_sync_at' => $lastSync?->created_at,
        ]);
    }
}

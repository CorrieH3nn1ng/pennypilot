/**
 * SyncManager - Oplog-based Sync with Optimistic UI
 *
 * This service manages synchronization between LocalBase (IndexedDB) and the Laravel backend.
 * It uses an Operation Log (Oplog) pattern to ensure no data is ever lost.
 *
 * Key Principles:
 * 1. Optimistic UI - Changes appear instantly in the UI
 * 2. Oplog-based - Every mutation is logged before execution
 * 3. Conflict Resolution - Server is authoritative, with rollback support
 * 4. Offline-First - Works without network, syncs when online
 * 5. Retry Logic - Failed syncs are retried with exponential backoff
 */

import { ref, computed, readonly } from 'vue';
import { localBaseService } from '@/services/storage/LocalBaseService';
import { apiClient } from '@/services/api/client';
import type {
  OplogEntry,
  OplogEntityType,
  OplogOperation,
  SyncState,
  SyncBatchResult,
  SubscriptionTier,
} from '@/types';

// Sync configuration
const SYNC_BATCH_SIZE = 50;
const SYNC_INTERVAL_MS = 30000; // 30 seconds
const MAX_RETRY_COUNT = 5;
const RETRY_BACKOFF_MS = [1000, 5000, 15000, 30000, 60000]; // Exponential backoff

class SyncManager {
  // Reactive state
  private _state = ref<SyncState>({
    is_syncing: false,
    is_online: navigator.onLine,
    last_sync_at: null,
    pending_count: 0,
    failed_count: 0,
    last_error: null,
  });

  private syncIntervalId: ReturnType<typeof setInterval> | null = null;
  private retryTimeoutId: ReturnType<typeof setTimeout> | null = null;
  private userTier: SubscriptionTier = 'free';

  // Public readonly state
  public state = readonly(this._state);

  // Computed helpers
  public isPending = computed(() => this._state.value.pending_count > 0);
  public isSyncing = computed(() => this._state.value.is_syncing);
  public isOnline = computed(() => this._state.value.is_online);
  public hasSyncErrors = computed(() => this._state.value.failed_count > 0);

  constructor() {
    this.setupNetworkListeners();
  }

  /**
   * Initialize the sync manager
   */
  async init(tier: SubscriptionTier = 'free'): Promise<void> {
    this.userTier = tier;
    await this.updatePendingCount();

    // Start auto-sync if tier allows
    if (this.isSyncEnabled()) {
      this.startAutoSync();
    }
  }

  /**
   * Check if sync is enabled for current tier
   */
  isSyncEnabled(): boolean {
    return this.userTier === 'cruiser' || this.userTier === 'captain';
  }

  /**
   * Setup network online/offline listeners
   */
  private setupNetworkListeners(): void {
    window.addEventListener('online', () => {
      this._state.value.is_online = true;
      console.log('[SyncManager] Network online - triggering sync');
      if (this.isSyncEnabled()) {
        this.syncNow();
      }
    });

    window.addEventListener('offline', () => {
      this._state.value.is_online = false;
      console.log('[SyncManager] Network offline - sync paused');
    });
  }

  /**
   * Start automatic background sync
   */
  startAutoSync(): void {
    if (this.syncIntervalId) return;

    this.syncIntervalId = setInterval(() => {
      if (this._state.value.is_online && !this._state.value.is_syncing) {
        this.syncNow();
      }
    }, SYNC_INTERVAL_MS);

    console.log('[SyncManager] Auto-sync started');
  }

  /**
   * Stop automatic sync
   */
  stopAutoSync(): void {
    if (this.syncIntervalId) {
      clearInterval(this.syncIntervalId);
      this.syncIntervalId = null;
      console.log('[SyncManager] Auto-sync stopped');
    }
  }

  /**
   * Record an operation in the oplog (Optimistic UI pattern)
   *
   * Call this BEFORE applying the change locally.
   * The change is applied optimistically, and synced in the background.
   */
  async recordOperation<T extends Record<string, unknown>>(
    entityType: OplogEntityType,
    entityId: string,
    operation: OplogOperation,
    data: T,
    previousData?: T
  ): Promise<OplogEntry> {
    const entry = await localBaseService.addOplogEntry(
      entityType,
      entityId,
      operation,
      data as Record<string, unknown>,
      previousData as Record<string, unknown> | undefined
    );

    await this.updatePendingCount();

    // Trigger sync if online and enabled
    if (this._state.value.is_online && this.isSyncEnabled()) {
      // Debounce - don't sync immediately for every operation
      this.scheduleSyncDebounced();
    }

    return entry;
  }

  /**
   * Schedule a debounced sync (500ms delay)
   */
  private syncDebounceTimeout: ReturnType<typeof setTimeout> | null = null;

  private scheduleSyncDebounced(): void {
    if (this.syncDebounceTimeout) {
      clearTimeout(this.syncDebounceTimeout);
    }
    this.syncDebounceTimeout = setTimeout(() => {
      this.syncNow();
    }, 500);
  }

  /**
   * Sync now - push all pending operations to server
   */
  async syncNow(): Promise<SyncBatchResult> {
    if (!this.isSyncEnabled()) {
      return { success: false, applied: 0, failed: 0, conflicts: [] };
    }

    if (!this._state.value.is_online) {
      return { success: false, applied: 0, failed: 0, conflicts: [] };
    }

    if (this._state.value.is_syncing) {
      console.log('[SyncManager] Sync already in progress');
      return { success: false, applied: 0, failed: 0, conflicts: [] };
    }

    this._state.value.is_syncing = true;
    this._state.value.last_error = null;

    try {
      const pendingEntries = await localBaseService.getPendingOplogEntries();

      if (pendingEntries.length === 0) {
        this._state.value.is_syncing = false;
        return { success: true, applied: 0, failed: 0, conflicts: [] };
      }

      console.log(`[SyncManager] Syncing ${pendingEntries.length} operations`);

      // Process in batches
      const result = await this.syncBatch(pendingEntries.slice(0, SYNC_BATCH_SIZE));

      // Update state
      this._state.value.last_sync_at = new Date().toISOString();
      await this.updatePendingCount();

      // Schedule retry if there were failures
      if (result.failed > 0) {
        this.scheduleRetry();
      }

      // Clean up old synced entries
      await localBaseService.clearSyncedOplogEntries(7);

      return result;
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Unknown sync error';
      this._state.value.last_error = errorMessage;
      console.error('[SyncManager] Sync failed:', errorMessage);
      this.scheduleRetry();
      return { success: false, applied: 0, failed: 0, conflicts: [] };
    } finally {
      this._state.value.is_syncing = false;
    }
  }

  /**
   * Sync a batch of oplog entries
   */
  private async syncBatch(entries: OplogEntry[]): Promise<SyncBatchResult> {
    const entryIds = entries.map((e) => e.id);
    await localBaseService.markOplogEntriesSyncing(entryIds);

    try {
      // Send to server
      const response = await apiClient.post<{
        applied: string[];
        failed: Array<{ id: string; error: string; server_version?: number }>;
      }>('/sync/apply', {
        operations: entries.map((e) => ({
          id: e.id,
          entity_type: e.entity_type,
          entity_id: e.entity_id,
          operation: e.operation,
          data: e.data,
          timestamp: e.timestamp,
          checksum: e.checksum,
        })),
      });

      // Mark successful entries as synced
      if (response.applied.length > 0) {
        await localBaseService.markOplogEntriesSynced(response.applied);
      }

      // Mark failed entries and handle conflicts
      const conflicts: SyncBatchResult['conflicts'] = [];
      for (const failure of response.failed) {
        await localBaseService.markOplogEntryFailed(failure.id, failure.error);

        const entry = entries.find((e) => e.id === failure.id);
        if (entry) {
          conflicts.push({
            entry_id: failure.id,
            entity_type: entry.entity_type,
            entity_id: entry.entity_id,
            error: failure.error,
            server_version: failure.server_version,
          });
        }
      }

      return {
        success: response.failed.length === 0,
        applied: response.applied.length,
        failed: response.failed.length,
        conflicts,
      };
    } catch (error) {
      // Network error - mark entries as pending again
      for (const id of entryIds) {
        await localBaseService.markOplogEntryFailed(
          id,
          error instanceof Error ? error.message : 'Network error'
        );
      }
      throw error;
    }
  }

  /**
   * Schedule a retry with exponential backoff
   */
  private scheduleRetry(): void {
    if (this.retryTimeoutId) {
      clearTimeout(this.retryTimeoutId);
    }

    const failedCount = this._state.value.failed_count;
    const backoffIndex = Math.min(failedCount, RETRY_BACKOFF_MS.length - 1);
    const delay = RETRY_BACKOFF_MS[backoffIndex];

    console.log(`[SyncManager] Scheduling retry in ${delay}ms`);

    this.retryTimeoutId = setTimeout(() => {
      this.syncNow();
    }, delay);
  }

  /**
   * Update pending count from oplog
   */
  private async updatePendingCount(): Promise<void> {
    const pending = await localBaseService.getOplogEntriesByStatus('pending');
    const failed = await localBaseService.getOplogEntriesByStatus('failed');

    this._state.value.pending_count = pending.length;
    this._state.value.failed_count = failed.filter(
      (e) => e.retry_count < MAX_RETRY_COUNT
    ).length;
  }

  /**
   * Rollback a failed operation
   */
  async rollbackOperation(entryId: string): Promise<boolean> {
    const success = await localBaseService.rollbackOplogEntry(entryId);
    await this.updatePendingCount();
    return success;
  }

  /**
   * Get sync statistics
   */
  async getStats(): Promise<{
    pending: number;
    synced: number;
    failed: number;
    total: number;
  }> {
    const pending = await localBaseService.getOplogEntriesByStatus('pending');
    const synced = await localBaseService.getOplogEntriesByStatus('synced');
    const failed = await localBaseService.getOplogEntriesByStatus('failed');

    return {
      pending: pending.length,
      synced: synced.length,
      failed: failed.length,
      total: pending.length + synced.length + failed.length,
    };
  }

  /**
   * Force resync all failed entries
   */
  async retryAllFailed(): Promise<void> {
    const failed = await localBaseService.getOplogEntriesByStatus('failed');

    for (const entry of failed) {
      if (entry.retry_count < MAX_RETRY_COUNT) {
        await localBaseService.markOplogEntriesSyncing([entry.id]);
        // Reset to pending status for retry
        await localBaseService.addOplogEntry(
          entry.entity_type,
          entry.entity_id,
          entry.operation,
          entry.data,
          entry.previous_data
        );
      }
    }

    await this.syncNow();
  }

  /**
   * Update user tier (call when user subscription changes)
   */
  setUserTier(tier: SubscriptionTier): void {
    this.userTier = tier;

    if (this.isSyncEnabled()) {
      this.startAutoSync();
    } else {
      this.stopAutoSync();
    }
  }

  /**
   * Cleanup on destroy
   */
  destroy(): void {
    this.stopAutoSync();
    if (this.retryTimeoutId) {
      clearTimeout(this.retryTimeoutId);
    }
    if (this.syncDebounceTimeout) {
      clearTimeout(this.syncDebounceTimeout);
    }
  }
}

// Export singleton instance
export const syncManager = new SyncManager();

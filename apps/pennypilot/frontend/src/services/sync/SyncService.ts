/**
 * SyncService - Server-First Offline Architecture
 *
 * SOURCE OF TRUTH: Server (PostgreSQL)
 * FALLBACK: IndexedDB (browser cache)
 *
 * Logic:
 * 1. Online + Pro → Server first, then cache to IndexedDB
 * 2. Offline OR Free → IndexedDB only
 * 3. Back Online + Pro → Sync IndexedDB pending items to Server
 */

import { transactionsApi } from '@/services/api/transactions.api';
import { localBaseService } from '@/services/storage/LocalBaseService';
import type { Transaction, ParsedTransaction } from '@/types';

export interface SyncResult {
  success: boolean;
  savedTo: 'server' | 'local' | 'both';
  serverCount: number;
  localCount: number;
  pendingSync: number;
  error?: string;
}

export interface PendingSyncItem {
  id: string;
  type: 'transaction' | 'category_update';
  data: Record<string, unknown>;
  createdAt: string;
  attempts: number;
}

const PENDING_SYNC_KEY = 'pendingSync';

class SyncServiceClass {
  private localDb = localBaseService;
  private isOnline = navigator.onLine;
  private syncInProgress = false;

  constructor() {
    // Listen for online/offline events
    window.addEventListener('online', () => {
      this.isOnline = true;
      console.log('🌐 Back online - triggering sync');
      this.syncPendingItems();
    });

    window.addEventListener('offline', () => {
      this.isOnline = false;
      console.log('📴 Went offline - using local storage');
    });
  }

  /**
   * Check if user has Pro tier (can sync to server)
   */
  private async isProUser(): Promise<boolean> {
    // Check user store or localStorage for subscription tier
    const userJson = localStorage.getItem('user');
    if (userJson) {
      try {
        const user = JSON.parse(userJson);
        return user.subscription_tier === 'pro' || user.subscription_tier === 'enterprise';
      } catch {
        return false;
      }
    }
    // Default to true for now (allow server sync)
    // TODO: Properly check from user store
    return true;
  }

  /**
   * Save transactions - Server first if available, else local
   */
  async saveTransactions(transactions: ParsedTransaction[]): Promise<SyncResult> {
    const isPro = await this.isProUser();

    // Prepare payload for server
    const serverPayload = transactions.map(tx => ({
      transaction_date: tx.transactionDate,
      description: tx.description,
      amount: tx.amount,
      balance_after: tx.balanceAfter,
      bank_reference: tx.bankReference,
      raw_description: tx.rawDescription,
      local_id: tx.bankReference || `${tx.transactionDate}-${tx.amount}`,
    }));

    // Prepare payload for local storage
    const localPayload = transactions.map(tx => ({
      id: tx.bankReference || `${tx.transactionDate}-${tx.amount}-${Date.now()}`,
      transaction_date: tx.transactionDate,
      description: tx.description,
      amount: tx.amount,
      balance_after: tx.balanceAfter,
      bank_reference: tx.bankReference,
      raw_description: tx.rawDescription,
      synced: false,
      created_at: new Date().toISOString(),
    }));

    // SCENARIO 1: Online + Pro → Server first, then local cache
    if (this.isOnline && isPro) {
      try {
        const serverResult = await transactionsApi.bulkCreate(serverPayload);
        console.log(`✅ SERVER: Saved ${serverResult.created_count} transactions`);

        // Mark as synced and save to local cache
        const syncedLocal = localPayload.map(tx => ({ ...tx, synced: true }));
        await this.saveToLocalDb(syncedLocal);
        console.log(`✅ LOCAL CACHE: Saved ${syncedLocal.length} transactions`);

        return {
          success: true,
          savedTo: 'both',
          serverCount: serverResult.created_count,
          localCount: syncedLocal.length,
          pendingSync: 0,
        };
      } catch (serverError) {
        console.error('❌ Server save failed, falling back to local:', serverError);
        // Fall through to local-only save
      }
    }

    // SCENARIO 2: Offline OR Free OR Server failed → Local only
    await this.saveToLocalDb(localPayload);
    console.log(`📱 LOCAL ONLY: Saved ${localPayload.length} transactions`);

    // If Pro but offline/failed, queue for sync
    if (isPro) {
      await this.queueForSync(localPayload);
    }

    return {
      success: true,
      savedTo: 'local',
      serverCount: 0,
      localCount: localPayload.length,
      pendingSync: isPro ? localPayload.length : 0,
    };
  }

  /**
   * Save to local IndexedDB
   */
  private async saveToLocalDb(transactions: Record<string, unknown>[]): Promise<void> {
    for (const tx of transactions) {
      await this.localDb.addTransaction(tx as Partial<Transaction>);
    }
  }

  /**
   * Queue items for sync when back online
   */
  private async queueForSync(items: Record<string, unknown>[]): Promise<void> {
    const pending = await this.getPendingItems();

    for (const item of items) {
      pending.push({
        id: item.id as string,
        type: 'transaction',
        data: item,
        createdAt: new Date().toISOString(),
        attempts: 0,
      });
    }

    localStorage.setItem(PENDING_SYNC_KEY, JSON.stringify(pending));
    console.log(`📋 Queued ${items.length} items for sync (total pending: ${pending.length})`);
  }

  /**
   * Get pending sync items
   */
  private async getPendingItems(): Promise<PendingSyncItem[]> {
    const json = localStorage.getItem(PENDING_SYNC_KEY);
    return json ? JSON.parse(json) : [];
  }

  /**
   * Sync pending items to server (called when back online)
   */
  async syncPendingItems(): Promise<SyncResult> {
    if (this.syncInProgress) {
      console.log('⏳ Sync already in progress');
      return { success: false, savedTo: 'local', serverCount: 0, localCount: 0, pendingSync: 0, error: 'Sync in progress' };
    }

    const isPro = await this.isProUser();
    if (!isPro) {
      console.log('🔒 Free tier - no server sync');
      return { success: true, savedTo: 'local', serverCount: 0, localCount: 0, pendingSync: 0 };
    }

    if (!this.isOnline) {
      console.log('📴 Offline - cannot sync');
      return { success: false, savedTo: 'local', serverCount: 0, localCount: 0, pendingSync: 0, error: 'Offline' };
    }

    this.syncInProgress = true;
    const pending = await this.getPendingItems();

    if (pending.length === 0) {
      this.syncInProgress = false;
      return { success: true, savedTo: 'server', serverCount: 0, localCount: 0, pendingSync: 0 };
    }

    console.log(`🔄 Syncing ${pending.length} pending items to server...`);

    try {
      // Group transactions for bulk upload
      const transactions = pending
        .filter(item => item.type === 'transaction')
        .map(item => ({
          transaction_date: item.data.transaction_date as string,
          description: item.data.description as string,
          amount: item.data.amount as number,
          balance_after: item.data.balance_after as number | null,
          bank_reference: item.data.bank_reference as string | null,
          raw_description: item.data.raw_description as string | null,
          local_id: item.id,
        }));

      if (transactions.length > 0) {
        const result = await transactionsApi.bulkCreate(transactions);
        console.log(`✅ Synced ${result.created_count} transactions to server`);

        // Mark as synced in local DB
        for (const item of pending) {
          await this.localDb.updateTransaction(item.id, { sync_status: 'synced' });
        }

        // Clear pending queue
        localStorage.setItem(PENDING_SYNC_KEY, '[]');

        this.syncInProgress = false;
        return {
          success: true,
          savedTo: 'both',
          serverCount: result.created_count,
          localCount: transactions.length,
          pendingSync: 0,
        };
      }

      this.syncInProgress = false;
      return { success: true, savedTo: 'server', serverCount: 0, localCount: 0, pendingSync: 0 };
    } catch (error) {
      console.error('❌ Sync failed:', error);
      this.syncInProgress = false;
      return {
        success: false,
        savedTo: 'local',
        serverCount: 0,
        localCount: 0,
        pendingSync: pending.length,
        error: String(error),
      };
    }
  }

  /**
   * Load transactions - Server first if Pro, else local
   */
  async loadTransactions(startDate?: string, endDate?: string): Promise<Transaction[]> {
    const isPro = await this.isProUser();

    // Pro + Online → Load from server (source of truth)
    if (this.isOnline && isPro) {
      try {
        const response = await transactionsApi.list({
          start_date: startDate,
          end_date: endDate,
          limit: 1000,
        });
        console.log(`✅ Loaded ${response.data.length} transactions from server`);

        // Update local cache with server data
        await this.localDb.addTransactionsFromServer(response.data);

        return response.data;
      } catch (error) {
        console.error('Server load failed, using local:', error);
      }
    }

    // Offline or Free → Load from local
    const localTx = await this.localDb.getAllTransactions();
    console.log(`📱 Loaded ${localTx.length} transactions from local`);
    return localTx;
  }

  /**
   * Get sync status
   */
  async getSyncStatus(): Promise<{
    isOnline: boolean;
    isPro: boolean;
    pendingCount: number;
  }> {
    const pending = await this.getPendingItems();
    return {
      isOnline: this.isOnline,
      isPro: await this.isProUser(),
      pendingCount: pending.length,
    };
  }
}

// Singleton instance
export const SyncService = new SyncServiceClass();

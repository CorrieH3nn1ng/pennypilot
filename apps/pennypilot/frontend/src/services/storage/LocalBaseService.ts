import Localbase from 'localbase';
import { v4 as uuidv4 } from 'uuid';
import type {
  Transaction,
  Category,
  SyncStatus,
  OplogEntry,
  OplogEntityType,
  OplogOperation,
  OplogSyncStatus,
} from '@/types';
import type { UserPersona } from '@/modules/gamification/types/quest.types';

export interface CategoryRule {
  id: string;
  pattern: string; // The keyword/pattern to match
  category_id: string;
  category_name: string;
  match_type: 'contains' | 'starts_with' | 'exact';
  is_user_defined: boolean;
  created_at: string;
  hit_count: number; // How many times this rule was applied
}

/**
 * Skipped record with reason for audit trail
 */
export interface SkippedRecord {
  transaction_date: string;
  description: string;
  amount: number;
  reason: 'duplicate_bank_ref' | 'duplicate_composite' | 'within_batch_duplicate';
  matched_key: string;  // The key that caused the collision
}

/**
 * Bulk import result with deduplication stats
 */
export interface BulkImportResult {
  added: Transaction[];
  duplicateCount: number;
  totalProcessed: number;
  skipped: SkippedRecord[];  // Detailed list of skipped records for audit
}

class LocalBaseService {
  private db: Localbase;

  constructor() {
    this.db = new Localbase('pennypilot');
    this.db.config.debug = import.meta.env.DEV;
  }

  /**
   * Generate a composite key for duplicate detection when bank_reference is NULL.
   * Uses date + amount + description hash to create a deterministic fingerprint.
   */
  private generateCompositeKey(t: Partial<Transaction>): string {
    const date = t.transaction_date || '';
    const amount = (t.amount ?? 0).toFixed(2);
    const description = (t.description || '').substring(0, 50).toUpperCase().trim();

    // Simple hash for browser (no async crypto needed)
    const payload = `${date}|${amount}|${description}`;
    let hash = 0;
    for (let i = 0; i < payload.length; i++) {
      hash = ((hash << 5) - hash) + payload.charCodeAt(i);
      hash = hash & hash;
    }
    return `COMPOSITE:${Math.abs(hash).toString(16).padStart(16, '0').substring(0, 16)}`;
  }

  async init(): Promise<void> {
    // Initialize database - LocalBase auto-initializes on first use
    // This method exists for future initialization needs
    console.log('LocalBase initialized');
  }

  // ========== Transactions ==========

  async getAllTransactions(): Promise<Transaction[]> {
    try {
      const transactions = await this.db.collection('transactions').get();
      return transactions || [];
    } catch {
      return [];
    }
  }

  async getTransactionsByDateRange(start: Date, end: Date): Promise<Transaction[]> {
    const all = await this.getAllTransactions();
    return all.filter((t) => {
      const date = new Date(t.transaction_date);
      return date >= start && date <= end;
    });
  }

  async addTransaction(transaction: Partial<Transaction>): Promise<Transaction> {
    const record: Transaction = {
      ...transaction,
      local_id: transaction.local_id || uuidv4(),
      sync_status: 'pending' as SyncStatus,
      version: 1,
      tags: transaction.tags || [],
      is_categorized: !!transaction.category_id,
      is_transfer: transaction.is_transfer ?? false,
    } as Transaction;

    await this.db.collection('transactions').add(record, record.local_id);
    return record;
  }

  /**
   * Add transactions from server (preserves sync_status as 'synced')
   */
  async addTransactionsFromServer(transactions: Transaction[]): Promise<number> {
    let added = 0;
    for (const tx of transactions) {
      // Convert is_transfer to proper boolean (server might send 0/1 or "0"/"1")
      const isTransfer = tx.is_transfer === true || tx.is_transfer === 1 || (tx.is_transfer as unknown) === '1';

      const record: Transaction = {
        ...tx,
        local_id: tx.local_id || tx.id || '',
        sync_status: 'synced',
        version: tx.version || 1,
        tags: tx.tags || [],
        is_categorized: !!tx.category_id,
        is_transfer: isTransfer,
        // Ensure numeric fields are numbers (server may return strings)
        amount: Number(tx.amount) || 0,
        balance_after: tx.balance_after != null ? Number(tx.balance_after) : null,
      };
      try {
        await this.db.collection('transactions').add(record, record.local_id);
        added++;
      } catch {
        // Skip if already exists
        console.log('Skipping existing transaction:', record.local_id);
      }
    }
    return added;
  }

  async addTransactionsBulk(
    transactions: Partial<Transaction>[],
    onProgress?: (processed: number, total: number) => void
  ): Promise<Transaction[]> {
    const result = await this.addTransactionsBulkWithStats(transactions, onProgress);
    return result.added;
  }

  /**
   * Bulk import with full deduplication statistics
   * Handles large imports (1000+ transactions) efficiently
   * Tracks skipped records for audit trail
   */
  async addTransactionsBulkWithStats(
    transactions: Partial<Transaction>[],
    onProgress?: (processed: number, total: number) => void
  ): Promise<BulkImportResult> {
    const total = transactions.length;
    let duplicateCount = 0;
    const skipped: SkippedRecord[] = [];

    // Get existing bank_references and composite keys to check for duplicates
    const existing = await this.getAllTransactions();
    const existingRefs = new Set<string>();
    const existingFromDatabase = new Set<string>();

    for (const t of existing) {
      if (t.bank_reference) {
        existingRefs.add(t.bank_reference);
        existingFromDatabase.add(t.bank_reference);
      }
    }

    // Filter out duplicates - use bank_reference if available, otherwise generate composite key
    const uniqueTransactions: Partial<Transaction>[] = [];

    for (const t of transactions) {
      if (t.bank_reference) {
        // Has bank reference - check it
        if (existingRefs.has(t.bank_reference)) {
          duplicateCount++;
          // Track why it was skipped
          const wasInDatabase = existingFromDatabase.has(t.bank_reference);
          skipped.push({
            transaction_date: String(t.transaction_date || ''),
            description: String(t.description || ''),
            amount: Number(t.amount) || 0,
            reason: wasInDatabase ? 'duplicate_bank_ref' : 'within_batch_duplicate',
            matched_key: t.bank_reference,
          });
          continue;
        }
        existingRefs.add(t.bank_reference);
        uniqueTransactions.push(t);
      } else {
        // No bank reference - generate composite key
        const compositeKey = this.generateCompositeKey(t);
        if (existingRefs.has(compositeKey)) {
          duplicateCount++;
          // Track why it was skipped
          const wasInDatabase = existingFromDatabase.has(compositeKey);
          skipped.push({
            transaction_date: String(t.transaction_date || ''),
            description: String(t.description || ''),
            amount: Number(t.amount) || 0,
            reason: wasInDatabase ? 'duplicate_composite' : 'within_batch_duplicate',
            matched_key: compositeKey,
          });
          continue;
        }
        // Assign composite key as bank_reference for storage
        t.bank_reference = compositeKey;
        // Add to set to prevent within-batch duplicates
        existingRefs.add(compositeKey);
        uniqueTransactions.push(t);
      }
    }

    const records: Transaction[] = uniqueTransactions.map((t) => ({
      ...t,
      local_id: t.local_id || uuidv4(),
      sync_status: 'pending' as SyncStatus,
      version: 1,
      tags: t.tags || [],
      is_categorized: !!t.category_id,
      is_transfer: t.is_transfer ?? false,
    })) as Transaction[];

    // Process in chunks of 100 for large imports (prevents UI freeze)
    const CHUNK_SIZE = 100;
    let processed = 0;

    for (let i = 0; i < records.length; i += CHUNK_SIZE) {
      const chunk = records.slice(i, i + CHUNK_SIZE);

      for (const record of chunk) {
        await this.db.collection('transactions').add(record, record.local_id);
      }

      processed += chunk.length;

      // Report progress
      if (onProgress) {
        onProgress(processed + duplicateCount, total);
      }

      // Yield to UI thread for large imports
      if (records.length > CHUNK_SIZE && i + CHUNK_SIZE < records.length) {
        await new Promise(resolve => setTimeout(resolve, 0));
      }
    }

    // Log summary with details
    console.log(`[addTransactionsBulk] Imported ${records.length}, skipped ${duplicateCount} duplicates`);
    if (skipped.length > 0) {
      console.log('[addTransactionsBulk] Skipped records breakdown:');
      const byReason = skipped.reduce((acc, s) => {
        acc[s.reason] = (acc[s.reason] || 0) + 1;
        return acc;
      }, {} as Record<string, number>);
      console.table(byReason);
    }

    return {
      added: records,
      duplicateCount,
      totalProcessed: total,
      skipped,
    };
  }

  async updateTransaction(localId: string, updates: Partial<Transaction>): Promise<void> {
    const existing = await this.db.collection('transactions').doc(localId).get();
    if (existing) {
      await this.db.collection('transactions').doc(localId).update({
        ...updates,
        sync_status: 'pending',
        version: (existing.version || 1) + 1,
      });
    }
  }

  async deleteTransaction(localId: string): Promise<void> {
    await this.db.collection('transactions').doc(localId).delete();
  }

  async getPendingSyncTransactions(): Promise<Transaction[]> {
    const all = await this.getAllTransactions();
    return all.filter((t) => t.sync_status === 'pending');
  }

  async markTransactionsSynced(localIds: string[], serverIds: Map<string, string>): Promise<void> {
    for (const localId of localIds) {
      const serverId = serverIds.get(localId);
      // Mark as synced even if no serverId (means it was skipped - already exists on server)
      const updateData: Record<string, unknown> = {
        sync_status: 'synced',
        last_synced_at: new Date().toISOString(),
      };
      if (serverId) {
        updateData.id = serverId;
      }
      await this.db.collection('transactions').doc(localId).update(updateData);
    }
  }

  // ========== Categories ==========

  async getAllCategories(): Promise<Category[]> {
    try {
      const categories = await this.db.collection('categories').get();
      return categories || [];
    } catch {
      return [];
    }
  }

  async setCategories(categories: Category[]): Promise<void> {
    // Clear existing categories (ignore errors if collection doesn't exist)
    try {
      await this.db.collection('categories').delete();
    } catch (e) {
      console.log('No existing categories collection to clear');
    }

    // Add new categories
    for (const cat of categories) {
      await this.db.collection('categories').add(cat, cat.id);
    }
  }

  async addCategory(category: Partial<Category>): Promise<Category> {
    const record: Category = {
      ...category,
      id: category.id || uuidv4(),
      local_id: category.local_id || uuidv4(),
      sync_status: 'pending' as SyncStatus,
    } as Category;

    await this.db.collection('categories').add(record, record.id);
    return record;
  }

  // ========== Sync Metadata ==========

  async getLastSyncTime(): Promise<string | null> {
    try {
      const meta = await this.db.collection('_meta').doc('sync').get();
      return meta?.lastSyncTime || null;
    } catch {
      return null;
    }
  }

  async setLastSyncTime(time: string): Promise<void> {
    await this.db.collection('_meta').doc('sync').set({
      lastSyncTime: time,
    });
  }

  // ========== Category Rules ==========

  async getAllCategoryRules(): Promise<CategoryRule[]> {
    try {
      const rules = await this.db.collection('categoryRules').get();
      return rules || [];
    } catch {
      return [];
    }
  }

  async addCategoryRule(rule: Omit<CategoryRule, 'id' | 'created_at' | 'hit_count'>): Promise<CategoryRule> {
    const record: CategoryRule = {
      ...rule,
      id: uuidv4(),
      created_at: new Date().toISOString(),
      hit_count: 0,
    };

    await this.db.collection('categoryRules').add(record, record.id);
    return record;
  }

  async updateCategoryRuleHitCount(ruleId: string): Promise<void> {
    const existing = await this.db.collection('categoryRules').doc(ruleId).get();
    if (existing) {
      await this.db.collection('categoryRules').doc(ruleId).update({
        hit_count: (existing.hit_count || 0) + 1,
      });
    }
  }

  async deleteCategoryRule(ruleId: string): Promise<void> {
    await this.db.collection('categoryRules').doc(ruleId).delete();
  }

  async findRuleByPattern(pattern: string): Promise<CategoryRule | null> {
    const rules = await this.getAllCategoryRules();
    return rules.find((r) => r.pattern.toLowerCase() === pattern.toLowerCase()) || null;
  }

  // ========== Oplog (Operation Log for Sync) ==========

  /**
   * Add an operation to the oplog for later sync
   */
  async addOplogEntry(
    entityType: OplogEntityType,
    entityId: string,
    operation: OplogOperation,
    data: Record<string, unknown>,
    previousData?: Record<string, unknown>
  ): Promise<OplogEntry> {
    const entry: OplogEntry = {
      id: uuidv4(),
      timestamp: Date.now(),
      entity_type: entityType,
      entity_id: entityId,
      operation,
      data,
      previous_data: previousData,
      sync_status: 'pending',
      retry_count: 0,
      checksum: this.generateChecksum(data),
    };

    await this.db.collection('oplog').add(entry, entry.id);
    return entry;
  }

  /**
   * Get all pending oplog entries (not yet synced)
   */
  async getPendingOplogEntries(): Promise<OplogEntry[]> {
    try {
      const entries = await this.db.collection('oplog').get();
      if (!entries) return [];
      return entries
        .filter((e: OplogEntry) => e.sync_status === 'pending' || e.sync_status === 'failed')
        .sort((a: OplogEntry, b: OplogEntry) => a.timestamp - b.timestamp);
    } catch {
      return [];
    }
  }

  /**
   * Get oplog entries by status
   */
  async getOplogEntriesByStatus(status: OplogSyncStatus): Promise<OplogEntry[]> {
    try {
      const entries = await this.db.collection('oplog').get();
      if (!entries) return [];
      return entries
        .filter((e: OplogEntry) => e.sync_status === status)
        .sort((a: OplogEntry, b: OplogEntry) => a.timestamp - b.timestamp);
    } catch {
      return [];
    }
  }

  /**
   * Mark oplog entries as syncing (in progress)
   */
  async markOplogEntriesSyncing(entryIds: string[]): Promise<void> {
    for (const id of entryIds) {
      await this.db.collection('oplog').doc(id).update({
        sync_status: 'syncing' as OplogSyncStatus,
      });
    }
  }

  /**
   * Mark oplog entries as synced (success)
   */
  async markOplogEntriesSynced(entryIds: string[]): Promise<void> {
    for (const id of entryIds) {
      await this.db.collection('oplog').doc(id).update({
        sync_status: 'synced' as OplogSyncStatus,
        synced_at: Date.now(),
      });
    }
  }

  /**
   * Mark oplog entry as failed with error message
   */
  async markOplogEntryFailed(entryId: string, errorMessage: string): Promise<void> {
    const existing = await this.db.collection('oplog').doc(entryId).get();
    if (existing) {
      await this.db.collection('oplog').doc(entryId).update({
        sync_status: 'failed' as OplogSyncStatus,
        retry_count: (existing.retry_count || 0) + 1,
        error_message: errorMessage,
      });
    }
  }

  /**
   * Get count of pending oplog entries
   */
  async getPendingOplogCount(): Promise<number> {
    const entries = await this.getPendingOplogEntries();
    return entries.length;
  }

  /**
   * Clear synced oplog entries (cleanup)
   * Keep entries from the last N days for debugging
   */
  async clearSyncedOplogEntries(keepDays: number = 7): Promise<number> {
    try {
      const entries = await this.db.collection('oplog').get();
      if (!entries) return 0;

      const cutoffTime = Date.now() - keepDays * 24 * 60 * 60 * 1000;
      let cleared = 0;

      for (const entry of entries) {
        if (
          entry.sync_status === 'synced' &&
          entry.synced_at &&
          entry.synced_at < cutoffTime
        ) {
          await this.db.collection('oplog').doc(entry.id).delete();
          cleared++;
        }
      }

      return cleared;
    } catch {
      return 0;
    }
  }

  /**
   * Generate a simple checksum for data integrity
   */
  private generateChecksum(data: Record<string, unknown>): string {
    const str = JSON.stringify(data);
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = (hash << 5) - hash + char;
      hash = hash & hash; // Convert to 32bit integer
    }
    return Math.abs(hash).toString(16);
  }

  /**
   * Get oplog entry by ID
   */
  async getOplogEntry(entryId: string): Promise<OplogEntry | null> {
    try {
      return await this.db.collection('oplog').doc(entryId).get();
    } catch {
      return null;
    }
  }

  /**
   * Rollback an oplog entry (restore previous data)
   * Used when server rejects a change
   */
  async rollbackOplogEntry(entryId: string): Promise<boolean> {
    try {
      const entry = await this.getOplogEntry(entryId);
      if (!entry || !entry.previous_data) return false;

      // Restore the entity to its previous state
      const collection = this.getCollectionForEntityType(entry.entity_type);
      if (!collection) return false;

      if (entry.operation === 'create') {
        // If it was a create, delete it
        await this.db.collection(collection).doc(entry.entity_id).delete();
      } else if (entry.operation === 'update') {
        // If it was an update, restore previous data
        await this.db.collection(collection).doc(entry.entity_id).set(entry.previous_data);
      } else if (entry.operation === 'delete') {
        // If it was a delete, restore the entity
        await this.db.collection(collection).add(entry.previous_data, entry.entity_id);
      }

      // Delete the oplog entry
      await this.db.collection('oplog').doc(entryId).delete();
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Get the collection name for an entity type
   */
  private getCollectionForEntityType(entityType: OplogEntityType): string | null {
    const mapping: Record<OplogEntityType, string> = {
      transaction: 'transactions',
      category: 'categories',
      invoice: 'invoices',
      client: 'clients',
      income_source: 'income_sources',
      fixed_expense: 'fixed_expenses',
    };
    return mapping[entityType] || null;
  }

  // ========== User Persona (Gamification) ==========

  /**
   * Get the user's persona (badges, XP, quests, financial goals)
   */
  async getPersona(): Promise<UserPersona | null> {
    try {
      const persona = await this.db.collection('persona').doc('user').get();
      return persona || null;
    } catch {
      return null;
    }
  }

  /**
   * Save the user's persona
   * Handles both fresh creation and updates
   */
  async savePersona(persona: UserPersona): Promise<void> {
    try {
      // Try set first (for existing docs)
      await this.db.collection('persona').doc('user').set(persona);
    } catch {
      // If set fails (collection was wiped), try add
      try {
        await this.db.collection('persona').add(persona, 'user');
      } catch (e) {
        console.log('Persona save deferred - will retry on next interaction');
        throw e;
      }
    }
  }

  /**
   * Clear persona data (for testing/reset)
   */
  async clearPersona(): Promise<void> {
    try {
      await this.db.collection('persona').delete();
    } catch {
      console.log('No persona collection to clear');
    }
  }

  // ========== Utilities ==========

  async clearAll(): Promise<void> {
    // Delete each collection individually to avoid IndexedDB errors
    // when trying to delete stores that don't exist
    try {
      await this.db.collection('transactions').delete();
    } catch (e) {
      console.log('No transactions collection to clear');
    }
    try {
      await this.db.collection('categories').delete();
    } catch (e) {
      console.log('No categories collection to clear');
    }
    try {
      await this.db.collection('category_rules').delete();
    } catch (e) {
      console.log('No category_rules collection to clear');
    }
    try {
      await this.db.collection('persona').delete();
    } catch (e) {
      console.log('No persona collection to clear');
    }
  }

  async clearTransactions(): Promise<void> {
    try {
      await this.db.collection('transactions').delete();
    } catch (e) {
      console.log('No transactions to clear');
    }
  }

  async getTransactionCount(): Promise<number> {
    const all = await this.getAllTransactions();
    return all.length;
  }
}

export const localBaseService = new LocalBaseService();

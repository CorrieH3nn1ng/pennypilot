import Localbase from 'localbase';
import { v4 as uuidv4 } from 'uuid';
import type { Transaction, Category, SyncStatus } from '@/types';

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

class LocalBaseService {
  private db: Localbase;

  constructor() {
    this.db = new Localbase('pennypilot');
    this.db.config.debug = import.meta.env.DEV;
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
    } as Transaction;

    await this.db.collection('transactions').add(record, record.local_id);
    return record;
  }

  async addTransactionsBulk(transactions: Partial<Transaction>[]): Promise<Transaction[]> {
    const records: Transaction[] = transactions.map((t) => ({
      ...t,
      local_id: t.local_id || uuidv4(),
      sync_status: 'pending' as SyncStatus,
      version: 1,
      tags: t.tags || [],
      is_categorized: !!t.category_id,
    })) as Transaction[];

    // LocalBase doesn't have bulk insert, so we batch manually
    for (const record of records) {
      await this.db.collection('transactions').add(record, record.local_id);
    }

    return records;
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
      if (serverId) {
        await this.db.collection('transactions').doc(localId).update({
          id: serverId,
          sync_status: 'synced',
          last_synced_at: new Date().toISOString(),
        });
      }
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
    // Clear existing categories
    await this.db.collection('categories').delete();

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

  // ========== Utilities ==========

  async clearAll(): Promise<void> {
    await this.db.delete();
  }

  async clearTransactions(): Promise<void> {
    await this.db.collection('transactions').delete();
  }

  async getTransactionCount(): Promise<number> {
    const all = await this.getAllTransactions();
    return all.length;
  }
}

export const localBaseService = new LocalBaseService();

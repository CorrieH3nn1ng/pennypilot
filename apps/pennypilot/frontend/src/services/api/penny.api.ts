import { apiClient } from './client';

/**
 * Penny Memory - User context stored in database
 */
export interface PennyMemory {
  id: string;
  user_id: string;
  // Identity
  display_name: string | null;
  age: number | null;
  primary_client: string | null;
  occupation: string | null;
  // Boss Goal
  boss_goal_name: string | null;
  boss_goal_target: number | null;
  boss_goal_current: number;
  boss_goal_deadline: string | null;
  // Quadrant Checksums (no-repeat logic)
  has_quests: boolean;
  has_inventory: boolean;
  has_traits: boolean;
  has_destination: boolean;
  // JSON Data
  inventory_items: InventoryItem[] | null;
  character_traits: CharacterTrait[] | null;
  daily_quests: DailyQuest[] | null;
  // Conversation
  has_met_penny: boolean;
  last_interaction: string | null;
  last_briefing_date: string | null;
  quest_streak: number;
  total_xp: number;
  // Realm
  current_location: string | null;
  target_realm: string | null;
  target_realm_cost: number | null;
  // Status
  is_healthy: boolean;
  is_secure: boolean;
  // n8n
  n8n_session_id: string | null;
  created_at: string;
  updated_at: string;
}

export interface InventoryItem {
  id: string;
  name: string;
  alias: string;
  icon: string;
  target: number;
  current: number;
}

export interface CharacterTrait {
  id: string;
  name: string;
  icon: string;
  progress: number;
  unlocked: boolean;
}

export interface DailyQuest {
  id: string;
  name: string;
  xp: number;
  completed: boolean;
}

/**
 * n8n Context - Full user context for AI
 */
export interface N8nContext {
  user_id: string;
  identity: {
    name: string | null;
    age: number | null;
    client: string | null;
    occupation: string | null;
  };
  boss_goal: {
    name: string | null;
    target: number | null;
    current: number;
    progress_percent: number;
    deadline: string | null;
  };
  quadrants: {
    has_quests: boolean;
    has_inventory: boolean;
    has_traits: boolean;
    has_destination: boolean;
  };
  data: {
    quests: DailyQuest[];
    inventory: InventoryItem[];
    traits: CharacterTrait[];
  };
  stats: {
    total_xp: number;
    quest_streak: number;
    level: number;
  };
  realm: {
    current: string | null;
    target: string | null;
    target_cost: number | null;
  };
  status: {
    healthy: boolean;
    secure: boolean;
  };
  conversation: {
    has_met_penny: boolean;
    last_interaction: string | null;
    last_briefing: string | null;
    session_id: string | null;
  };
  missing_fields: string[];
}

export interface MemoryResponse {
  data: PennyMemory;
  context: N8nContext;
  missing_fields: string[];
}

export interface ChatResponse {
  reply: string;
  action: {
    type: string;
    label: string;
    color?: string;
    data?: unknown;
    route?: string;
  } | null;
  memory_updated?: boolean;
}

export interface MemoryUpdatePayload {
  display_name?: string;
  age?: number;
  primary_client?: string;
  occupation?: string;
  boss_goal_name?: string;
  boss_goal_target?: number;
  boss_goal_current?: number;
  boss_goal_deadline?: string;
  inventory_items?: InventoryItem[];
  character_traits?: CharacterTrait[];
  daily_quests?: DailyQuest[];
  quest_streak?: number;
  total_xp?: number;
  current_location?: string;
  target_realm?: string;
  target_realm_cost?: number;
  is_healthy?: boolean;
  is_secure?: boolean;
  has_met_penny?: boolean;
  last_briefing_date?: string;
}

export interface AvatarSyncPayload {
  name?: string;
  totalXP?: number;
  questStreak?: number;
  quests?: DailyQuest[];
  inventory?: InventoryItem[];
  attributes?: CharacterTrait[];
  targetRealm?: { location: string; cost: number };
  currentLocation?: string;
  healthy?: boolean;
  secure?: boolean;
}

/**
 * Penny API Service - n8n Integration
 */
export const pennyApi = {
  /**
   * Get user's Penny memory (creates if not exists)
   * Called when chat opens to fetch full context
   */
  async getMemory(): Promise<MemoryResponse> {
    // PennyController returns { data, context, missing_fields } directly
    return await apiClient.getRaw<MemoryResponse>('/penny/memory');
  },

  /**
   * Update Penny memory with new data
   */
  async updateMemory(payload: MemoryUpdatePayload): Promise<MemoryResponse> {
    // PennyController returns { data, context, message } directly
    return await apiClient.putRaw<MemoryResponse>('/penny/memory', payload);
  },

  /**
   * Sync avatar data from frontend to memory
   */
  async syncFromAvatar(payload: AvatarSyncPayload): Promise<MemoryResponse> {
    // PennyController returns { data, context, message } directly
    return await apiClient.postRaw<MemoryResponse>('/penny/sync-avatar', payload);
  },

  /**
   * Send message to Penny via n8n webhook
   */
  async chat(message: string, action?: string): Promise<ChatResponse> {
    // PennyController returns { reply, action, memory_updated } directly
    return await apiClient.postRaw<ChatResponse>('/penny/chat', {
      message,
      action,
    });
  },

  /**
   * Add a quest to memory
   */
  async addQuest(name: string, xp: number): Promise<{ data: PennyMemory; message: string }> {
    // PennyController returns { data, message } directly
    return await apiClient.postRaw<{ data: PennyMemory; message: string }>('/penny/quest', {
      name,
      xp,
    });
  },

  /**
   * Add an item to inventory
   */
  async addItem(
    name: string,
    alias: string,
    target: number,
    current: number = 0,
    icon: string = 'savings'
  ): Promise<{ data: PennyMemory; message: string }> {
    // PennyController returns { data, message } directly
    return await apiClient.postRaw<{ data: PennyMemory; message: string }>('/penny/item', {
      name,
      alias,
      target,
      current,
      icon,
    });
  },

  /**
   * Set boss goal
   */
  async setBossGoal(
    name: string,
    target: number,
    current: number = 0,
    deadline?: string
  ): Promise<{ data: PennyMemory; message: string }> {
    // PennyController returns { data, message } directly
    return await apiClient.postRaw<{ data: PennyMemory; message: string }>('/penny/boss-goal', {
      name,
      target,
      current,
      deadline,
    });
  },
};

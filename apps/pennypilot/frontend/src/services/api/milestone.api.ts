import { apiClient } from './client';

export interface Milestone {
  id: string;
  title: string;
  description: string | null;
  due_date: string;
  type: 'reminder' | 'review' | 'payment' | 'goal';
  status: 'pending' | 'completed' | 'dismissed';
  priority: 'low' | 'medium' | 'high' | 'critical';
  category: string | null;
  meta: Record<string, unknown> | null;
  completed_at: string | null;
  dismissed_at: string | null;
  created_at: string;
  updated_at: string;
  // Computed
  is_overdue?: boolean;
  days_until_due?: number;
  is_urgent?: boolean;
}

export interface UpcomingMilestones {
  upcoming: Milestone[];
  overdue: Milestone[];
}

export interface CreateMilestoneData {
  title: string;
  description?: string;
  due_date: string;
  type?: 'reminder' | 'review' | 'payment' | 'goal';
  priority?: 'low' | 'medium' | 'high' | 'critical';
  category?: string;
  meta?: Record<string, unknown>;
}

export const milestoneApi = {
  /**
   * Get all milestones
   */
  async getAll(params?: {
    status?: string;
    type?: string;
    category?: string;
    upcoming?: number;
  }): Promise<Milestone[]> {
    const queryParams = new URLSearchParams();
    if (params?.status) queryParams.append('status', params.status);
    if (params?.type) queryParams.append('type', params.type);
    if (params?.category) queryParams.append('category', params.category);
    if (params?.upcoming) queryParams.append('upcoming', params.upcoming.toString());

    const query = queryParams.toString();
    return apiClient.get<Milestone[]>(`/milestones${query ? `?${query}` : ''}`);
  },

  /**
   * Get upcoming and overdue milestones (for dashboard alerts)
   */
  async getUpcoming(days = 30): Promise<UpcomingMilestones> {
    return apiClient.get<UpcomingMilestones>(`/milestones/upcoming?days=${days}`);
  },

  /**
   * Create a new milestone
   */
  async create(data: CreateMilestoneData): Promise<Milestone> {
    return apiClient.post<Milestone>('/milestones', data);
  },

  /**
   * Get a specific milestone
   */
  async get(id: string): Promise<Milestone> {
    return apiClient.get<Milestone>(`/milestones/${id}`);
  },

  /**
   * Update a milestone
   */
  async update(id: string, data: Partial<CreateMilestoneData>): Promise<Milestone> {
    return apiClient.put<Milestone>(`/milestones/${id}`, data);
  },

  /**
   * Delete a milestone
   */
  async delete(id: string): Promise<void> {
    return apiClient.delete(`/milestones/${id}`);
  },

  /**
   * Mark a milestone as complete
   */
  async complete(id: string): Promise<Milestone> {
    return apiClient.post<Milestone>(`/milestones/${id}/complete`);
  },

  /**
   * Dismiss a milestone
   */
  async dismiss(id: string): Promise<Milestone> {
    return apiClient.post<Milestone>(`/milestones/${id}/dismiss`);
  },

  /**
   * Snooze a milestone
   */
  async snooze(id: string, days = 7): Promise<Milestone> {
    return apiClient.post<Milestone>(`/milestones/${id}/snooze`, { days });
  },
};

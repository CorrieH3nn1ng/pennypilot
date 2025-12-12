import { apiClient } from './client';
import type { Category } from '@/types';

export const categoriesApi = {
  async list(): Promise<Category[]> {
    return apiClient.get<Category[]>('/categories');
  },

  async create(data: {
    name: string;
    icon?: string;
    color?: string;
    parent_id?: string;
    is_income?: boolean;
  }): Promise<Category> {
    return apiClient.post<Category>('/categories', data);
  },

  async update(id: string, data: Partial<Category>): Promise<Category> {
    return apiClient.put<Category>(`/categories/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/categories/${id}`);
  },
};

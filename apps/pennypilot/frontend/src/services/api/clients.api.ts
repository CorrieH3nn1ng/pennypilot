import { apiClient } from './client';
import type { Client, ClientsResponse } from '@/types';

export interface CreateClientData {
  name: string;
  email?: string;
  contact_person?: string;
  phone?: string;
  address?: string;
  notes?: string;
  billing_period_start?: number;
  billing_period_end?: number | null;
  payment_day?: number | null;
  payment_terms?: number | null;
  default_rate?: number | null;
  rate_type?: 'hourly' | 'daily' | 'fixed' | null;
  is_active?: boolean;
}

export interface UpdateClientData extends Partial<CreateClientData> {}

export const clientApi = {
  async list(): Promise<ClientsResponse> {
    return apiClient.getRaw<ClientsResponse>('/clients');
  },

  async get(id: string): Promise<{ data: Client }> {
    return apiClient.getRaw<{ data: Client }>(`/clients/${id}`);
  },

  async create(data: CreateClientData): Promise<Client> {
    return apiClient.post<Client>('/clients', data);
  },

  async update(id: string, data: UpdateClientData): Promise<Client> {
    return apiClient.put<Client>(`/clients/${id}`, data);
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/clients/${id}`);
  },
};

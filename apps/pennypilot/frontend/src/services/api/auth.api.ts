import { apiClient } from './client';
import type { AuthResponse, User } from '@/types';

export const authApi = {
  async register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    consent_given: boolean;
  }): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/register', data);
    apiClient.setToken(response.token);
    return response;
  },

  async login(email: string, password: string): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/login', {
      email,
      password,
    });
    apiClient.setToken(response.token);
    return response;
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout');
    apiClient.clearToken();
  },

  async getUser(): Promise<User> {
    return apiClient.get<User>('/auth/user');
  },

  async updateProfile(data: { name?: string; email?: string }): Promise<User> {
    return apiClient.put<User>('/auth/profile', data);
  },

  async changePassword(currentPassword: string, newPassword: string): Promise<void> {
    await apiClient.put('/auth/password', {
      current_password: currentPassword,
      password: newPassword,
      password_confirmation: newPassword,
    });
  },
};

import { apiClient } from './client';
import type { BusinessProfile, BusinessProfileResponse } from '@/types';

export interface UpdateBusinessProfileData {
  // Business Info
  business_name?: string;
  trading_name?: string;
  registration_number?: string;
  tax_number?: string;
  vat_number?: string;
  // Contact Info
  email?: string;
  phone?: string;
  website?: string;
  address?: string;
  // Banking Details
  bank_name?: string;
  bank_branch?: string;
  bank_branch_code?: string;
  account_holder?: string;
  account_number?: string;
  account_type?: 'cheque' | 'savings' | 'transmission' | 'current';
  // Invoice Settings
  invoice_prefix?: string;
  invoice_notes?: string;
  payment_terms?: string;
  default_tax_rate?: number;
}

export const businessProfileApi = {
  async get(): Promise<BusinessProfileResponse> {
    return apiClient.getRaw<BusinessProfileResponse>('/business-profile');
  },

  async update(data: UpdateBusinessProfileData): Promise<BusinessProfile> {
    return apiClient.put<BusinessProfile>('/business-profile', data);
  },

  async uploadLogo(file: File): Promise<{ data: BusinessProfile; logo_url: string }> {
    const formData = new FormData();
    formData.append('logo', file);
    return apiClient.postFormData<{ data: BusinessProfile; logo_url: string }>(
      '/business-profile/logo',
      formData
    );
  },

  async removeLogo(): Promise<BusinessProfile> {
    const response = await apiClient.getRaw<{ data: BusinessProfile }>('/business-profile/logo');
    return response.data;
  },
};

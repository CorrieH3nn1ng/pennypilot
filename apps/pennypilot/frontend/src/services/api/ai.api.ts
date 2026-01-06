import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

function getAuthHeaders() {
  const token = localStorage.getItem('auth_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export interface ExtractedInvoiceData {
  invoice_number: string | null;
  invoice_date: string | null;
  due_date: string | null;
  client_name: string | null;
  client_email: string | null;
  subtotal: number | null;
  tax_rate: number | null;
  tax_amount: number | null;
  total: number | null;
  currency: string | null;
  payment_reference: string | null;
  line_items: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
  }>;
  confidence: number | null;
  notes: string | null;
}

export interface MatchedClient {
  id: string;
  name: string;
  client_code: string;
  billing_period_start?: number;
  billing_period_end?: number | null;
  payment_day?: number | null;
  payment_terms?: number | null;
  default_rate?: number | null;
  rate_type?: 'hourly' | 'daily' | 'fixed' | null;
}

export interface PotentialTransaction {
  id: string;
  date: string;
  amount: number;
  description: string;
}

export interface InvoiceExtractionResponse {
  success: boolean;
  data?: ExtractedInvoiceData;
  matched_client?: MatchedClient | null;
  potential_transactions?: PotentialTransaction[];
  message?: string;
  raw_response?: string;
}

export interface AiStatusResponse {
  available: boolean;
  provider: string;
}

export const aiApi = {
  /**
   * Check if AI extraction is available
   */
  async getStatus(): Promise<AiStatusResponse> {
    const response = await axios.get<AiStatusResponse>(`${API_URL}/ai/status`, {
      headers: {
        ...getAuthHeaders(),
        Accept: 'application/json',
      },
    });
    return response.data;
  },

  /**
   * Extract invoice data from a PDF or image file
   */
  async extractInvoice(file: File): Promise<InvoiceExtractionResponse> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await axios.post<InvoiceExtractionResponse>(
      `${API_URL}/ai/extract-invoice`,
      formData,
      {
        headers: {
          ...getAuthHeaders(),
          'Content-Type': 'multipart/form-data',
        },
        timeout: 60000, // 60 second timeout for AI processing
      }
    );
    return response.data;
  },
};

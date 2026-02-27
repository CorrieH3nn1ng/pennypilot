import { apiClient } from './client';

export interface BusinessLocation {
  lat: number;
  lng: number;
  name?: string;
}

export interface TelemetryLocations {
  home: {
    lat: number;
    lng: number;
  };
  business_locations: BusinessLocation[];
}

export interface TripSummary {
  total_trips: number;
  business_trips: number;
  commute_trips: number;
  private_trips: number;
  skipped_trips: number;
  total_km: number;
  business_km: number;
  commute_km: number;
  private_km: number;
  deductible_amount: number;
  sars_rate: number;
}

export interface ImportedTrip {
  date: string;
  start_time: string;
  end_time?: string;
  origin: string;
  destination: string;
  distance_km: number;
  duration_minutes?: number;
  category: 'Business' | 'Commute' | 'Private';
}

export interface TelemetryImportResult {
  success: boolean;
  dry_run: boolean;
  summary: TripSummary;
  errors: string[];
  trips: ImportedTrip[];
}

export interface TravelDashboardSummary {
  total_trips: number;
  total_km: number;
  business: {
    trips: number;
    km: number;
  };
  private: {
    trips: number;
    km: number;
  };
  commute: {
    trips: number;
    km: number;
  };
  tax_deductible: number;
  sars_rate: number;
  date_range: {
    first: string | null;
    last: string | null;
  };
}

export interface LogbookTrip {
  id: number;
  date: string;
  start_time: string | null;
  end_time: string | null;
  origin: string;
  destination: string;
  distance_km: number;
  odometer_start: number | null;
  odometer_end: number | null;
  vehicle_id: string | null;
  purpose: string | null;
  is_mirror: boolean;
}

export interface LogbookResponse {
  category: string;
  trips: LogbookTrip[];
  total_trips: number;
  total_km: number;
  tax_deductible: number;
  sars_rate: number;
}

export const telemetryApi = {
  /**
   * Import a Timeline.json file
   */
  async import(
    file: File,
    homeLat: number,
    homeLng: number,
    businessLocations: BusinessLocation[] = [],
    dryRun = false
  ): Promise<TelemetryImportResult> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('home_lat', homeLat.toString());
    formData.append('home_lng', homeLng.toString());
    formData.append('dry_run', dryRun ? '1' : '0');

    if (businessLocations.length > 0) {
      businessLocations.forEach((loc, index) => {
        formData.append(`business_locations[${index}][lat]`, loc.lat.toString());
        formData.append(`business_locations[${index}][lng]`, loc.lng.toString());
        if (loc.name) {
          formData.append(`business_locations[${index}][name]`, loc.name);
        }
      });
    }

    return apiClient.postFormDataRaw<TelemetryImportResult>('/telemetry/import', formData);
  },

  /**
   * Get saved geofence locations
   */
  async getLocations(): Promise<TelemetryLocations> {
    return apiClient.getRaw<TelemetryLocations>('/telemetry/locations');
  },

  /**
   * Save geofence locations
   */
  async saveLocations(
    homeLat: number,
    homeLng: number,
    businessLocations: BusinessLocation[] = []
  ): Promise<void> {
    await apiClient.postRaw('/telemetry/locations', {
      home_lat: homeLat,
      home_lng: homeLng,
      business_locations: businessLocations,
    });
  },

  /**
   * Get travel summary for dashboard
   */
  async getSummary(): Promise<TravelDashboardSummary> {
    return apiClient.getRaw<TravelDashboardSummary>('/telemetry/summary');
  },

  /**
   * Get trips list for logbook view
   */
  async getTrips(category: 'Business' | 'Private' = 'Business', vehicleId?: string): Promise<LogbookResponse> {
    const params = new URLSearchParams({ category });
    if (vehicleId) params.append('vehicle_id', vehicleId);
    return apiClient.getRaw<LogbookResponse>(`/telemetry/trips?${params.toString()}`);
  },
};

export default telemetryApi;

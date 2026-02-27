<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TelemetryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TelemetryController extends Controller
{
    public function __construct(
        private TelemetryImportService $importService
    ) {}

    /**
     * Upload and process a Google Timeline JSON file
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'home_lat' => 'required|numeric',
            'home_lng' => 'required|numeric',
            'business_locations' => 'nullable|array',
            'business_locations.*.lat' => 'required_with:business_locations|numeric',
            'business_locations.*.lng' => 'required_with:business_locations|numeric',
            'business_locations.*.name' => 'nullable|string',
            'dry_run' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $tempPaths = [];

        // Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['json', 'zip'])) {
            return response()->json([
                'success' => false,
                'error' => 'File must be a JSON or ZIP file (Google Takeout)',
            ], 422);
        }

        try {
            if ($extension === 'zip') {
                // Extract Timeline JSON from Google Takeout ZIP
                $fullPath = $this->extractTimelineFromZip($file, $tempPaths);
            } else {
                $tempPath = $file->store('telemetry/temp', 'local');
                $tempPaths[] = $tempPath;
                $fullPath = Storage::disk('local')->path($tempPath);
            }

            $homeLocation = [
                'lat' => (float) $request->input('home_lat'),
                'lng' => (float) $request->input('home_lng'),
            ];

            $businessLocations = $request->input('business_locations', []);

            $result = $this->importService->import(
                $fullPath,
                $user,
                $homeLocation,
                $businessLocations,
                (bool) $request->input('dry_run', false)
            );

            // Clean up temp files
            foreach ($tempPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            // Clean up temp files on error
            foreach ($tempPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to process file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract Timeline JSON from a Google Takeout ZIP file.
     * Looks for Timeline Edits.json, Semantic Location History, or Records.json.
     */
    private function extractTimelineFromZip($file, array &$tempPaths): string
    {
        $zipTempPath = $file->store('telemetry/temp', 'local');
        $tempPaths[] = $zipTempPath;
        $zipFullPath = Storage::disk('local')->path($zipTempPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipFullPath) !== true) {
            throw new \RuntimeException('Could not open ZIP file');
        }

        // Priority order for timeline data files
        $candidates = [
            'Timeline Edits.json',
            'Timeline_Edits.json',
            'Records.json',
            'Semantic Location History.json',
            'Location History.json',
        ];

        $foundEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            foreach ($candidates as $candidate) {
                if (str_ends_with($entryName, $candidate)) {
                    $foundEntry = $entryName;
                    break 2;
                }
            }
        }

        if (!$foundEntry) {
            $zip->close();
            throw new \RuntimeException('No Timeline data found in ZIP. Expected Timeline Edits.json or Records.json inside the Takeout archive.');
        }

        // Extract the JSON file
        $jsonContent = $zip->getFromName($foundEntry);
        $zip->close();

        if ($jsonContent === false) {
            throw new \RuntimeException("Could not extract {$foundEntry} from ZIP");
        }

        // Write to temp file
        $jsonTempPath = 'telemetry/temp/' . uniqid('timeline_') . '.json';
        Storage::disk('local')->put($jsonTempPath, $jsonContent);
        $tempPaths[] = $jsonTempPath;

        return Storage::disk('local')->path($jsonTempPath);
    }

    /**
     * Get user's saved geofence locations
     */
    public function getLocations(Request $request): JsonResponse
    {
        $user = $request->user();

        // For now return defaults - could be stored in user settings later
        return response()->json([
            'home' => [
                'lat' => -25.73630055231681,
                'lng' => 27.854198033004796,
            ],
            'business_locations' => [
                [
                    'lat' => -25.996357394362125,
                    'lng' => 27.91677581374382,
                    'name' => 'Nucleus Mining',
                ],
            ],
        ]);
    }

    /**
     * Save user's geofence locations
     */
    public function saveLocations(Request $request): JsonResponse
    {
        $request->validate([
            'home_lat' => 'required|numeric',
            'home_lng' => 'required|numeric',
            'business_locations' => 'nullable|array',
            'business_locations.*.lat' => 'required_with:business_locations|numeric',
            'business_locations.*.lng' => 'required_with:business_locations|numeric',
            'business_locations.*.name' => 'nullable|string',
        ]);

        // TODO: Store in user settings table
        // For now just return success

        return response()->json([
            'success' => true,
            'message' => 'Locations saved',
        ]);
    }

    /**
     * Get trips list for logbook view (printable)
     */
    public function trips(Request $request): JsonResponse
    {
        $user = $request->user();
        $category = $request->input('category', 'Business');

        $query = Trip::where('user_id', $user->id)
            ->where('category', $category);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->input('vehicle_id'));
        }

        $trips = $query->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get()
            ->map(function ($trip) {
                return [
                    'id' => $trip->id,
                    'date' => $trip->date->format('Y-m-d'),
                    'start_time' => $trip->start_time ? $trip->start_time->format('H:i') : null,
                    'end_time' => $trip->end_time ? $trip->end_time->format('H:i') : null,
                    'origin' => $trip->origin,
                    'destination' => $trip->destination,
                    'distance_km' => (float) $trip->distance_km,
                    'odometer_start' => $trip->odometer_start,
                    'odometer_end' => $trip->odometer_end,
                    'vehicle_id' => $trip->vehicle_id,
                    'purpose' => $trip->purpose,
                    'is_mirror' => $trip->is_mirror ?? false,
                ];
            });

        $totalKm = $trips->sum('distance_km');
        $sarsRate = TelemetryImportService::SARS_RATE_2026;

        return response()->json([
            'category' => $category,
            'trips' => $trips,
            'total_trips' => $trips->count(),
            'total_km' => round($totalKm, 2),
            'tax_deductible' => $category === 'Business' ? round($totalKm * $sarsRate, 2) : 0,
            'sars_rate' => $sarsRate,
        ]);
    }

    /**
     * Get travel summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get trip statistics by category
        $stats = Trip::where('user_id', $user->id)
            ->selectRaw("
                category,
                COUNT(*) as trips,
                COALESCE(SUM(distance_km), 0) as total_km
            ")
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $businessTrips = $stats->get('Business');
        $privateTrips = $stats->get('Private');
        $commuteTrips = $stats->get('Commute');

        $businessKm = (float) ($businessTrips->total_km ?? 0);
        $privateKm = (float) ($privateTrips->total_km ?? 0);
        $commuteKm = (float) ($commuteTrips->total_km ?? 0);
        $totalKm = $businessKm + $privateKm + $commuteKm;

        // SARS rate
        $sarsRate = TelemetryImportService::SARS_RATE_2026;
        $taxDeductible = $businessKm * $sarsRate;

        // Get date range
        $dateRange = Trip::where('user_id', $user->id)
            ->selectRaw('MIN(date) as first_date, MAX(date) as last_date')
            ->first();

        return response()->json([
            'total_trips' => (int) (($businessTrips->trips ?? 0) + ($privateTrips->trips ?? 0) + ($commuteTrips->trips ?? 0)),
            'total_km' => round($totalKm, 2),
            'business' => [
                'trips' => (int) ($businessTrips->trips ?? 0),
                'km' => round($businessKm, 2),
            ],
            'private' => [
                'trips' => (int) ($privateTrips->trips ?? 0),
                'km' => round($privateKm, 2),
            ],
            'commute' => [
                'trips' => (int) ($commuteTrips->trips ?? 0),
                'km' => round($commuteKm, 2),
            ],
            'tax_deductible' => round($taxDeductible, 2),
            'sars_rate' => $sarsRate,
            'date_range' => [
                'first' => $dateRange->first_date,
                'last' => $dateRange->last_date,
            ],
        ]);
    }
}

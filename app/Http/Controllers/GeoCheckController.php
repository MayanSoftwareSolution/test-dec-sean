<?php

namespace App\Http\Controllers;

use Exception;
use App\Services\GeoCheckService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use App\Http\Requests\ValidateLocationCheckRequest;

class GeoCheckController extends Controller
{

    /**
     * Testing Interface
     *
     * What it does:
     * - Generates a web page for testing geofence locations
     * - Provides a form with input fields for latitude, longitude, and accuracy
     * - Includes a set of predefined test cases for demonstration
     *
     * Predefined Test Cases Cover:
     * - Points inside the geofence
     * - Points near geofence boundaries
     * - Points with different accuracy thresholds
     */
    public function renderGeoCheckForm(): Factory|View|Application
    {
        return view('input-location-form', [
            'testCases' => [
                ['lat' => 50.840473, 'lon' => -0.146755, 'accuracy' => 30],
                ['lat' => 50.842458, 'lon' => -0.150285, 'accuracy' => 5],
                ['lat' => 50.843317, 'lon' => -0.144960, 'accuracy' => 0],
                ['lat' => 44.197736, 'lon' => 1.183339, 'accuracy' => 1000000],
                ['lat' => 50.854067, 'lon' => -0.163824, 'accuracy' => 100]
            ]
        ]);
    }

    /**
     * Location Verification Endpoint
     *
     * What it does:
     * - Receives location verification request
     * - Validates input parameters
     * - Calls GeofenceService to check location
     * - Returns result with human-readable message
     *
     * Validation Ensures:
     * - Latitude and longitude are numeric
     * - Accuracy is a non-negative number
     * @param ValidateLocationCheckRequest $request
     * @return array
     * @throws Exception
     */
    public function submitLocation(ValidateLocationCheckRequest $request): array
    {
        // Validate form data. Validation request will throw a validation error if the data is not valid
        $request->validated();

        // Destructure the request data
        $longitude = $request->input('longitude');
        $latitude = $request->input('latitude');
        $accuracy = $request->input('accuracy');

        $service = new GeoCheckService();

        // Proceeds the submitted location data through the GeoCheckService
        $result = $service->isLocationWithinArea(
            longitude: $longitude,
            latitude: $latitude,
            accuracy: $accuracy
        );

        return [
            'result' => $result,
            'message' => $result ? 'Location is within geofence' : 'Location is outside geofence'
        ];
    }
}

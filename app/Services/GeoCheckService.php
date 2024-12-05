<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Storage;

/**
 * GeoCheckService: Geographic Boundary Verification Scripts
 *
 * This service checks whether a given geographic coordinate
 * falls within a predefined area (geofence).
 *
 * Core Functionality:
 * - Reads location data from a JSON file
 * - Determines if a point is inside or near a polygon
 * - Calculates distance to polygon boundaries
 * - Handles accuracy thresholds for location checking
 */
class GeoCheckService
{
    // Stores the location data to define the geofence area
    public array $geoFenceCoordinates;

    /**
     * Constructor: Load Geofence Coordinates
     *
     * What it does:
     * - Reads the geofence.json file from storage
     * - Extracts coordinate points defining the geofence
     * @throws Exception
     */
    public function __construct()
    {
       $getFile = json_decode(Storage::disk('public')->get('geofence.json'), true);
       $this->geoFenceCoordinates = $getFile['results']['geometry'][0] ?? null;
    }

    /**
     * What it does:
     * - Checks if a given geographic coordinate is within the geofence
     * - Supports an accuracy threshold for near-boundary locations
     * - Returns true if location is inside or within accuracy range
     * - Returns false if location is outside the defined area
     *
     * @param float $longitude Geographic longitude coordinate
     * @param float $latitude Geographic latitude coordinate
     * @param float $accuracy Allowable distance outside boundary (in metres)
     * @return bool Whether location is within or near the geofence
     * @throws Exception
     */
    public function isLocationWithinArea(float $longitude, float $latitude, float $accuracy = 0): bool
    {
        // Prevent processing if no geofence coordinates are loaded
        if (!$this->geoFenceCoordinates) {
            throw new Exception('GeoJson data not found');
        }

        // Convert input and geofence coordinates to radians for trigonometric calculations
        $point = $this->convertToRadians($longitude, $latitude);
        $polygon = array_map(fn($coord) => $this->convertToRadians($coord['lon'], $coord['lat']), $this->geoFenceCoordinates);

        // Check if point is inside the polygon using ray-casting algorithm
        $inside = $this->pointInPolygon($point, $polygon);

        // If point is inside polygon, immediately return true
        if ($inside) {
            return true;
        }

        // If no accuracy threshold is set, return false for points outside polygon
        if ($accuracy == 0) {
            return false;
        }

        // Calculate distance to nearest polygon boundary
        $nearestDistance = $this->calculateNearestDistance($point, $polygon);

        // Check if distance is within the specified accuracy threshold
        return $nearestDistance <= $accuracy;
    }

    /**
     *  What it does:
     *  - Converts geographic coordinates from degrees to radians
     *
     * @param float $lon Longitude
     * @param float $lat Latitude
     * @return array Coordinates in radians
     */
    private function convertToRadians(float $lon, float $lat): array
    {
        return [
            'lon' => deg2rad($lon),
            'lat' => deg2rad($lat)
        ];
    }

    /**
     * What it does:
     * Determine if point is inside polygon using ray casting algorithm
     *
     * @param array $point Point coordinates in radians
     * @param array $polygon Polygon coordinates in radians
     * @return bool Whether point is inside polygon
     */
    private function pointInPolygon(array $point, array $polygon): bool
    {
        // Sets a default inside state to false
        $inside = false;
        $count = count($polygon);

        // Loops through each polygon edge to determine if point is inside
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            if (
                (($polygon[$i]['lat'] > $point['lat']) != ($polygon[$j]['lat'] > $point['lat'])) &&
                ($point['lon'] < ($polygon[$j]['lon'] - $polygon[$i]['lon']) *
                    ($point['lat'] - $polygon[$i]['lat']) /
                    ($polygon[$j]['lat'] - $polygon[$i]['lat']) + $polygon[$i]['lon'])
            ) {
                // Toggles the inside state if the point crosses an edge
                $inside = !$inside;
            }
        }

        // Returns whether the point is inside the polygon
        return $inside;
    }

    /**
     *  What it does:
     * Calculates the distance to the nearest geofence edge
     *
     * @param array $point Point coordinates in radians
     * @param array $polygon Polygon coordinates in radians
     * @return float Distance in metres
     */
    private function calculateNearestDistance(array $point, array $polygon): float
    {
        // Earth's radius in metres
        $earthRadius = 6371000;
        // Initialize the nearest distance to a very large number
        $nearestDistance = PHP_FLOAT_MAX;

        // Loop through each edge of the polygon
        for ($i = 0, $j = count($polygon) - 1; $i < count($polygon); $j = $i++) {
            // Calculate the distance from the point to the current edge
            $distance = $this->pointToLineDistance(
                $point,
                $polygon[$i],
                $polygon[$j],
                $earthRadius
            );
            // Update the nearest distance if the current distance is smaller
            $nearestDistance = min($nearestDistance, $distance);
        }

        // Return the smallest distance found
        return $nearestDistance;
    }

    /**
     *  What it does:
     * Calculate distance from a point to a line segment
     *
     *
     * @param array $point Point coordinates
     * @param array $lineStart Start of line segment
     * @param array $lineEnd End of line segment
     * @param float $radius Earth's radius
     * @return float Distance in metres
     */
    private function pointToLineDistance(array $point, array $lineStart, array $lineEnd, float $radius): float
    {
        // Haversine formula for distance between points
        $dLat = $lineEnd['lat'] - $lineStart['lat'];
        $dLon = $lineEnd['lon'] - $lineStart['lon'];

        // Apply the Haversine formula to calculate the distance between the line endpoints
        $a = sin($dLat/2) * sin($dLat/2) +
            cos($lineStart['lat']) * cos($lineEnd['lat']) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $lineLength = $radius * $c;

        // If line segment is essentially a point
        if ($lineLength < 0.01) {
            return $this->haversineDistance($point, $lineStart, $radius);
        }

        // If the line segment is short return the distance from the point to this "point"
        $t = (
                (($point['lon'] - $lineStart['lon']) * ($lineEnd['lon'] - $lineStart['lon'])) +
                (($point['lat'] - $lineStart['lat']) * ($lineEnd['lat'] - $lineStart['lat']))
            ) / ($lineLength * $lineLength);

        // This is to ensure the nearest point is within the line segment
        $t = max(0, min(1, $t));

        // Calculate the nearest point
        $nearestPoint = [
            'lon' => $lineStart['lon'] + $t * ($lineEnd['lon'] - $lineStart['lon']),
            'lat' => $lineStart['lat'] + $t * ($lineEnd['lat'] - $lineStart['lat'])
        ];

        return $this->haversineDistance($point, $nearestPoint, $radius);
    }

    /**
     *  What it does:
     * Calculate Haversine distance between two points
     *
     * @param array $point1 First point coordinates
     * @param array $point2 Second point coordinates
     * @param float $radius Earth's radius
     * @return float Distance in metres
     */
    private function haversineDistance(array $point1, array $point2, float $radius): float
    {
        // Calculate the difference in latitude and longitude
        $dLat = $point2['lat'] - $point1['lat'];
        $dLon = $point2['lon'] - $point1['lon'];

        // Apply the Haversine formula
        $a = sin($dLat/2) * sin($dLat/2) +
            cos($point1['lat']) * cos($point2['lat']) *
            sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        // Return the distance in meters
        return $radius * $c;
    }

}

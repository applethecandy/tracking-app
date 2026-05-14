<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class RouteMetricsService
{
    public function prepare(array $points): array
    {
        $points = $this->withElevations($points);

        return [
            'points' => $points,
            'metrics' => $this->calculate($points),
        ];
    }

    public function calculate(array $points): array
    {
        $distance = 0.0;
        $gain = 0.0;
        $loss = 0.0;

        for ($i = 1; $i < count($points); $i++) {
            $previous = $points[$i - 1];
            $current = $points[$i];

            $distance += $this->distanceBetween($previous, $current);

            if (isset($previous['ele'], $current['ele'])) {
                $delta = (float) $current['ele'] - (float) $previous['ele'];

                if ($delta > 0) {
                    $gain += $delta;
                } else {
                    $loss += abs($delta);
                }
            }
        }

        return [
            'distance_m' => (int) round($distance),
            'elevation_gain_m' => (int) round($gain),
            'elevation_loss_m' => (int) round($loss),
        ];
    }

    private function withElevations(array $points): array
    {
        if (count($points) === 0 || collect($points)->every(fn (array $point) => isset($point['ele']))) {
            return $points;
        }

        try {
            foreach (array_chunk(array_keys($points), 100) as $indexes) {
                $chunk = collect($indexes)->map(fn (int $index) => $points[$index]);

                $response = Http::timeout(8)->get('https://api.open-meteo.com/v1/elevation', [
                    'latitude' => $chunk->pluck('lat')->implode(','),
                    'longitude' => $chunk->pluck('lng')->implode(','),
                ]);

                if (! $response->successful()) {
                    return $points;
                }

                $elevations = $response->json('elevation', []);

                foreach ($indexes as $position => $pointIndex) {
                    if (isset($elevations[$position])) {
                        $points[$pointIndex]['ele'] = (float) $elevations[$position];
                    }
                }
            }
        } catch (Throwable) {
            return $points;
        }

        return $points;
    }

    private function distanceBetween(array $from, array $to): float
    {
        $earthRadius = 6371000;
        $lat1 = deg2rad((float) $from['lat']);
        $lat2 = deg2rad((float) $to['lat']);
        $deltaLat = deg2rad((float) $to['lat'] - (float) $from['lat']);
        $deltaLng = deg2rad((float) $to['lng'] - (float) $from['lng']);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

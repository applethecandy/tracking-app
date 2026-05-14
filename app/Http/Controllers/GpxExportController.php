<?php

namespace App\Http\Controllers;

use App\Models\TrackRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class GpxExportController extends Controller
{
    public function route(Request $request, TrackRoute $trackRoute): Response
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        return $this->download($trackRoute);
    }

    public function public(string $shareToken): Response
    {
        $route = TrackRoute::query()
            ->where('is_shared', true)
            ->where('share_token', $shareToken)
            ->firstOrFail();

        return $this->download($route);
    }

    private function download(TrackRoute $route): Response
    {
        $filename = Str::slug($route->title) ?: 'route';
        $filename .= '.gpx';

        return response($this->toGpx($route), 200, [
            'Content-Type' => 'application/gpx+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function toGpx(TrackRoute $route): string
    {
        $name = htmlspecialchars($route->title, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $date = $route->activity_date->format('Y-m-d');

        $points = collect($route->points)->map(function (array $point): string {
            $lat = number_format((float) $point['lat'], 7, '.', '');
            $lng = number_format((float) $point['lng'], 7, '.', '');
            $ele = isset($point['ele'])
                ? '<ele>'.htmlspecialchars((string) $point['ele'], ENT_XML1 | ENT_COMPAT, 'UTF-8').'</ele>'
                : '';

            return "      <trkpt lat=\"{$lat}\" lon=\"{$lng}\">{$ele}</trkpt>";
        })->implode("\n");

        return <<<GPX
<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="Tracking" xmlns="http://www.topografix.com/GPX/1/1">
  <metadata>
    <name>{$name}</name>
    <time>{$date}T00:00:00Z</time>
  </metadata>
  <trk>
    <name>{$name}</name>
    <type>{$route->activity_type}</type>
    <trkseg>
{$points}
    </trkseg>
  </trk>
</gpx>
GPX;
    }
}

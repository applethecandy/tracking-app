<?php

namespace App\Http\Controllers;

use App\Models\TrackRoute;
use Inertia\Inertia;
use Inertia\Response;

class PublicRouteController extends Controller
{
    public function show(string $shareToken): Response
    {
        $route = TrackRoute::query()
            ->where('is_shared', true)
            ->where('share_token', $shareToken)
            ->firstOrFail();

        return Inertia::render('Routes/PublicShow', [
            'routeModel' => [
                'id' => $route->id,
                'title' => $route->title,
                'activity_date' => $route->activity_date->format('Y-m-d'),
                'duration_minutes' => $route->duration_minutes,
                'activity_type' => $route->activity_type,
                'activity_label' => $route->activity_label,
                'comment' => $route->comment,
                'points' => $route->points,
                'distance_m' => $route->distance_m,
                'elevation_gain_m' => $route->elevation_gain_m,
                'elevation_loss_m' => $route->elevation_loss_m,
                'gpx_url' => route('public.routes.gpx', $route->share_token),
            ],
        ]);
    }
}

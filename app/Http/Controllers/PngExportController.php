<?php

namespace App\Http\Controllers;

use App\Models\TrackRoute;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PngExportController extends Controller
{
    public function route(Request $request, TrackRoute $trackRoute): Response
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        return Inertia::render('Routes/PngExport', [
            'routeModel' => [
                'id' => $trackRoute->id,
                'title' => $trackRoute->title,
                'activity_date' => $trackRoute->activity_date->format('Y-m-d'),
                'duration_minutes' => $trackRoute->duration_minutes,
                'activity_type' => $trackRoute->activity_type,
                'activity_label' => $trackRoute->activity_label,
                'comment' => $trackRoute->comment,
                'points' => $trackRoute->points,
                'distance_m' => $trackRoute->distance_m,
                'elevation_gain_m' => $trackRoute->elevation_gain_m,
                'elevation_loss_m' => $trackRoute->elevation_loss_m,
                'is_shared' => $trackRoute->is_shared,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TrackRoute;
use App\Services\RouteMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrackRouteController extends Controller
{
    public function __construct(private readonly RouteMetricsService $routeMetrics) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'activity_type' => ['nullable', Rule::in(array_keys(TrackRoute::ACTIVITIES))],
        ]);

        $query = $request->user()->trackRoutes()->latest('activity_date');

        if (! empty($filters['date_from'])) {
            $query->whereDate('activity_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('activity_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        $routes = $query->paginate(12)->withQueryString();
        $totals = (clone $query)
            ->selectRaw('COUNT(*) as routes_count, COALESCE(SUM(distance_m), 0) as distance_m, COALESCE(SUM(elevation_gain_m), 0) as elevation_gain_m')
            ->first();

        return Inertia::render('Routes/Index', [
            'routes' => $routes,
            'filters' => $filters,
            'activities' => TrackRoute::ACTIVITIES,
            'totals' => [
                'routes_count' => (int) $totals->routes_count,
                'distance_m' => (int) $totals->distance_m,
                'elevation_gain_m' => (int) $totals->elevation_gain_m,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Routes/Form', [
            'routeModel' => null,
            'activities' => TrackRoute::ACTIVITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRouteData($request);
        $prepared = $this->routeMetrics->prepare($data['points']);
        $data['points'] = $prepared['points'];

        $route = $request->user()->trackRoutes()->create([
            ...$data,
            ...$prepared['metrics'],
            'share_token' => $data['is_shared'] ? Str::random(40) : null,
        ]);

        return redirect()->route('routes.show', $route);
    }

    public function show(Request $request, TrackRoute $trackRoute): Response
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        return Inertia::render('Routes/Show', [
            'routeModel' => $this->serializeRoute($trackRoute),
            'activities' => TrackRoute::ACTIVITIES,
        ]);
    }

    public function edit(Request $request, TrackRoute $trackRoute): Response
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        return Inertia::render('Routes/Form', [
            'routeModel' => $this->serializeRoute($trackRoute),
            'activities' => TrackRoute::ACTIVITIES,
        ]);
    }

    public function update(Request $request, TrackRoute $trackRoute): RedirectResponse
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        $data = $this->validatedRouteData($request);
        $prepared = $this->routeMetrics->prepare($data['points']);
        $data['points'] = $prepared['points'];

        $trackRoute->update([
            ...$data,
            ...$prepared['metrics'],
            'share_token' => $data['is_shared']
                ? ($trackRoute->share_token ?: Str::random(40))
                : null,
        ]);

        return redirect()->route('routes.show', $trackRoute);
    }

    public function destroy(Request $request, TrackRoute $trackRoute): RedirectResponse
    {
        abort_unless($trackRoute->user_id === $request->user()->id, 403);

        $trackRoute->delete();

        return redirect()->route('routes.index');
    }

    private function validatedRouteData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'activity_date' => ['required', 'date'],
            'activity_type' => ['required', Rule::in(array_keys(TrackRoute::ACTIVITIES))],
            'comment' => ['nullable', 'string'],
            'is_shared' => ['boolean'],
            'points' => ['required', 'array', 'min:2'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.ele' => ['nullable', 'numeric'],
        ]);
    }

    private function serializeRoute(TrackRoute $route): array
    {
        return [
            'id' => $route->id,
            'title' => $route->title,
            'activity_date' => $route->activity_date->format('Y-m-d'),
            'activity_type' => $route->activity_type,
            'activity_label' => $route->activity_label,
            'comment' => $route->comment,
            'points' => $route->points,
            'distance_m' => $route->distance_m,
            'elevation_gain_m' => $route->elevation_gain_m,
            'elevation_loss_m' => $route->elevation_loss_m,
            'is_shared' => $route->is_shared,
            'share_url' => $route->is_shared && $route->share_token
                ? route('public.routes.show', $route->share_token)
                : null,
        ];
    }
}

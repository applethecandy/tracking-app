<?php

namespace Tests\Feature;

use App\Models\TrackRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_route_and_export_gpx(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'elevation' => [10, 34],
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('routes.store'), [
            'title' => 'Evening skate',
            'activity_date' => '2026-05-14',
            'activity_type' => 'skateboard',
            'comment' => 'Dry asphalt.',
            'is_shared' => true,
            'points' => [
                ['lat' => 55.751244, 'lng' => 37.618423],
                ['lat' => 55.752500, 'lng' => 37.630000],
            ],
        ]);

        $route = TrackRoute::query()->firstOrFail();

        $response->assertRedirect(route('routes.show', $route));
        $this->assertSame($user->id, $route->user_id);
        $this->assertSame('skateboard', $route->activity_type);
        $this->assertTrue($route->is_shared);
        $this->assertNotNull($route->share_token);
        $this->assertGreaterThan(0, $route->distance_m);
        $this->assertSame(24, $route->elevation_gain_m);
        $this->assertEquals(10.0, $route->points[0]['ele']);
        $this->assertEquals(34.0, $route->points[1]['ele']);

        $this->actingAs($user)
            ->get(route('routes.gpx', $route))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/gpx+xml; charset=UTF-8')
            ->assertSee('<trkpt lat="55.7512440" lon="37.6184230">', false);
    }

    public function test_private_route_is_not_available_by_public_link(): void
    {
        $route = TrackRoute::factory()->for(User::factory())->create([
            'is_shared' => false,
            'share_token' => 'hidden-token',
        ]);

        $this->get(route('public.routes.show', $route->share_token))->assertNotFound();
    }

    public function test_user_can_open_png_export_page_for_own_route(): void
    {
        $user = User::factory()->create();
        $route = TrackRoute::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('routes.png', $route))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Routes/PngExport')
                ->where('routeModel.id', $route->id)
            );
    }
}

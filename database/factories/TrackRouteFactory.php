<?php

namespace Database\Factories;

use App\Models\TrackRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrackRoute>
 */
class TrackRouteFactory extends Factory
{
    protected $model = TrackRoute::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'activity_date' => $this->faker->date(),
            'activity_type' => 'walk',
            'comment' => $this->faker->optional()->sentence(),
            'points' => [
                ['lat' => 55.751244, 'lng' => 37.618423],
                ['lat' => 55.752500, 'lng' => 37.630000],
            ],
            'distance_m' => 740,
            'elevation_gain_m' => 0,
            'elevation_loss_m' => 0,
            'is_shared' => false,
            'share_token' => null,
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => [
            'is_shared' => true,
            'share_token' => Str::random(40),
        ]);
    }
}

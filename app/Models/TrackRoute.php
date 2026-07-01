<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackRoute extends Model
{
    use HasFactory;

    public const ACTIVITIES = [
        'walk' => 'Ходьба',
        'run' => 'Бег',
        'skateboard' => 'Скейтбординг',
        'car' => 'Авто',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'activity_date',
        'duration_minutes',
        'activity_type',
        'comment',
        'points',
        'distance_m',
        'elevation_gain_m',
        'elevation_loss_m',
        'is_shared',
        'share_token',
    ];

    protected $casts = [
        'activity_date' => 'date:Y-m-d',
        'duration_minutes' => 'integer',
        'points' => 'array',
        'is_shared' => 'boolean',
    ];

    protected $appends = [
        'activity_label',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivityLabelAttribute(): string
    {
        return self::ACTIVITIES[$this->activity_type] ?? ($this->activity_type ?: 'Не указано');
    }
}

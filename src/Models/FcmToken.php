<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $token
 * @property bool $is_valid
 * @property bool $is_primary
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class FcmToken extends Model
{
    protected $table = 'fcm_tokens';

    protected $fillable = [
        'token',
        'is_valid',
        'is_primary',
        'metadata',
        'last_used_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $token) {
            if (! isset($token->last_used_at)) {
                $token->last_used_at = now();
            }
        });
    }

    /**
     * Get the parent tokenable model.
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include valid tokens.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope a query to only include primary tokens.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include tokens not used since a given date.
     */
    public function scopeNotUsedSince(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('last_used_at', '<', $date);
    }
}

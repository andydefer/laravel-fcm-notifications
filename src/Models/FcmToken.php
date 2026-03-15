<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Eloquent model representing a Firebase Cloud Messaging (FCM) device token.
 *
 * This model stores FCM tokens associated with various notifiable entities
 * (users, devices, etc.) through a polymorphic relationship. It tracks token
 * validity and usage timestamps for automatic cleanup of stale tokens.
 *
 * The "primary" token concept is now determined dynamically as the most recent
 * token based on the `last_used_at` timestamp, eliminating the need for a
 * dedicated database field.
 *
 * @package Andydefer\FcmNotifications\Models
 *
 * @property int $id Unique identifier for the token record
 * @property string $tokenable_type The class name of the parent model
 * @property int $tokenable_id The ID of the parent model
 * @property string $token The FCM device token string
 * @property bool $is_valid Whether the token is currently valid for sending
 * @property array<string, mixed>|null $metadata Additional token metadata (device info, platform, etc.)
 * @property Carbon|null $last_used_at Timestamp of the last successful notification send
 * @property Carbon $created_at Timestamp when the token was registered
 * @property Carbon $updated_at Timestamp when the token was last updated
 *
 * @property-read bool $is_primary Dynamically determines if this is the primary token (most recent)
 *
 * @method static Builder|static valid() Scope to include only valid tokens
 * @method static Builder|static notUsedSince(DateTimeInterface $date) Scope to tokens unused since given date
 */
class FcmToken extends Model
{
    /**
     * The database table associated with the model.
     *
     * @var string
     */
    protected $table = 'fcm_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'token',
        'is_valid',
        'metadata',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_valid' => 'boolean',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'is_primary',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_valid' => true,
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * This method sets up model event listeners:
     * - Automatically sets last_used_at on creation if not provided
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            if (!isset($token->last_used_at)) {
                $token->last_used_at = now();
            }
        });
    }

    /**
     * Get the parent tokenable model (polymorphic relationship).
     *
     * This relationship allows any model (User, Device, etc.) to have multiple
     * FCM tokens associated with it.
     *
     * @return MorphTo
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if this token is the primary token for its tokenable.
     *
     * A token is considered primary if it's the most recently used valid token
     * among all tokens belonging to the same tokenable entity. This is determined
     * dynamically based on the `last_used_at` timestamp.
     *
     * @return bool True if this is the most recent valid token
     */
    public function getIsPrimaryAttribute(): bool
    {
        if (!$this->is_valid || !$this->tokenable) {
            return false;
        }

        /** @var FcmToken|null $mostRecentToken */
        $mostRecentToken = $this->tokenable->fcmTokens()
            ->valid()
            ->orderBy('last_used_at', 'desc')
            ->first();

        return $mostRecentToken && $mostRecentToken->id === $this->id;
    }

    /**
     * Scope the query to include only valid tokens.
     *
     * Valid tokens are those marked as valid and ready to receive notifications.
     * Invalid tokens are typically those that have expired or returned errors.
     *
     * @param Builder $query The Eloquent query builder instance
     * @return Builder
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope the query to include tokens that haven't been used since a given date.
     *
     * This scope is useful for cleaning up stale tokens that haven't received
     * notifications for a specified period.
     *
     * @param Builder $query The Eloquent query builder instance
     * @param DateTimeInterface $date The cutoff date (tokens unused since this date)
     * @return Builder
     *
     * @example
     * ```php
     * // Get tokens not used in the last 30 days
     * $staleTokens = FcmToken::notUsedSince(now()->subDays(30))->get();
     * ```
     */
    public function scopeNotUsedSince(Builder $query, DateTimeInterface $date): Builder
    {
        return $query->where('last_used_at', '<', $date);
    }

    /**
     * Mark the token as used at the current timestamp.
     *
     * This method updates the last_used_at timestamp without firing model events
     * to avoid unnecessary event processing for frequent updates.
     *
     * @return bool True if the update was successful
     */
    public function markAsUsed(): bool
    {
        return $this->newQuery()
            ->whereKey($this->getKey())
            ->update(['last_used_at' => now()]) > 0;
    }

    /**
     * Invalidate the token.
     *
     * This method marks the token as invalid, preventing it from being used
     * for future notifications. It's typically called when FCM returns a
     * token error (expired, unregistered, etc.).
     *
     * @return bool True if the update was successful
     */
    public function invalidate(): bool
    {
        return $this->newQuery()
            ->whereKey($this->getKey())
            ->update(['is_valid' => false]) > 0;
    }
}

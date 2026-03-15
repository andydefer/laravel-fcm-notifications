<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Traits;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;

/**
 * Trait for Eloquent models that can receive Firebase Cloud Messaging notifications.
 *
 * This trait provides the complete implementation of the HasFcmToken interface,
 * allowing any Eloquent model (typically User) to manage FCM tokens and receive
 * push notifications. It handles token registration, validation, invalidation,
 * and automatic cleanup of old tokens.
 *
 * **Note on Primary Tokens:**
 * The concept of a "primary" token is now determined dynamically as the most
 * recently used valid token. The `is_primary` database field is maintained for
 * backward compatibility but is no longer actively used in the logic.
 *
 * @package Andydefer\FcmNotifications\Traits
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @see HasFcmToken
 * @see FcmToken
 */
trait HasFcmNotifications
{
    /**
     * Boot the trait and register model event listeners.
     *
     * Automatically cleans up all associated FCM tokens when the model is deleted.
     *
     * @return void
     */
    public static function bootHasFcmNotifications(): void
    {
        static::deleted(function ($model): void {
            $model->fcmTokens()->delete();
        });
    }

    /**
     * Initialize the trait (optional).
     *
     * This method is called by Laravel when the trait is used. It can be used
     * to set default values or initialize any properties.
     *
     * @return void
     */
    public function initializeHasFcmNotifications(): void
    {
        // Optional initialization logic can be added here
    }

    /**
     * Get the polymorphic relationship for FCM tokens.
     *
     * @return MorphMany
     */
    public function fcmTokens(): MorphMany
    {
        return $this->morphMany(FcmToken::class, 'tokenable');
    }

    /**
     * Get all valid FCM tokens for this notifiable entity.
     *
     * Returns an array of tokens ordered by last used date (most recent first),
     * limited to the maximum configured number of tokens per notifiable.
     *
     * @return array<string> Array of valid FCM tokens
     */
    public function getFcmTokens(): array
    {
        return $this->fcmTokens()
            ->valid()
            ->latest('last_used_at')
            ->limit($this->getMaxTokensPerNotifiable())
            ->pluck('token')
            ->toArray();
    }

    /**
     * Get the primary FCM token for this notifiable entity.
     *
     * The primary token is determined as the most recently used valid token.
     * This is a dynamic calculation that ensures the most active token is
     * always considered primary.
     *
     * @return string|null The most recent valid token, or null if none exist
     */
    public function getPrimaryFcmToken(): ?string
    {
        return $this->fcmTokens()
            ->valid()
            ->latest('last_used_at')
            ->value('token');
    }

    /**
     * Check if this notifiable entity has any valid FCM tokens.
     *
     * @return bool True if at least one valid token exists
     */
    public function hasFcmTokens(): bool
    {
        return $this->fcmTokens()->valid()->exists();
    }

    /**
     * Register a new FCM token for this notifiable entity.
     *
     * This method handles:
     * - Token limit enforcement (oldest tokens are removed when limit is exceeded)
     * - Token update if it already exists
     *
     * Note: The `isPrimary` parameter is maintained for backward compatibility
     * but is no longer used. Primary status is now determined by last_used_at.
     *
     * @param string $token The FCM token to register
     * @param bool $isPrimary Deprecated: Maintained for backward compatibility
     * @param array<string, mixed> $metadata Additional metadata to store with the token
     * @return FcmToken The registered or updated token model instance
     */
    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken {
        // First, enforce the token limit (will remove oldest tokens if needed)
        $this->enforceTokenLimitBeforeRegistration();

        // Then register the new token
        $fcmToken = $this->fcmTokens()->updateOrCreate(
            ['token' => $token],
            [
                'is_valid' => true,
                'metadata' => $metadata,
                'last_used_at' => now(),
            ]
        );

        // Finally, enforce the limit again (in case the token already existed)
        $this->enforceTokenLimitAfterRegistration();

        return $fcmToken->fresh();
    }

    /**
     * Enforce the maximum token limit before registering a new token.
     *
     * @return void
     */
    private function enforceTokenLimitBeforeRegistration(): void
    {
        $maxTokens = $this->getMaxTokensPerNotifiable();
        $validTokenCount = $this->fcmTokens()->valid()->count();

        // If we're at or above the limit, we need to remove the oldest tokens
        if ($validTokenCount >= $maxTokens) {
            $this->removeOldestTokens($validTokenCount - $maxTokens + 1);
        }
    }

    /**
     * Enforce the maximum token limit after registering a token.
     *
     * This handles cases where the token already existed and we didn't
     * add a new token, but the count might still be over the limit.
     *
     * @return void
     */
    private function enforceTokenLimitAfterRegistration(): void
    {
        $maxTokens = $this->getMaxTokensPerNotifiable();
        $validTokenCount = $this->fcmTokens()->valid()->count();

        if ($validTokenCount > $maxTokens) {
            $this->removeOldestTokens($validTokenCount - $maxTokens);
        }
    }

    /**
     * Remove the oldest valid tokens.
     *
     * @param int $count Number of oldest tokens to remove
     * @return void
     */
    private function removeOldestTokens(int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $oldestTokens = $this->fcmTokens()
            ->valid()
            ->orderBy('last_used_at', 'asc')
            ->limit($count)
            ->get();

        foreach ($oldestTokens as $token) {
            $token->delete();
        }
    }

    /**
     * Invalidate a specific FCM token.
     *
     * Marks the token as invalid without deleting it, which is useful for
     * tracking historical tokens and debugging.
     *
     * @param string $token The token to invalidate
     * @return bool True if the token was found and invalidated
     */
    public function invalidateFcmToken(string $token): bool
    {
        return (bool) $this->fcmTokens()
            ->where('token', $token)
            ->update(['is_valid' => false]);
    }

    /**
     * Invalidate all valid FCM tokens for this notifiable entity.
     *
     * @return int Number of tokens invalidated
     */
    public function invalidateAllFcmTokens(): int
    {
        return $this->fcmTokens()
            ->valid()
            ->update(['is_valid' => false]);
    }

    /**
     * Get the FCM tokens for notification routing.
     *
     * This method is called by Laravel's notification system to determine
     * which tokens to send the notification to.
     *
     * @param mixed $notification The notification being routed
     * @return array<string>|null Array of tokens or null if none exist
     */
    public function routeNotificationForFcm($notification): ?array
    {
        $tokens = $this->getFcmTokens();

        return !empty($tokens) ? $tokens : null;
    }

    /**
     * Get the maximum number of tokens allowed per notifiable.
     *
     * @return int
     */
    protected function getMaxTokensPerNotifiable(): int
    {
        return Config::get('fcm.tokens.max_per_notifiable', 10);
    }
}

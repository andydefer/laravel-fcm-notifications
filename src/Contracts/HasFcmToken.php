<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Contracts;

use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for entities that can receive Firebase Cloud Messaging notifications.
 *
 * This interface defines the methods that any notifiable entity must implement
 * to support FCM notifications. It handles token management, including
 * registration, retrieval, and invalidation of FCM tokens.
 *
 * Implementing this interface allows your models to:
 * - Store and manage multiple FCM tokens per user/device
 * - Track token validity and last usage
 * - Handle token invalidation when devices are no longer active
 * - Route notifications to the appropriate FCM tokens
 *
 * @package Andydefer\FcmNotifications\Contracts
 */
interface HasFcmToken
{
    /**
     * Define the Eloquent relationship for FCM tokens.
     *
     * This method should return a MorphMany relationship pointing to the
     * FcmToken model. It allows the notifiable entity to have multiple
     * tokens associated with it.
     *
     * Example implementation:
     * <code>
     * public function fcmTokens(): MorphMany
     * {
     *     return $this->morphMany(FcmToken::class, 'tokenable');
     * }
     * </code>
     *
     * @return MorphMany The relationship instance
     */
    public function fcmTokens(): MorphMany;

    /**
     * Retrieve all valid FCM tokens associated with the notifiable.
     *
     * This method should return an array of token strings that are currently
     * valid and can be used to send notifications. Invalid or expired tokens
     * should be excluded from this list.
     *
     * @return array<string> Array of valid FCM token strings
     */
    public function getFcmTokens(): array;

    /**
     * Get the primary FCM token for this notifiable.
     *
     * The primary token typically represents the user's main device or the
     * most recently used token. This can be useful for scenarios where you
     * want to send notifications to a single device.
     *
     * @return string|null The primary token string, or null if no tokens exist
     */
    public function getPrimaryFcmToken(): ?string;

    /**
     * Check if the notifiable has any registered FCM tokens.
     *
     * This is a convenience method to quickly determine if there are any
     * valid tokens available before attempting to send notifications.
     *
     * @return bool True if at least one valid token exists, false otherwise
     */
    public function hasFcmTokens(): bool;

    /**
     * Register a new FCM token for this notifiable.
     *
     * This method handles the storage of a new device token, optionally marking
     * it as primary and attaching metadata. It should create a new FcmToken
     * record and associate it with the notifiable.
     *
     * @param string $token The FCM token string to register
     * @param bool $isPrimary Whether this token should be marked as primary
     * @param array<string, mixed> $metadata Additional metadata to store with the token
     *                                       (e.g., device model, platform, app version)
     * @return FcmToken The created token model instance
     */
    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken;

    /**
     * Invalidate a specific FCM token.
     *
     * This method should mark the given token as invalid, typically by setting
     * an `is_valid` flag to false or by removing it from the database. This is
     * useful when a token is reported as invalid by FCM or when a user logs out.
     *
     * @param string $token The FCM token to invalidate
     * @return bool True if the token was found and invalidated, false otherwise
     */
    public function invalidateFcmToken(string $token): bool;

    /**
     * Invalidate all FCM tokens associated with this notifiable.
     *
     * This is useful for scenarios like user logout from all devices, account
     * suspension, or security concerns. It should invalidate every token
     * belonging to the notifiable.
     *
     * @return int The number of tokens that were invalidated
     */
    public function invalidateAllFcmTokens(): int;

    /**
     * Get the FCM tokens for notification routing.
     *
     * This method is used by Laravel's notification system to determine which
     * tokens should receive the notification. It should return an array of
     * valid token strings.
     *
     * Example implementation:
     * <code>
     * public function routeNotificationForFcm($notification): ?array
     * {
     *     return $this->getFcmTokens();
     * }
     * </code>
     *
     * @param mixed $notification The notification instance being routed
     * @return array<string>|null Array of token strings or null if no tokens available
     */
    public function routeNotificationForFcm($notification): ?array;
}

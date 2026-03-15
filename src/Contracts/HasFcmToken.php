<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Contracts;

use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasFcmToken
{
    /**
     * Get the FCM tokens relationship.
     */
    public function fcmTokens(): MorphMany;

    /**
     * Get all valid FCM tokens.
     */
    public function getFcmTokens(): array;

    /**
     * Get the primary FCM token.
     */
    public function getPrimaryFcmToken(): ?string;

    /**
     * Check if the notifiable has any FCM tokens.
     */
    public function hasFcmTokens(): bool;

    /**
     * Register a new FCM token.
     */
    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken;

    /**
     * Invalidate an FCM token.
     */
    public function invalidateFcmToken(string $token): bool;

    /**
     * Invalidate all FCM tokens.
     */
    public function invalidateAllFcmTokens(): int;

    /**
     * Route notification for the FCM channel.
     */
    public function routeNotificationForFcm($notification): array|null;
}

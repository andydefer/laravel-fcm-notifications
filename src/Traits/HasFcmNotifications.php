<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Traits;

use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;

trait HasFcmNotifications
{
    /**
     * Boot the trait.
     */
    public static function bootHasFcmNotifications(): void
    {
        static::deleted(function ($model) {
            $model->fcmTokens()->delete();
        });
    }

    /**
     * Initialize the trait.
     */
    public function initializeHasFcmNotifications(): void
    {
        // Optional: Add any initialization logic
    }

    /**
     * Get the FCM tokens relationship.
     */
    public function fcmTokens(): MorphMany  // ✅ Type de retour ajouté
    {
        return $this->morphMany(FcmToken::class, 'tokenable');
    }

    /**
     * {@inheritdoc}
     */
    public function getFcmTokens(): array
    {
        return $this->fcmTokens()
            ->valid()
            ->orderBy('is_primary', 'desc')
            ->orderBy('last_used_at', 'desc')
            ->limit(Config::get('fcm.tokens.max_per_notifiable', 10))
            ->pluck('token')
            ->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryFcmToken(): ?string
    {
        return $this->fcmTokens()
            ->valid()
            ->primary()
            ->value('token')
            ?? $this->fcmTokens()
            ->valid()
            ->latest('last_used_at')
            ->value('token');
    }

    /**
     * {@inheritdoc}
     */
    public function hasFcmTokens(): bool
    {
        return $this->fcmTokens()->valid()->exists();
    }

    /**
     * {@inheritdoc}
     */
    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken {
        $maxTokens = Config::get('fcm.tokens.max_per_notifiable', 10);

        // Check token limit
        if ($this->fcmTokens()->valid()->count() >= $maxTokens) {
            // Remove oldest token
            $oldest = $this->fcmTokens()
                ->valid()
                ->orderBy('last_used_at', 'asc')
                ->first();

            if ($oldest) {
                $oldest->delete();
            }
        }

        // Handle primary token
        if ($isPrimary) {
            $this->fcmTokens()
                ->valid()
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        // Update or create token
        return $this->fcmTokens()->updateOrCreate(
            ['token' => $token],
            [
                'is_valid' => true,
                'is_primary' => $isPrimary,
                'metadata' => $metadata,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateFcmToken(string $token): bool
    {
        return (bool) $this->fcmTokens()
            ->where('token', $token)
            ->update(['is_valid' => false]);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidateAllFcmTokens(): int
    {
        return $this->fcmTokens()
            ->valid()
            ->update(['is_valid' => false]);
    }

    /**
     * {@inheritdoc}
     */
    public function routeNotificationForFcm($notification): ?array
    {
        $tokens = $this->getFcmTokens();

        return ! empty($tokens) ? $tokens : null;
    }
}

<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Test implementation of the HasFcmToken contract for testing purposes.
 *
 * This class provides a lightweight, in-memory implementation of the HasFcmToken
 * interface that doesn't require a database connection. It's used in unit tests
 * to verify token management functionality without the overhead of Eloquent models.
 *
 * @package Andydefer\FcmNotifications\Tests\Fixtures
 */
class TestNotifiable implements HasFcmToken
{
    /**
     * Currently active tokens.
     *
     * @var array<string>
     */
    private array $activeTokens = [];

    /**
     * Tokens that have been invalidated.
     *
     * @var array<string>
     */
    private array $invalidatedTokens = [];

    /**
     * Create a new test notifiable instance.
     *
     * @param array<string> $initialTokens Initial set of active tokens
     */
    public function __construct(array $initialTokens = [])
    {
        $this->activeTokens = $initialTokens;
    }

    /**
     * Get the FCM tokens relationship.
     *
     * Returns a mock MorphMany relationship that doesn't interact with the database.
     * This allows testing of token management without requiring actual database tables.
     *
     * @return MorphMany
     */
    public function fcmTokens(): MorphMany
    {
        return new class extends MorphMany {
            /**
             * Create a mock MorphMany relationship that bypasses database requirements.
             */
            public function __construct()
            {
                // Skip parent constructor to avoid database dependencies
            }

            /**
             * Get the results of the relationship.
             *
             * @return Collection<int, FcmToken> Empty collection
             */
            public function getResults()
            {
                return collect();
            }

            /**
             * Add constraints to the relationship query.
             *
             * @return void
             */
            public function addConstraints(): void
            {
                // No constraints needed for mock implementation
            }

            /**
             * Add eager constraints to the relationship query.
             *
             * @param array<int, mixed> $models
             * @return void
             */
            public function addEagerConstraints(array $models): void
            {
                // No eager constraints needed for mock implementation
            }

            /**
             * Initialize the relation on a set of models.
             *
             * @param array<int, mixed> $models
             * @param string $relation
             * @return array<int, mixed>
             */
            public function initRelation(array $models, $relation): array
            {
                return $models;
            }

            /**
             * Match the eagerly loaded results to their parents.
             *
             * @param array<int, mixed> $models
             * @param Collection<int, mixed> $results
             * @param string $relation
             * @return array<int, mixed>
             */
            public function match(array $models, $results, $relation): array
            {
                return $models;
            }

            /**
             * Get the relationship for eager loading.
             *
             * @param mixed $query
             * @param mixed $parentQuery
             * @param array<int, string>|string $columns
             * @return mixed
             */
            public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
            {
                return $query;
            }
        };
    }

    /**
     * Get all active FCM tokens.
     *
     * @return array<string> Array of active token strings
     */
    public function getFcmTokens(): array
    {
        return $this->activeTokens;
    }

    /**
     * Get the primary FCM token.
     *
     * In this test implementation, the first token is considered the primary one.
     *
     * @return string|null The primary token or null if no tokens exist
     */
    public function getPrimaryFcmToken(): ?string
    {
        return $this->activeTokens[0] ?? null;
    }

    /**
     * Check if any FCM tokens exist.
     *
     * @return bool True if at least one active token exists
     */
    public function hasFcmTokens(): bool
    {
        return !empty($this->activeTokens);
    }

    /**
     * Register a new FCM token.
     *
     * @param string $token The token to register
     * @param bool $isPrimary Whether this should be the primary token
     * @param array<string, mixed> $metadata Additional token metadata
     * @return FcmToken A token model instance (not persisted)
     */
    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken {
        $fcmToken = $this->createTokenModel(
            token: $token,
            isPrimary: $isPrimary,
            metadata: $metadata
        );

        if (!in_array($token, $this->activeTokens, true)) {
            $this->activeTokens[] = $token;
        }

        if ($isPrimary) {
            $this->promoteTokenToPrimary($token);
        }

        return $fcmToken;
    }

    /**
     * Invalidate a specific FCM token.
     *
     * @param string $token The token to invalidate
     * @return bool True if token was found and invalidated
     */
    public function invalidateFcmToken(string $token): bool
    {
        if (!in_array($token, $this->activeTokens, true)) {
            return false;
        }

        $this->invalidatedTokens[] = $token;
        $this->activeTokens = array_values(array_diff($this->activeTokens, [$token]));

        return true;
    }

    /**
     * Invalidate all FCM tokens.
     *
     * @return int Number of tokens invalidated
     */
    public function invalidateAllFcmTokens(): int
    {
        $invalidatedCount = count($this->activeTokens);

        $this->invalidatedTokens = array_merge($this->invalidatedTokens, $this->activeTokens);
        $this->activeTokens = [];

        return $invalidatedCount;
    }

    /**
     * Get the FCM tokens for notification routing.
     *
     * @param mixed $notification The notification being routed
     * @return array<string>|null Array of tokens or null if none exist
     */
    public function routeNotificationForFcm($notification): ?array
    {
        return $this->getFcmTokens();
    }

    /**
     * Get all tokens that have been invalidated during testing.
     *
     * @return array<string> Array of invalidated token strings
     */
    public function getInvalidatedTokens(): array
    {
        return $this->invalidatedTokens;
    }

    /**
     * Create a new FCM token model instance.
     *
     * @param string $token The token string
     * @param bool $isPrimary Whether this is a primary token
     * @param array<string, mixed> $metadata Token metadata
     * @return FcmToken
     */
    private function createTokenModel(string $token, bool $isPrimary, array $metadata): FcmToken
    {
        $tokenModel = new FcmToken();
        $tokenModel->token = $token;
        $tokenModel->is_primary = $isPrimary;
        $tokenModel->metadata = $metadata;
        $tokenModel->is_valid = true;
        $tokenModel->last_used_at = now();

        return $tokenModel;
    }

    /**
     * Promote a token to primary status.
     *
     * In this test implementation, we don't need to demote other tokens
     * as primary status is determined by order in the active tokens array.
     *
     * @param string $token The token to promote
     * @return void
     */
    private function promoteTokenToPrimary(string $token): void
    {
        // Move token to the front of the array
        $this->activeTokens = array_values(array_diff($this->activeTokens, [$token]));
        array_unshift($this->activeTokens, $token);
    }
}

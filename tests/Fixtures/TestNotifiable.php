<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TestNotifiable implements HasFcmToken
{
    protected array $tokens = [];
    protected array $invalidatedTokens = [];

    public function __construct(array $tokens = [])
    {
        $this->tokens = $tokens;
    }

    public function fcmTokens(): MorphMany  // ✅ Type de retour ajouté
    {
        return new class extends MorphMany {
            public function __construct()
            {
                // Skip parent constructor
            }

            public function getResults()
            {
                return collect();
            }

            public function addConstraints(): void
            {
                // No constraints needed
            }

            public function addEagerConstraints(array $models): void
            {
                // No eager constraints needed
            }

            public function initRelation(array $models, $relation): array
            {
                return $models;
            }

            public function match(array $models, $results, $relation): array
            {
                return $models;
            }

            public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
            {
                return $query;
            }
        };
    }

    public function getFcmTokens(): array
    {
        return $this->tokens;
    }

    public function getPrimaryFcmToken(): ?string
    {
        return $this->tokens[0] ?? null;
    }

    public function hasFcmTokens(): bool
    {
        return !empty($this->tokens);
    }

    public function registerFcmToken(
        string $token,
        bool $isPrimary = false,
        array $metadata = []
    ): FcmToken {
        $fcmToken = new FcmToken();
        $fcmToken->token = $token;
        $fcmToken->is_primary = $isPrimary;
        $fcmToken->metadata = $metadata;
        $fcmToken->is_valid = true;
        $fcmToken->last_used_at = now();

        if (!in_array($token, $this->tokens)) {
            $this->tokens[] = $token;
        }

        return $fcmToken;
    }

    public function invalidateFcmToken(string $token): bool
    {
        $this->invalidatedTokens[] = $token;
        $this->tokens = array_values(array_diff($this->tokens, [$token]));
        return true;
    }

    public function invalidateAllFcmTokens(): int
    {
        $count = count($this->tokens);
        $this->invalidatedTokens = array_merge($this->invalidatedTokens, $this->tokens);
        $this->tokens = [];
        return $count;
    }

    public function routeNotificationForFcm($notification): ?array
    {
        return $this->getFcmTokens();
    }

    public function getInvalidatedTokens(): array
    {
        return $this->invalidatedTokens;
    }
}

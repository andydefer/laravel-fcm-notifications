<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Models;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Unit tests for the FcmToken model.
 *
 * This test suite verifies the core functionality of the FcmToken model including:
 * - Token creation and attribute handling
 * - Model scopes for filtering tokens
 * - Relationships with notifiable entities
 * - Token invalidation and status management
 *
 * @package Andydefer\FcmNotifications\Tests\Unit\Models
 */
class FcmTokenTest extends TestCase
{
    /**
     * Test that a token can be created with all attributes.
     *
     * Verifies that:
     * - Token is properly instantiated as an FcmToken model
     * - All provided attributes are correctly saved
     * - JSON metadata is properly cast to array
     *
     * @return void
     */
    public function test_can_create_token_with_all_attributes(): void
    {
        // Arrange: Create a test user and define token attributes
        $user = $this->createTestUser();
        $tokenValue = 'test-token-123';
        $deviceMetadata = ['device' => 'iPhone 15', 'os' => 'iOS 17'];

        // Act: Create a new token with all attributes
        $token = $user->fcmTokens()->create([
            'token' => $tokenValue,
            'is_valid' => true,
            'metadata' => $deviceMetadata,
            'last_used_at' => now(),
        ]);

        // Assert: Token is properly created with all attributes
        $this->assertInstanceOf(FcmToken::class, $token);
        $this->assertEquals($tokenValue, $token->token);
        $this->assertTrue($token->is_valid);
        $this->assertEquals($deviceMetadata, $token->metadata);
    }

    /**
     * Test that last_used_at is automatically set when not provided.
     *
     * Verifies the model's default value behavior for the last_used_at timestamp.
     *
     * @return void
     */
    public function test_automatically_sets_last_used_timestamp_on_creation(): void
    {
        // Arrange: Create a test user
        $user = $this->createTestUser();

        // Act: Create a token without specifying last_used_at
        $token = $user->fcmTokens()->create([
            'token' => 'test-token-123',
            'is_valid' => true,
        ]);

        // Assert: last_used_at is automatically populated
        $this->assertNotNull($token->last_used_at);
        $this->assertInstanceOf(Carbon::class, $token->last_used_at);
        $this->assertTrue($token->last_used_at->isToday());
    }

    /**
     * Test the valid() scope filters only valid tokens.
     *
     * Verifies that the scope correctly includes valid tokens
     * and excludes invalid ones.
     *
     * @return void
     */
    public function test_valid_scope_returns_only_valid_tokens(): void
    {
        // Arrange: Create tokens with mixed validity status
        $user = $this->createTestUser();

        /** @var Collection<int, FcmToken> $tokens */
        $tokens = $user->fcmTokens()->createMany([
            ['token' => 'valid-token-1', 'is_valid' => true],
            ['token' => 'valid-token-2', 'is_valid' => true],
            ['token' => 'invalid-token', 'is_valid' => false],
        ]);

        // Act: Apply valid scope to the query
        $validTokens = $user->fcmTokens()->valid()->get();

        // Assert: Only valid tokens are returned
        $this->assertCount(2, $validTokens);
        $this->assertEquals('valid-token-1', $validTokens[0]->token);
        $this->assertEquals('valid-token-2', $validTokens[1]->token);
        $this->assertNotContains('invalid-token', $validTokens->pluck('token'));
    }

    /**
     * Test the notUsedSince() scope filters tokens by last usage date.
     *
     * Verifies that the scope correctly identifies tokens that haven't
     * been used since a specified cutoff date.
     *
     * @return void
     */
    public function test_not_used_since_scope_filters_by_inactivity(): void
    {
        // Arrange: Create tokens with different last_used_at dates
        $user = $this->createTestUser();
        $cutoffDate = now()->subDays(30);

        /** @var Collection<int, FcmToken> $tokens */
        $tokens = $user->fcmTokens()->createMany([
            ['token' => 'abandoned-device', 'last_used_at' => now()->subDays(40)],
            ['token' => 'occasional-device', 'last_used_at' => now()->subDays(10)],
            ['token' => 'active-device', 'last_used_at' => now()],
        ]);

        // Act: Find tokens not used since cutoff date
        $inactiveTokens = $user->fcmTokens()->notUsedSince($cutoffDate)->get();

        // Assert: Only tokens older than cutoff are returned
        $this->assertCount(1, $inactiveTokens);
        $this->assertEquals('abandoned-device', $inactiveTokens[0]->token);
        $this->assertTrue($inactiveTokens[0]->last_used_at->lt($cutoffDate));
    }

    /**
     * Test the polymorphic relationship with the tokenable model.
     *
     * Verifies that the token can correctly access its parent model
     * through the tokenable relationship.
     *
     * @return void
     */
    public function test_tokenable_relationship_returns_owning_model(): void
    {
        // Arrange: Create a user with an associated token
        $user = $this->createTestUser();

        /** @var FcmToken $token */
        $token = $user->fcmTokens()->create([
            'token' => 'test-device-token'
        ]);

        // Act: Access the tokenable relationship
        $tokenable = $token->tokenable;

        // Assert: Relationship returns the correct user
        $this->assertInstanceOf(TestUser::class, $tokenable);
        $this->assertEquals($user->id, $tokenable->id);
        $this->assertEquals($user->email, $tokenable->email);
    }

    /**
     * Test that a token can be marked as invalid.
     *
     * Verifies that the is_valid flag can be toggled to false,
     * effectively invalidating the token for future notifications.
     *
     * @return void
     */
    public function test_can_mark_token_as_invalid(): void
    {
        // Arrange: Create a valid token
        $user = $this->createTestUser();

        /** @var FcmToken $token */
        $token = $user->fcmTokens()->create([
            'token' => 'valid-device-token',
            'is_valid' => true,
        ]);

        // Act: Invalidate the token
        $token->update(['is_valid' => false]);
        $refreshedToken = $token->fresh();

        // Assert: Token is now marked as invalid
        $this->assertFalse($refreshedToken->is_valid);
        $this->assertNotNull($refreshedToken->updated_at);
    }
}

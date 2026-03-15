<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Traits;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for the HasFcmNotifications trait.
 *
 * This test suite verifies all functionality provided by the HasFcmNotifications trait
 * including token registration, validation, invalidation, primary token management,
 * and integration with Laravel's notification system.
 *
 * @package Andydefer\FcmNotifications\Tests\Unit\Traits
 */
class HasFcmNotificationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a new FCM token can be registered with metadata.
     *
     * @return void
     */
    public function test_can_register_fcm_token_with_metadata(): void
    {
        // Arrange: Create a user and prepare token data
        $user = $this->createTestUser();
        $deviceToken = 'device-token-123';
        $metadata = ['device' => 'iPhone 15', 'os' => 'iOS 17'];

        // Act: Register a new token with metadata
        $fcmToken = $user->registerFcmToken(
            token: $deviceToken,
            metadata: $metadata
        );

        // Assert: Token is correctly created with all attributes
        $this->assertInstanceOf(FcmToken::class, $fcmToken);
        $this->assertEquals($deviceToken, $fcmToken->token);
        $this->assertEquals($metadata, $fcmToken->metadata);
        $this->assertTrue($fcmToken->is_valid);
        $this->assertNotNull($fcmToken->last_used_at);
    }

    /**
     * Test that token registration respects the maximum tokens per user limit.
     *
     * When the limit is reached, the oldest token (by last_used_at) should be
     * automatically removed to make room for the new token.
     *
     * @return void
     */
    public function test_enforces_token_limit_with_lru_eviction(): void
    {
        // Arrange: Configure a 3-token limit and create a user
        config(['fcm.tokens.max_per_notifiable' => 3]);
        $user = $this->createTestUser();

        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Ensure tokens have different timestamps
        sleep(1);

        // Act: Register a fourth token (should evict the oldest)
        $user->registerFcmToken('token-4');

        // Assert: Only 3 tokens remain, oldest (token-1) is gone
        $activeTokens = $user->getFcmTokens();
        $this->assertCount(3, $activeTokens);
        $this->assertNotContains('token-1', $activeTokens);
        $this->assertContains('token-2', $activeTokens);
        $this->assertContains('token-3', $activeTokens);
        $this->assertContains('token-4', $activeTokens);
    }

    /**
     * Test that the most recent token is automatically considered primary.
     *
     * @return void
     */
    public function test_most_recent_token_is_primary(): void
    {
        // Arrange: Create user with multiple tokens at different times
        $user = $this->createTestUser();

        $user->registerFcmToken('token-1');
        sleep(1);
        $user->registerFcmToken('token-2');
        sleep(1);
        $user->registerFcmToken('token-3');

        // Assert: The most recent token (token-3) is primary
        $this->assertEquals('token-3', $user->getPrimaryFcmToken());
    }

    /**
     * Test that the isPrimary parameter in registerFcmToken is ignored
     * (maintained for backward compatibility).
     *
     * @return void
     */
    public function test_is_primary_parameter_is_ignored_for_backward_compatibility(): void
    {
        // Arrange: Create user
        $user = $this->createTestUser();

        // Act: Register tokens with isPrimary=true for older token
        $user->registerFcmToken('token-1', isPrimary: true);
        sleep(1);
        $user->registerFcmToken('token-2', isPrimary: false);

        // Assert: Most recent token is primary regardless of isPrimary parameter
        $this->assertEquals('token-2', $user->getPrimaryFcmToken());
    }

    /**
     * Test that registering an existing token updates its metadata.
     *
     * @return void
     */
    public function test_updates_existing_token_metadata_when_re_registered(): void
    {
        // Arrange: Register a token with initial metadata
        $user = $this->createTestUser();
        $user->registerFcmToken(
            token: 'token-1',
            metadata: ['device' => 'iPhone']
        );

        // Act: Register the same token with updated metadata
        $user->registerFcmToken(
            token: 'token-1',
            metadata: ['device' => 'iPhone 15 Pro']
        );

        // Assert: Token count remains 1, but metadata is updated
        $tokens = $user->fcmTokens()->get();
        $this->assertCount(1, $tokens);
        $this->assertEquals(['device' => 'iPhone 15 Pro'], $tokens[0]->metadata);
    }

    /**
     * Test retrieving all active FCM tokens as an array.
     *
     * @return void
     */
    public function test_returns_all_active_tokens_as_array(): void
    {
        // Arrange: Register multiple tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Act: Retrieve all tokens
        $activeTokens = $user->getFcmTokens();

        // Assert: All tokens are returned in correct format
        $this->assertIsArray($activeTokens);
        $this->assertCount(3, $activeTokens);
        $this->assertEquals(['token-1', 'token-2', 'token-3'], $activeTokens);
    }

    /**
     * Test retrieving the primary FCM token (most recent).
     *
     * @return void
     */
    public function test_returns_most_recent_token_as_primary(): void
    {
        // Arrange: Register tokens with different timestamps
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        sleep(1);
        $user->registerFcmToken('token-2');
        sleep(1);
        $user->registerFcmToken('token-3');

        // Act: Get primary token
        $primaryToken = $user->getPrimaryFcmToken();

        // Assert: Most recent token is returned
        $this->assertEquals('token-3', $primaryToken);
    }

    /**
     * Test that hasFcmTokens returns true when tokens exist.
     *
     * @return void
     */
    public function test_detects_presence_of_tokens(): void
    {
        // Arrange: Create user with a token
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');

        // Assert: hasFcmTokens returns true
        $this->assertTrue($user->hasFcmTokens());
    }

    /**
     * Test that hasFcmTokens returns false when no tokens exist.
     *
     * @return void
     */
    public function test_detects_absence_of_tokens(): void
    {
        // Arrange: Create user without tokens
        $user = $this->createTestUser();

        // Assert: hasFcmTokens returns false
        $this->assertFalse($user->hasFcmTokens());
    }

    /**
     * Test that a specific token can be invalidated.
     *
     * @return void
     */
    public function test_can_invalidate_specific_token(): void
    {
        // Arrange: Register multiple tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');

        // Act: Invalidate token-1
        $invalidationResult = $user->invalidateFcmToken('token-1');

        // Assert: Token is removed from active list and marked invalid
        $this->assertTrue($invalidationResult);
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertNotContains('token-1', $user->getFcmTokens());
        $this->assertContains('token-2', $user->getFcmTokens());

        $invalidToken = $user->fcmTokens()->where('token', 'token-1')->first();
        $this->assertFalse($invalidToken->is_valid);
    }

    /**
     * Test that all tokens can be invalidated at once.
     *
     * @return void
     */
    public function test_can_invalidate_all_tokens(): void
    {
        // Arrange: Register multiple tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Act: Invalidate all tokens
        $invalidatedCount = $user->invalidateAllFcmTokens();

        // Assert: All tokens are invalidated
        $this->assertEquals(3, $invalidatedCount);
        $this->assertCount(0, $user->getFcmTokens());

        $validTokensCount = $user->fcmTokens()->valid()->count();
        $this->assertEquals(0, $validTokensCount);
    }

    /**
     * Test that routeNotificationForFcm returns all active tokens.
     *
     * This method is used by Laravel's notification system to determine
     * where to send FCM notifications.
     *
     * @return void
     */
    public function test_provides_tokens_for_notification_routing(): void
    {
        // Arrange: Register multiple tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');

        // Act: Get notification route
        $route = $user->routeNotificationForFcm(notification: null);

        // Assert: All active tokens are returned
        $this->assertIsArray($route);
        $this->assertCount(2, $route);
        $this->assertEquals(['token-1', 'token-2'], $route);
    }

    /**
     * Test that tokens are cascade deleted when the owning user is deleted.
     *
     * @return void
     */
    public function test_tokens_are_cascade_deleted_with_user(): void
    {
        // Arrange: Create user with tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $userId = $user->id;

        // Act: Delete the user
        $user->delete();

        // Assert: All associated tokens are deleted
        $remainingTokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userId)
            ->count();

        $this->assertEquals(0, $remainingTokens);
    }

    /**
     * Test that primary token updates to the next most recent when current is invalidated.
     *
     * @return void
     */
    public function test_primary_token_updates_to_next_most_recent_when_invalidated(): void
    {
        // Arrange: Create user with tokens at different times
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1'); // oldest
        sleep(1);
        $user->registerFcmToken('token-2'); // middle
        sleep(1);
        $user->registerFcmToken('token-3'); // newest

        // Initially, token-3 should be primary
        $this->assertEquals('token-3', $user->getPrimaryFcmToken());

        // Act: Invalidate the current primary token
        $user->invalidateFcmToken('token-3');

        // Assert: The next most recent token (token-2) becomes primary
        $this->assertEquals('token-2', $user->getPrimaryFcmToken());
    }

    /**
     * Test that primary token returns null when all tokens are invalid.
     *
     * @return void
     */
    public function test_primary_token_is_null_when_all_tokens_invalid(): void
    {
        // Arrange: Create user with tokens, then invalidate all
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->invalidateAllFcmTokens();

        // Assert: getPrimaryFcmToken returns null
        $this->assertNull($user->getPrimaryFcmToken());
    }
}

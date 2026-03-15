<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Feature;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Feature tests for the complete FCM notification system.
 *
 * This test suite verifies the integration between all components of the package:
 * - Token registration and management
 * - Multiple users and tokens handling
 * - Token limits enforcement
 * - Cascade deletions
 *
 * @package Andydefer\FcmNotifications\Tests\Feature
 */
class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the complete lifecycle of FCM tokens from registration to deletion.
     *
     * This test verifies:
     * 1. Token registration with metadata
     * 2. Primary token management (based on most recent)
     * 3. Token usage tracking
     * 4. Token invalidation
     * 5. Primary token reassignment
     * 6. Cascade deletion when user is removed
     *
     * @return void
     */
    public function test_complete_token_lifecycle(): void
    {
        // Arrange: Create a user and register multiple tokens with metadata
        $user = $this->createTestUser();

        $user->registerFcmToken(
            token: 'token-1',
            metadata: ['device' => 'iPhone']
        );

        // Wait to ensure different timestamps
        sleep(1);

        $user->registerFcmToken(
            token: 'token-2',
            metadata: ['device' => 'iPad']
        );

        // Assert: Initial token registration is correct
        $this->assertCount(2, $user->getFcmTokens());
        // The most recent token (token-2) should be primary
        $this->assertEquals('token-2', $user->getPrimaryFcmToken());

        // Act: Simulate token usage by updating last_used_at for token-1
        $token = $user->fcmTokens()->where('token', 'token-1')->first();
        $token->update(['last_used_at' => now()]);

        // Now token-1 becomes primary (most recent)
        $this->assertEquals('token-1', $user->getPrimaryFcmToken());

        // Act: Invalidate token-1
        $user->invalidateFcmToken('token-1');

        // Assert: Only token-2 remains active and becomes primary
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertEquals(['token-2'], $user->getFcmTokens());
        $this->assertEquals('token-2', $user->getPrimaryFcmToken());

        // Act: Register a new token
        sleep(1);
        $user->registerFcmToken(token: 'token-3');

        // Assert: New token becomes primary (most recent)
        $this->assertCount(2, $user->getFcmTokens());
        $this->assertEquals('token-3', $user->getPrimaryFcmToken());

        // Act: Delete the user
        $userId = $user->id;
        $user->delete();

        // Assert: All associated tokens are cascade deleted
        $remainingTokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userId)
            ->count();

        $this->assertEquals(0, $remainingTokens);
    }

    /**
     * Test that multiple users can manage their tokens independently.
     *
     * This test verifies:
     * - Token isolation between different users
     * - Correct token counts per user
     * - Global token count accuracy
     *
     * @return void
     */
    public function test_token_isolation_between_multiple_users(): void
    {
        // Arrange: Create three users with different token configurations
        $userWithTwoTokens = $this->createTestUser([
            'name' => 'User With Two Tokens',
            'email' => 'two.tokens@example.com'
        ]);
        $userWithTwoTokens->registerFcmToken('user1-token1');
        $userWithTwoTokens->registerFcmToken('user1-token2');

        $userWithOneToken = $this->createTestUser([
            'name' => 'User With One Token',
            'email' => 'one.token@example.com'
        ]);
        $userWithOneToken->registerFcmToken('user2-token1');

        $userWithNoTokens = $this->createTestUser([
            'name' => 'User With No Tokens',
            'email' => 'no.tokens@example.com'
        ]);

        // Assert: Each user has the expected number of tokens
        $this->assertCount(2, $userWithTwoTokens->getFcmTokens());
        $this->assertCount(1, $userWithOneToken->getFcmTokens());
        $this->assertCount(0, $userWithNoTokens->getFcmTokens());

        $this->assertTrue($userWithTwoTokens->hasFcmTokens());
        $this->assertTrue($userWithOneToken->hasFcmTokens());
        $this->assertFalse($userWithNoTokens->hasFcmTokens());

        // Assert: Total tokens in database matches sum of individual counts
        $totalTokens = FcmToken::count();
        $this->assertEquals(3, $totalTokens);

        // Assert: Tokens are correctly scoped to their respective users
        $userOneTokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userWithTwoTokens->id)
            ->count();
        $this->assertEquals(2, $userOneTokens);

        $userTwoTokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userWithOneToken->id)
            ->count();
        $this->assertEquals(1, $userTwoTokens);
    }

    /**
     * Test that token limits per user are enforced with LRU behavior.
     *
     * This test verifies:
     * - Configuration-based token limits are respected
     * - Oldest token is automatically removed when limit is exceeded
     * - New token is successfully added within limits
     *
     * @return void
     */
    public function test_enforces_token_limit_with_lru_eviction(): void
    {
        // Arrange: Configure a limit of 3 tokens per user
        config(['fcm.tokens.max_per_notifiable' => 3]);

        $user = $this->createTestUser();

        // Register tokens up to the limit
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Ensure tokens have different timestamps
        sleep(1);

        // Assert: User has exactly the limit number of tokens
        $this->assertCount(3, $user->getFcmTokens());

        // Act: Register one more token (should replace the oldest)
        $user->registerFcmToken('token-4');

        // Assert: Token count remains at limit, oldest token (token-1) is removed
        $activeTokens = $user->getFcmTokens();
        $this->assertCount(3, $activeTokens);
        $this->assertContains('token-4', $activeTokens);
        $this->assertNotContains('token-1', $activeTokens);
        $this->assertContains('token-2', $activeTokens);
        $this->assertContains('token-3', $activeTokens);
    }
}

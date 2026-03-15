<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Traits;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;

class HasFcmNotificationsTest extends TestCase
{
    /**
     * Test registering a new FCM token.
     */
    public function test_can_register_fcm_token(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $token = 'device-token-123';
        $metadata = ['device' => 'iPhone 15', 'os' => 'iOS 17'];

        // Act
        $fcmToken = $user->registerFcmToken($token, true, $metadata);

        // Assert
        $this->assertInstanceOf(FcmToken::class, $fcmToken);
        $this->assertEquals($token, $fcmToken->token);
        $this->assertTrue($fcmToken->is_primary);
        $this->assertEquals($metadata, $fcmToken->metadata);
        $this->assertTrue($fcmToken->is_valid);
    }

    /**
     * Test that registering a token respects max limit.
     */
    public function test_register_respects_max_tokens_limit(): void
    {
        // Arrange
        config(['fcm.tokens.max_per_notifiable' => 3]);
        $user = $this->createTestUser();

        // Register 3 tokens
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Add delay to ensure different last_used_at
        sleep(1);

        // Act - This should remove the oldest token (token-1)
        $user->registerFcmToken('token-4');

        // Assert
        $tokens = $user->getFcmTokens();
        $this->assertCount(3, $tokens);
        $this->assertNotContains('token-1', $tokens);
        $this->assertContains('token-2', $tokens);
        $this->assertContains('token-3', $tokens);
        $this->assertContains('token-4', $tokens);
    }

    /**
     * Test that setting a token as primary unsets others.
     */
    public function test_setting_primary_token_unsets_others(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1', isPrimary: false);
        $user->registerFcmToken('token-2', isPrimary: false);
        $user->registerFcmToken('token-3', isPrimary: false);

        // Act
        $user->registerFcmToken('token-2', isPrimary: true);

        // Assert
        $primaryTokens = $user->fcmTokens()->primary()->get();
        $this->assertCount(1, $primaryTokens);
        $this->assertEquals('token-2', $primaryTokens[0]->token);
    }

    /**
     * Test updating an existing token.
     */
    public function test_updating_existing_token(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1', metadata: ['device' => 'iPhone']);

        // Act - Register same token with new metadata
        $user->registerFcmToken('token-1', isPrimary: true, metadata: ['device' => 'iPhone 15 Pro']);

        // Assert
        $tokens = $user->fcmTokens()->get();
        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens[0]->is_primary);
        $this->assertEquals(['device' => 'iPhone 15 Pro'], $tokens[0]->metadata);
    }

    /**
     * Test getting FCM tokens.
     */
    public function test_get_fcm_tokens(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Act
        $tokens = $user->getFcmTokens();

        // Assert
        $this->assertIsArray($tokens);
        $this->assertCount(3, $tokens);
        $this->assertEquals(['token-1', 'token-2', 'token-3'], $tokens);
    }

    /**
     * Test getting primary FCM token.
     */
    public function test_get_primary_fcm_token(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2', isPrimary: true);
        $user->registerFcmToken('token-3');

        // Act
        $primaryToken = $user->getPrimaryFcmToken();

        // Assert
        $this->assertEquals('token-2', $primaryToken);
    }

    /**
     * Test hasFcmTokens returns true when tokens exist.
     */
    public function test_has_fcm_tokens_returns_true_when_tokens_exist(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');

        // Act & Assert
        $this->assertTrue($user->hasFcmTokens());
    }

    /**
     * Test hasFcmTokens returns false when no tokens exist.
     */
    public function test_has_fcm_tokens_returns_false_when_no_tokens(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act & Assert
        $this->assertFalse($user->hasFcmTokens());
    }

    /**
     * Test invalidating a token.
     */
    public function test_invalidate_fcm_token(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');

        // Act
        $result = $user->invalidateFcmToken('token-1');

        // Assert
        $this->assertTrue($result);
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertNotContains('token-1', $user->getFcmTokens());
        $this->assertContains('token-2', $user->getFcmTokens());

        // Verify token is marked invalid
        $token = $user->fcmTokens()->where('token', 'token-1')->first();
        $this->assertFalse($token->is_valid);
    }

    /**
     * Test invalidating all tokens.
     */
    public function test_invalidate_all_fcm_tokens(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        // Act
        $count = $user->invalidateAllFcmTokens();

        // Assert
        $this->assertEquals(3, $count);
        $this->assertCount(0, $user->getFcmTokens());

        // Verify all tokens are marked invalid
        $validTokens = $user->fcmTokens()->valid()->count();
        $this->assertEquals(0, $validTokens);
    }

    /**
     * Test routeNotificationForFcm returns tokens.
     */
    public function test_route_notification_for_fcm_returns_tokens(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');

        // Act
        $route = $user->routeNotificationForFcm(null);

        // Assert
        $this->assertIsArray($route);
        $this->assertCount(2, $route);
        $this->assertEquals(['token-1', 'token-2'], $route);
    }

    /**
     * Test tokens are deleted when user is deleted.
     */
    public function test_tokens_are_deleted_when_user_is_deleted(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');

        // Act
        $userId = $user->id;
        $user->delete();

        // Assert
        $tokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userId)
            ->count();
        $this->assertEquals(0, $tokens);
    }
}

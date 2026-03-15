<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Feature;

use Andydefer\FcmNotifications\Tests\Fixtures\TestFcmNotification;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

class NotificationFlowTest extends TestCase
{
    /**
     * Test complete notification flow with FCM.
     */
    public function test_complete_notification_flow(): void
    {
        // Arrange
        Notification::fake();

        $user = $this->createTestUser();
        $user->registerFcmToken('device-token-123', isPrimary: true);

        $notification = new TestFcmNotification(
            title: 'Welcome!',
            body: 'Thanks for joining our app',
            data: ['user_id' => (string) $user->id]
        );

        // Act
        $user->notify($notification);

        // Assert
        Notification::assertSentTo($user, TestFcmNotification::class, function ($sent, $channels) {
            return in_array('fcm', $channels);
        });
    }

    /**
     * Test notification with multiple tokens.
     */
    public function test_notification_with_multiple_tokens(): void
    {
        // Arrange
        Notification::fake();

        $user = $this->createTestUser();
        $user->registerFcmToken('device-1');
        $user->registerFcmToken('device-2');
        $user->registerFcmToken('device-3');

        $notification = new TestFcmNotification('Multi-device', 'Message');

        // Act
        $user->notify($notification);

        // Assert
        Notification::assertSentTo($user, TestFcmNotification::class);
        $this->assertCount(3, $user->getFcmTokens());
    }

    /**
     * Test that invalid tokens are automatically cleaned up.
     */
    public function test_invalid_tokens_are_cleaned_up(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->registerFcmToken('valid-token');
        $user->registerFcmToken('invalid-token');

        // Act - Invalidate the token
        $user->invalidateFcmToken('invalid-token');

        // Assert
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertEquals(['valid-token'], $user->getFcmTokens());

        $invalidToken = $user->fcmTokens()->where('token', 'invalid-token')->first();
        $this->assertFalse($invalidToken->is_valid);
    }
}

<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Feature;

use Andydefer\FcmNotifications\Tests\Fixtures\TestFcmNotification;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Feature tests for the FCM notification delivery flow.
 *
 * @package Andydefer\FcmNotifications\Tests\Feature
 */
class NotificationFlowTest extends TestCase
{
    /**
     * Test that a notification with FCM data is properly sent to a user with a token.
     */
    public function test_sends_fcm_notification_to_user_with_token(): void
    {
        // Arrange: Fake notifications and create a user with a token
        Notification::fake();

        $user = $this->createTestUser();
        $user->registerFcmToken('device-token-123', isPrimary: true);

        $notification = new TestFcmNotification(
            title: 'Welcome!',
            body: 'Thanks for joining our app',
            data: ['user_id' => (string) $user->id]
        );

        // Act: Send the notification
        $user->notify($notification);

        // Assert: The notification was sent
        Notification::assertSentTo($user, TestFcmNotification::class);
    }

    /**
     * Test that notifications are sent to users with multiple tokens.
     */
    public function test_sends_notification_to_user_with_multiple_tokens(): void
    {
        // Arrange: Fake notifications and create a user with multiple tokens
        Notification::fake();

        $user = $this->createTestUser();
        $user->registerFcmToken('mobile-device-android');
        $user->registerFcmToken('mobile-device-ios');
        $user->registerFcmToken('tablet-device');

        $notification = new TestFcmNotification(
            title: 'Multi-device notification',
            body: 'This message appears on all your devices'
        );

        // Act: Send the notification
        $user->notify($notification);

        // Assert: The notification was sent and tokens remain
        Notification::assertSentTo($user, TestFcmNotification::class);
        $this->assertCount(3, $user->getFcmTokens());
    }

    /**
     * Test that invalid tokens are properly handled.
     */
    public function test_marks_tokens_as_invalid_when_they_fail(): void
    {
        // Arrange: Create a user with tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('valid-device-token');
        $user->registerFcmToken('token-that-will-be-invalidated');

        // Act: Invalidate one token
        $user->invalidateFcmToken('token-that-will-be-invalidated');

        // Assert: Only valid token remains in active list
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertEquals(['valid-device-token'], $user->getFcmTokens());

        // Assert: Invalid token is marked as invalid in database
        $invalidToken = $user->fcmTokens()
            ->where('token', 'token-that-will-be-invalidated')
            ->first();

        $this->assertFalse($invalidToken->is_valid);
    }

    /**
     * Test that notifications can be sent to anonymous notifiables.
     */
    public function test_sends_notification_to_anonymous_notifiable(): void
    {
        // Arrange: Fake notifications and create anonymous notifiable
        Notification::fake();

        $anonymous = (new AnonymousNotifiable)
            ->route('fcm', 'anonymous-device-token');

        $notification = new TestFcmNotification(
            title: 'Anonymous notification',
            body: 'Hello from the application'
        );

        // Act: Send notification
        $anonymous->notify($notification);

        // Assert: Notification was sent
        Notification::assertSentTo($anonymous, TestFcmNotification::class);
    }

    /**
     * Test that notifications are sent even without FCM tokens (other channels).
     */
    public function test_sends_notification_to_users_without_tokens(): void
    {
        // Arrange: Fake notifications and create a user with no tokens
        Notification::fake();

        $user = $this->createTestUser(); // No tokens

        $notification = new TestFcmNotification(
            title: 'Test notification',
            body: 'This should be sent via other channels'
        );

        // Act: Send the notification
        $user->notify($notification);

        // Assert: The notification was still sent (via other channels)
        Notification::assertSentTo($user, TestFcmNotification::class);
    }

    /**
     * Test that notifications are sent even with invalid tokens (other channels).
     */
    public function test_sends_notification_to_users_with_only_invalid_tokens(): void
    {
        // Arrange: Fake notifications and create a user with only invalid tokens
        Notification::fake();

        $user = $this->createTestUser();

        // Register tokens and invalidate them
        $user->registerFcmToken('invalid-token-1');
        $user->registerFcmToken('invalid-token-2');
        $user->invalidateAllFcmTokens();

        $notification = new TestFcmNotification(
            title: 'Test notification',
            body: 'This should be sent via other channels'
        );

        // Act: Send the notification
        $user->notify($notification);

        // Assert: The notification was still sent (via other channels)
        Notification::assertSentTo($user, TestFcmNotification::class);
    }
}

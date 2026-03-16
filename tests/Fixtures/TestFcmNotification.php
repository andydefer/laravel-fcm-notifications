<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Illuminate\Notifications\Notification;

/**
 * Test notification fixture for FCM testing.
 *
 * This notification class is used throughout the test suite to verify
 * FCM notification functionality without relying on application-specific
 * notification classes. It provides configurable title, body, and data
 * payload for comprehensive testing scenarios.
 *
 * @package Andydefer\FcmNotifications\Tests\Fixtures
 */
class TestFcmNotification extends Notification implements ShouldFcm
{
    /**
     * Create a new test notification instance.
     *
     * @param string $title The notification title to display
     * @param string $body The notification body content
     * @param array<string, mixed> $data Additional custom data payload
     */
    public function __construct(
        protected string $title = 'Test Notification Title',
        protected string $body = 'This is a test notification body content',
        protected array $data = []
    ) {}

    /**
     * Define the delivery channels for the notification.
     *
     * @param mixed $notifiable The notifiable entity
     * @return array<int, string> List of channel names
     */
    public function via($notifiable): array
    {
        return ['fcm'];
    }

    /**
     * Convert the notification to an FCM message data structure.
     *
     * @param mixed $notifiable The notifiable entity
     * @return FcmMessageData The FCM message data with configured title, body, and data
     */
    public function toFcm($notifiable): FcmMessageData
    {
        return FcmMessageData::info(
            title: $this->title,
            body: $this->body,
        );
    }
}

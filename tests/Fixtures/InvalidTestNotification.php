<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Illuminate\Notifications\Notification;

/**
 * A test notification that intentionally does NOT implement ShouldFcm.
 *
 * This fixture is used for testing error handling and validation scenarios
 * where a notification is configured to use the FCM channel but does not
 * implement the required ShouldFcm interface. It helps verify that the
 * FCM channel properly validates notifications before attempting to send.
 *
 * The absence of the toFcm() method (required by ShouldFcm) will trigger
 * appropriate error handling in the FcmChannel when this notification is used.
 *
 * @package Andydefer\FcmNotifications\Tests\Fixtures
 *
 * @see \Andydefer\FcmNotifications\Channels\FcmChannel
 * @see \Andydefer\FcmNotifications\Contracts\ShouldFcm
 */
class InvalidTestNotification extends Notification
{
    /**
     * Get the notification's delivery channels.
     *
     * This notification incorrectly declares support for the 'fcm' channel
     * without implementing the required ShouldFcm interface, making it useful
     * for testing validation logic.
     *
     * @param mixed $notifiable The notifiable entity
     * @return array<int, string> The delivery channels
     */
    public function via($notifiable): array
    {
        return ['fcm'];
    }

    /**
     * Intentionally missing toFcm() method.
     *
     * This method is deliberately omitted to create an invalid notification
     * that does not fulfill the ShouldFcm contract. The FcmChannel should
     * detect this and handle it appropriately.
     *
     * @see \Andydefer\FcmNotifications\Contracts\ShouldFcm::toFcm()
     */
}

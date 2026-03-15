<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Contracts;

use Andydefer\PushNotifier\Dtos\FcmMessageData;

interface ShouldFcm
{
    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): FcmMessageData;
}

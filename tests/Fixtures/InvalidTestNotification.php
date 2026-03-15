<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Illuminate\Notifications\Notification;

class InvalidTestNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['fcm'];
    }

    // Missing toFcm method
}

<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Fixtures;

use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Illuminate\Notifications\Notification;

class TestFcmNotification extends Notification implements ShouldFcm
{
    public function __construct(
        protected string $title = 'Test Title',
        protected string $body = 'Test Body',
        protected array $data = []
    ) {}

    public function via($notifiable): array
    {
        return ['fcm'];
    }

    public function toFcm($notifiable): FcmMessageData
    {
        return FcmMessageData::info(
            title: $this->title,
            body: $this->body,
            data: $this->data
        );
    }
}

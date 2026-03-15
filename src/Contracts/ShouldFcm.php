<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Contracts;

use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\FcmNotifications\Channels\FcmChannel;

/**
 * Contract for notifications that can be sent via Firebase Cloud Messaging (FCM).
 *
 * This interface must be implemented by any notification that should be sent
 * through the FCM channel. It ensures that the notification can provide a
 * properly formatted FCM message structure.
 *
 * @package Andydefer\FcmNotifications\Contracts
 *
 * @see FcmChannel
 * @see FcmMessageData
 */
interface ShouldFcm
{
    /**
     * Convert the notification to an FCM message data structure.
     *
     * This method is called by the FCM channel when preparing the notification
     * for delivery. It should return a properly configured FcmMessageData instance
     * containing all the information needed for the push notification.
     *
     * The method receives the notifiable entity, which can be used to customize
     * the message content based on the recipient's preferences or characteristics.
     *
     * @param object $notifiable The entity receiving the notification. Typically
     *                           implements HasFcmToken for token management.
     *
     * @return FcmMessageData The FCM message data structure containing title,
     *                        body, and optional data payload, images, or actions.
     *
     * @example
     * ```php
     * public function toFcm($notifiable): FcmMessageData
     * {
     *     return FcmMessageData::info(
     *         title: 'New Message',
     *         body: 'You have received a new message from ' . $this->sender,
     *         data: ['message_id' => $this->message->id]
     *     );
     * }
     * ```
     */
    public function toFcm(object $notifiable): FcmMessageData;
}

<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Channels;

use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\PushNotifier\Core\NotificationFactory;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Exceptions\FcmSendException;
use Andydefer\PushNotifier\Exceptions\FirebaseAuthException;
use Andydefer\PushNotifier\Exceptions\InvalidConfigurationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Exception;

/**
 * Laravel notification channel for sending Firebase Cloud Messaging (FCM) notifications.
 *
 * This channel handles sending push notifications to both single and multiple devices
 * using FCM. It supports token validation, automatic invalidation of expired tokens,
 * and comprehensive logging for debugging and monitoring purposes.
 *
 * @package Andydefer\FcmNotifications\Channels
 */
class FcmChannel implements ShouldQueue
{
    use InteractsWithQueue;

    private NotificationFactory $notificationFactory;
    private string $credentialsPath;

    /**
     * Create a new FCM channel instance.
     *
     * @param NotificationFactory|null $notificationFactory Factory for creating Firebase services
     * @param string|null $credentialsPath Path to the Firebase credentials JSON file
     */
    public function __construct(
        ?NotificationFactory $notificationFactory = null,
        ?string $credentialsPath = null
    ) {
        $this->notificationFactory = $notificationFactory ?? new NotificationFactory();
        $this->credentialsPath = $credentialsPath ?? Config::get('fcm.credentials');
    }

    /**
     * Send the given notification to FCM.
     *
     * This method validates that both the notifiable entity and notification
     * implement the required interfaces before attempting to send. It handles
     * single and multicast messages, and automatically invalidates tokens that
     * are no longer valid.
     *
     * @param mixed $notifiable The entity receiving the notification
     * @param Notification $notification The notification to send
     * @return void
     */
    public function send($notifiable, Notification $notification): void
    {
        if (!$this->isValidNotifiable($notifiable, $notification)) {
            return;
        }

        /** @var HasFcmToken $notifiable */
        $tokens = $notifiable->getFcmTokens();

        if (empty($tokens)) {
            return;
        }

        /** @var ShouldFcm $notification */
        $message = $notification->toFcm($notifiable);

        try {
            $this->sendToTokens($notifiable, $tokens, $message);
        } catch (Exception $exception) {
            $this->handleSendingException($exception, $notifiable, $notification);
        }
    }

    /**
     * Validate that the notifiable and notification implement required interfaces.
     *
     * @param mixed $notifiable The entity to validate
     * @param Notification $notification The notification to validate
     * @return bool True if both implement required interfaces
     */
    private function isValidNotifiable($notifiable, Notification $notification): bool
    {
        if (!$notifiable instanceof HasFcmToken) {
            $this->logWarning('Notifiable must implement HasFcmToken interface', $notifiable);
            return false;
        }

        if (!$notification instanceof ShouldFcm) {
            $this->logWarning('Notification must implement ShouldFcm interface', $notifiable, $notification);
            return false;
        }

        return true;
    }

    /**
     * Send the notification to one or multiple FCM tokens.
     *
     * @param HasFcmToken $notifiable The entity receiving the notification
     * @param array<string> $tokens Array of FCM tokens
     * @param FcmMessageData $message The message to send
     * @return void
     */
    private function sendToTokens(HasFcmToken $notifiable, array $tokens, FcmMessageData $message): void
    {
        $firebaseService = $this->notificationFactory->makeFirebaseServiceFromJsonFile(
            jsonFilePath: $this->credentialsPath
        );

        if (count($tokens) === 1) {
            $this->sendSingleNotification($notifiable, $tokens[0], $firebaseService, $message);
            return;
        }

        $this->sendMulticastNotification($notifiable, $tokens, $firebaseService, $message);
    }

    /**
     * Send a notification to a single device.
     *
     * @param HasFcmToken $notifiable The entity receiving the notification
     * @param string $token Single FCM token
     * @param mixed $firebaseService Firebase service instance
     * @param FcmMessageData $message The message to send
     * @return void
     */
    private function sendSingleNotification(
        HasFcmToken $notifiable,
        string $token,
        $firebaseService,
        FcmMessageData $message
    ): void {
        $response = $firebaseService->send($token, $message);

        if ($response->isInvalidToken()) {
            $notifiable->invalidateFcmToken($token);
            $this->logInfo('FCM token invalidated and removed', $notifiable, ['token' => $token]);
        }

        $this->logInfo('FCM notification sent successfully', $notifiable, [
            'token' => $token,
            'message_id' => $response->messageId,
        ]);
    }

    /**
     * Send a notification to multiple devices.
     *
     * @param HasFcmToken $notifiable The entity receiving the notification
     * @param array<string> $tokens Array of FCM tokens
     * @param mixed $firebaseService Firebase service instance
     * @param FcmMessageData $message The message to send
     * @return void
     */
    private function sendMulticastNotification(
        HasFcmToken $notifiable,
        array $tokens,
        $firebaseService,
        FcmMessageData $message
    ): void {
        $results = $firebaseService->sendMulticast($tokens, $message);
        $invalidTokens = 0;

        foreach ($results as $token => $response) {
            if ($response->isInvalidToken()) {
                $notifiable->invalidateFcmToken($token);
                $invalidTokens++;
                $this->logInfo('FCM token invalidated and removed', $notifiable, ['token' => $token]);
            }
        }

        $successCount = count(array_filter($results, fn($response) => $response->success));

        $this->logInfo('FCM multicast notification completed', $notifiable, [
            'total_tokens' => count($tokens),
            'successful_sends' => $successCount,
            'failed_sends' => count($tokens) - $successCount,
            'invalidated_tokens' => $invalidTokens,
        ]);
    }

    /**
     * Handle exceptions that occur during notification sending.
     *
     * @param Exception $exception The caught exception
     * @param HasFcmToken $notifiable The notifiable entity
     * @param Notification|ShouldFcm $notification The notification being sent
     * @return void
     * @throws Exception If queue is disabled, rethrows the exception
     */
    private function handleSendingException(
        Exception $exception,
        HasFcmToken $notifiable,
        Notification|ShouldFcm $notification
    ): void {
        $context = $this->buildExceptionContext($notifiable, $notification);

        match (true) {
            $exception instanceof InvalidConfigurationException =>
            Log::error('FCM configuration error: ' . $exception->getMessage(), $context),

            $exception instanceof FirebaseAuthException =>
            Log::error('FCM authentication failed: ' . $exception->getMessage(), $context),

            $exception instanceof FcmSendException =>
            Log::error(
                'FCM send operation failed: ' . $exception->getMessage(),
                $this->enrichFcmExceptionContext($context, $exception)
            ),

            default =>
            Log::error('Unexpected FCM error occurred: ' . $exception->getMessage(), $context),
        };

        if (Config::get('fcm.queue.enabled', true)) {
            $this->fail($exception);
        } else {
            throw $exception;
        }
    }

    /**
     * Build base context array for exception logging.
     *
     * @param HasFcmToken $notifiable The notifiable entity
     * @param Notification $notification The notification
     * @return array<string, mixed>
     */
    private function buildExceptionContext(HasFcmToken $notifiable, Notification $notification): array
    {
        return [
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id ?? null,
            'notification' => get_class($notification),
        ];
    }

    /**
     * Enrich exception context with FCM-specific details.
     *
     * @param array<string, mixed> $context Base context
     * @param FcmSendException $exception The FCM exception
     * @return array<string, mixed>
     */
    private function enrichFcmExceptionContext(array $context, FcmSendException $exception): array
    {
        return array_merge($context, [
            'error_code' => $exception->getErrorCode(),
            'status_code' => $exception->getStatusCode(),
        ]);
    }

    /**
     * Log a warning message if logging is enabled.
     *
     * @param string $message Warning message
     * @param mixed $notifiable The notifiable entity
     * @param Notification|null $notification Optional notification instance
     * @return void
     */
    private function logWarning(string $message, $notifiable, ?Notification $notification = null): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        Log::channel($this->getLogChannel())->warning($message, [
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id ?? null,
            'notification' => $notification ? get_class($notification) : null,
        ]);
    }

    /**
     * Log an info message if logging is enabled.
     *
     * @param string $message Info message
     * @param HasFcmToken $notifiable The notifiable entity
     * @param array<string, mixed> $extra Additional context data
     * @return void
     */
    private function logInfo(string $message, HasFcmToken $notifiable, array $extra = []): void
    {
        if (!$this->isLoggingEnabled()) {
            return;
        }

        Log::channel($this->getLogChannel())->info($message, array_merge([
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id ?? null,
        ], $extra));
    }

    /**
     * Check if logging is enabled in configuration.
     *
     * @return bool
     */
    private function isLoggingEnabled(): bool
    {
        return (bool) Config::get('fcm.logging.enabled', true);
    }

    /**
     * Get the configured logging channel.
     *
     * @return string
     */
    private function getLogChannel(): string
    {
        return Config::get('fcm.logging.channel', 'stack');
    }
}

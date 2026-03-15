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

class FcmChannel implements ShouldQueue
{
    use InteractsWithQueue;

    protected NotificationFactory $factory;
    protected string $credentialsPath;

    public function __construct(?NotificationFactory $factory = null, ?string $credentialsPath = null)
    {
        $this->factory = $factory ?? new NotificationFactory();
        $this->credentialsPath = $credentialsPath ?? Config::get('fcm.credentials');
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        // Validate notifiable implements the contract
        if (! $notifiable instanceof HasFcmToken) {
            $this->logWarning('Notifiable does not implement HasFcmToken', $notifiable);
            return;
        }

        // Vérification typée avec l'interface
        if (! $notification instanceof ShouldFcm) {
            $this->logWarning('Notification must implement ShouldFcm interface', $notifiable, $notification);
            return;
        }

        // ✅ Vérification cruciale : si pas de tokens, on arrête immédiatement
        if (! $notifiable->hasFcmTokens()) {
            return; // Pas de logs, pas d'erreurs, on ignore silencieusement
        }

        // Get tokens
        $tokens = $notifiable->getFcmTokens();

        if (empty($tokens)) {
            return;
        }

        // Maintenant PHPStan sait que toFcm existe
        $message = $notification->toFcm($notifiable);

        try {
            $this->sendNotification($notifiable, $tokens, $message);
        } catch (\Exception $e) {
            $this->handleException($e, $notifiable, $notification);
        }
    }

    /**
     * Send the notification to FCM.
     */
    protected function sendNotification(HasFcmToken $notifiable, array $tokens, FcmMessageData $message): void
    {
        $firebaseService = $this->factory->makeFirebaseServiceFromJsonFile($this->credentialsPath);

        // Single token
        if (count($tokens) === 1) {
            $token = $tokens[0];
            $response = $firebaseService->send($token, $message);

            if ($response->isInvalidToken()) {
                $notifiable->invalidateFcmToken($token);
                $this->logInfo('FCM token invalidated', $notifiable, ['token' => $token]);
            }

            $this->logInfo('FCM notification sent', $notifiable, [
                'token' => $token,
                'message_id' => $response->messageId,
            ]);

            return;
        }

        // Multiple tokens
        $results = $firebaseService->sendMulticast($tokens, $message);

        foreach ($results as $token => $response) {
            if ($response->isInvalidToken()) {
                $notifiable->invalidateFcmToken($token);
                $this->logInfo('FCM token invalidated', $notifiable, ['token' => $token]);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r->success));
        $this->logInfo('FCM multicast sent', $notifiable, [
            'total' => count($tokens),
            'success' => $successCount,
            'failed' => count($tokens) - $successCount,
        ]);
    }

    /**
     * Handle exceptions during sending.
     */
    protected function handleException(\Exception $e, $notifiable, $notification): void
    {
        $context = [
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id ?? null,
            'notification' => get_class($notification),
        ];

        match (true) {
            $e instanceof InvalidConfigurationException => Log::error('FCM configuration error: ' . $e->getMessage(), $context),
            $e instanceof FirebaseAuthException => Log::error('FCM authentication error: ' . $e->getMessage(), $context),
            $e instanceof FcmSendException => Log::error('FCM send error: ' . $e->getMessage(), array_merge($context, [
                'error_code' => $e->getErrorCode(),
                'status_code' => $e->getStatusCode(),
            ])),
            default => Log::error('Unexpected FCM error: ' . $e->getMessage(), $context),
        };

        if (Config::get('fcm.queue.enabled', true)) {
            $this->fail($e);
        } else {
            throw $e;
        }
    }

    /**
     * Log warning message.
     */
    protected function logWarning(string $message, $notifiable, $notification = null): void
    {
        if (! Config::get('fcm.logging.enabled', true)) {
            return;
        }

        Log::channel(Config::get('fcm.logging.channel', 'stack'))
            ->warning($message, [
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => $notification ? get_class($notification) : null,
            ]);
    }

    /**
     * Log info message.
     */
    protected function logInfo(string $message, $notifiable, array $extra = []): void
    {
        if (! Config::get('fcm.logging.enabled', true)) {
            return;
        }

        Log::channel(Config::get('fcm.logging.channel', 'stack'))
            ->info($message, array_merge([
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id ?? null,
            ], $extra));
    }
}

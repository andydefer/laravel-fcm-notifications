<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Console\Commands;

use Andydefer\FcmNotifications\Channels\FcmChannel;
use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Contracts\ShouldFcm;
use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notification;

class TestFcmConnectionCommand extends Command
{
    protected $signature = 'fcm:test-connection
                            {token? : A test FCM token to send a test notification}
                            {--title=Test Notification : Notification title}
                            {--body=This is a test notification from your application : Notification body}';

    protected $description = 'Test the Firebase Cloud Messaging connection';

    public function handle(FcmChannel $channel): int
    {
        $this->info('Testing FCM connection...');

        $token = $this->argument('token');

        if (! $token) {
            $this->error('Please provide a test FCM token.');
            return Command::FAILURE;
        }

        // Create a test notification class on the fly
        $notification = $this->createTestNotification(
            $this->option('title'),
            $this->option('body')
        );

        // Create a test notifiable object
        $notifiable = $this->createTestNotifiable($token);

        try {
            $channel->send($notifiable, $notification);
            $this->info('✅ Test notification sent successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send test notification: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Create a test notification class.
     */
    private function createTestNotification(string $title, string $body): Notification
    {
        return new class($title, $body) extends Notification implements ShouldFcm {
            protected string $title;
            protected string $body;

            public function __construct(string $title, string $body)
            {
                $this->title = $title;
                $this->body = $body;
            }

            public function via($notifiable): array
            {
                return ['fcm'];
            }

            public function toFcm($notifiable): FcmMessageData
            {
                return FcmMessageData::info(
                    title: $this->title,
                    body: $this->body
                );
            }
        };
    }

    /**
     * Create a test notifiable object implementing HasFcmToken.
     */
    private function createTestNotifiable(string $token): HasFcmToken
    {
        return new class($token) implements HasFcmToken {
            protected string $token;
            protected array $tokens = [];

            public function __construct(string $token)
            {
                $this->token = $token;
                $this->tokens = [$token];
            }

            public function fcmTokens(): MorphMany
            {
                return new class extends MorphMany {
                    public function __construct()
                    {
                        // Skip parent constructor
                    }

                    public function getResults()
                    {
                        return collect();
                    }

                    public function addConstraints(): void {}
                    public function addEagerConstraints(array $models): void {}
                    public function initRelation(array $models, $relation): array
                    {
                        return $models;
                    }
                    public function match(array $models, $results, $relation): array
                    {
                        return $models;
                    }
                    public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
                    {
                        return $query;
                    }
                };
            }

            public function getFcmTokens(): array
            {
                return $this->tokens;
            }

            public function getPrimaryFcmToken(): ?string
            {
                return $this->token;
            }

            public function hasFcmTokens(): bool
            {
                return !empty($this->tokens);
            }

            public function registerFcmToken(
                string $token,
                bool $isPrimary = false,
                array $metadata = []
            ): FcmToken {
                $fcmToken = new FcmToken();
                $fcmToken->token = $token;
                $fcmToken->is_primary = $isPrimary;
                $fcmToken->metadata = $metadata;
                $fcmToken->is_valid = true;
                $fcmToken->last_used_at = now();

                if (!in_array($token, $this->tokens)) {
                    $this->tokens[] = $token;
                }

                return $fcmToken;
            }

            public function invalidateFcmToken(string $token): bool
            {
                $this->tokens = array_values(array_diff($this->tokens, [$token]));
                return true;
            }

            public function invalidateAllFcmTokens(): int
            {
                $count = count($this->tokens);
                $this->tokens = [];
                return $count;
            }

            public function routeNotificationForFcm($notification): ?array
            {
                return $this->getFcmTokens();
            }
        };
    }
}

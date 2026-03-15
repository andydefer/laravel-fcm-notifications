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
use Exception;

/**
 * Console command to test Firebase Cloud Messaging connectivity.
 *
 * This command allows developers to verify their FCM configuration by sending
 * a test notification to a provided device token. It creates a temporary
 * notifiable object and notification class to simulate a real notification
 * flow without requiring existing database records.
 *
 * @package Andydefer\FcmNotifications\Console\Commands
 */
class TestFcmConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:test-connection
                            {token? : A valid FCM device token to send the test notification to}
                            {--title=Test Notification : The title of the test notification}
                            {--body=This is a test notification from your application : The body content of the test notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify FCM configuration by sending a test push notification';

    /**
     * Execute the console command.
     *
     * This method orchestrates the test notification process:
     * 1. Validates that a token is provided
     * 2. Creates a temporary notification with the provided title and body
     * 3. Creates a temporary notifiable object with the test token
     * 4. Attempts to send the notification through the FCM channel
     * 5. Reports success or failure with appropriate messaging
     *
     * @param FcmChannel $channel The FCM notification channel
     * @return int Command execution status (0 for success, 1 for failure)
     */
    public function handle(FcmChannel $channel): int
    {
        $this->info('Initiating FCM connection test...');

        $token = $this->getTestToken();

        if ($token === null) {
            return Command::FAILURE;
        }

        $notification = $this->buildTestNotification(
            title: $this->option('title'),
            body: $this->option('body')
        );

        $notifiable = $this->createMockNotifiable($token);

        try {
            $channel->send($notifiable, $notification);
            $this->info('✅ Test notification sent successfully to FCM!');
            return Command::SUCCESS;
        } catch (Exception $exception) {
            $this->showErrorWithTroubleshooting($exception);
            return Command::FAILURE;
        }
    }

    /**
     * Retrieve and validate the test token from command arguments.
     *
     * @return string|null The validated token or null if validation fails
     */
    private function getTestToken(): ?string
    {
        $token = $this->argument('token');

        if (empty($token)) {
            $this->error('❌ A valid FCM token is required for testing.');
            $this->line('Please provide a token as an argument: php artisan fcm:test-connection YOUR_TOKEN_HERE');
            return null;
        }

        return $token;
    }

    /**
     * Create a temporary test notification instance.
     *
     * @param string $title The notification title
     * @param string $body The notification body content
     * @return Notification A notification instance implementing ShouldFcm
     */
    private function buildTestNotification(string $title, string $body): Notification
    {
        return new class($title, $body) extends Notification implements ShouldFcm {
            private string $title;
            private string $body;

            /**
             * Create a new test notification instance.
             *
             * @param string $title The notification title
             * @param string $body The notification body
             */
            public function __construct(string $title, string $body)
            {
                $this->title = $title;
                $this->body = $body;
            }

            /**
             * Define the delivery channels for the notification.
             *
             * @param mixed $notifiable The notifiable entity
             * @return array<int, string>
             */
            public function via($notifiable): array
            {
                return ['fcm'];
            }

            /**
             * Convert the notification to an FCM message.
             *
             * @param mixed $notifiable The notifiable entity
             * @return FcmMessageData The FCM message data
             */
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
     * Create a temporary test notifiable object.
     *
     * @param string $token The FCM token to use for testing
     * @return HasFcmToken A notifiable instance implementing HasFcmToken
     */
    private function createMockNotifiable(string $token): HasFcmToken
    {
        return new class($token) implements HasFcmToken {
            private string $primaryToken;
            private array $tokens = [];

            /**
             * Create a new test notifiable instance.
             *
             * @param string $token The primary FCM token
             */
            public function __construct(string $token)
            {
                $this->primaryToken = $token;
                $this->tokens = [$token];
            }

            /**
             * Get the FCM tokens relationship.
             *
             * @return MorphMany
             */
            public function fcmTokens(): MorphMany
            {
                return new class extends MorphMany {
                    /**
                     * Create a mock MorphMany relationship.
                     */
                    public function __construct()
                    {
                        // Skip parent constructor to avoid database requirements
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function getResults()
                    {
                        return collect();
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function addConstraints(): void {}

                    /**
                     * {@inheritDoc}
                     */
                    public function addEagerConstraints(array $models): void {}

                    /**
                     * {@inheritDoc}
                     */
                    public function initRelation(array $models, $relation): array
                    {
                        return $models;
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function match(array $models, $results, $relation): array
                    {
                        return $models;
                    }

                    /**
                     * {@inheritDoc}
                     */
                    public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
                    {
                        return $query;
                    }
                };
            }

            /**
             * Get all valid FCM tokens.
             *
             * @return array<string> Array of FCM tokens
             */
            public function getFcmTokens(): array
            {
                return $this->tokens;
            }

            /**
             * Get the primary FCM token.
             *
             * @return string|null The primary token or null if none exists
             */
            public function getPrimaryFcmToken(): ?string
            {
                return $this->primaryToken;
            }

            /**
             * Check if any FCM tokens exist.
             *
             * @return bool True if at least one token exists
             */
            public function hasFcmTokens(): bool
            {
                return !empty($this->tokens);
            }

            /**
             * Register a new FCM token.
             *
             * @param string $token The token to register
             * @param bool $isPrimary Whether this should be the primary token
             * @param array<string, mixed> $metadata Additional token metadata
             * @return FcmToken The registered token model
             */
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

                if (!in_array($token, $this->tokens, true)) {
                    $this->tokens[] = $token;
                }

                if ($isPrimary) {
                    $this->primaryToken = $token;
                }

                return $fcmToken;
            }

            /**
             * Invalidate a specific FCM token.
             *
             * @param string $token The token to invalidate
             * @return bool True if token was found and invalidated
             */
            public function invalidateFcmToken(string $token): bool
            {
                $this->tokens = array_values(array_diff($this->tokens, [$token]));

                if ($this->primaryToken === $token) {
                    $this->primaryToken = $this->tokens[0] ?? '';
                }

                return true;
            }

            /**
             * Invalidate all FCM tokens.
             *
             * @return int Number of tokens invalidated
             */
            public function invalidateAllFcmTokens(): int
            {
                $count = count($this->tokens);
                $this->tokens = [];
                $this->primaryToken = '';
                return $count;
            }

            /**
             * Get the FCM tokens for notification routing.
             *
             * @param mixed $notification The notification being routed
             * @return array<string>|null Array of tokens or null if none exist
             */
            public function routeNotificationForFcm($notification): ?array
            {
                return $this->getFcmTokens();
            }
        };
    }

    /**
     * Display a formatted error message with troubleshooting tips.
     *
     * @param Exception $exception The caught exception
     * @return void
     */
    private function showErrorWithTroubleshooting(Exception $exception): void
    {
        $this->error('❌ Failed to send test notification: ' . $exception->getMessage());

        if ($this->isVerbose()) {
            $this->line('Exception details:');
            $this->line($exception->getTraceAsString());
        }

        $this->line('');
        $this->warn('Common issues:');
        $this->line('  • Verify your FCM credentials file path in config/fcm.php');
        $this->line('  • Check that the FCM token is valid and not expired');
        $this->line('  • Ensure your Firebase project has Cloud Messaging enabled');
        $this->line('  • Confirm the device with this token is online');
    }

    /**
     * Check if the command is running in verbose mode.
     *
     * @return bool True if verbose output is enabled
     */
    private function isVerbose(): bool
    {
        return $this->getOutput()->isVerbose();
    }
}

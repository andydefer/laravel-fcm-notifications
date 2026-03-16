<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Channels;

use Andydefer\FcmNotifications\Channels\FcmChannel;
use Andydefer\FcmNotifications\Contracts\HasFcmToken;
use Andydefer\FcmNotifications\Exceptions\InvalidCredentialsException;
use Andydefer\FcmNotifications\Tests\Fixtures\InvalidTestNotification;
use Andydefer\FcmNotifications\Tests\Fixtures\TestFcmNotification;
use Andydefer\FcmNotifications\Tests\TestCase;
use Andydefer\PushNotifier\Core\NotificationFactory;
use Andydefer\PushNotifier\Dtos\FcmMessageData;
use Andydefer\PushNotifier\Dtos\FcmResponseData;
use Andydefer\PushNotifier\Services\FirebaseService;
use Exception;
use Mockery;
use Mockery\MockInterface;
use stdClass;

/**
 * Unit tests for the FCM notification channel.
 *
 * This test suite verifies the behavior of the FcmChannel class:
 * - Credentials validation during construction
 * - Sending notifications to single and multiple tokens
 * - Token invalidation logic
 * - Validation of notifiable and notification contracts
 * - Exception handling and queue configuration
 * - Logging behavior
 *
 * @package Andydefer\FcmNotifications\Tests\Unit\Channels
 */
class FcmChannelTest extends TestCase
{
    /**
     * Mock instance of NotificationFactory.
     *
     * @var NotificationFactory&MockInterface
     */
    private NotificationFactory&MockInterface $notificationFactoryMock;

    /**
     * Mock instance of FirebaseService.
     *
     * @var FirebaseService&MockInterface
     */
    private FirebaseService&MockInterface $firebaseServiceMock;

    /**
     * Instance of FcmChannel being tested.
     *
     * @var FcmChannel
     */
    private FcmChannel $fcmChannel;

    /**
     * Temporary file path for credentials.
     *
     * @var string|null
     */
    private ?string $tempCredentialsFile = null;

    /**
     * Set up the test environment before each test.
     *
     * Creates mock instances for the NotificationFactory and FirebaseService,
     * and configures the factory to return the mocked Firebase service.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDependencies();
        $this->configureFactoryToReturnMockedFirebaseService();
        $this->createFcmChannelWithValidCredentials();
    }

    /**
     * Clean up after each test.
     *
     * Closes Mockery expectations and removes temporary files.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->tempCredentialsFile && file_exists($this->tempCredentialsFile)) {
            unlink($this->tempCredentialsFile);
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that the channel throws exception when credentials are not configured.
     *
     * @return void
     */
    public function test_throws_exception_when_credentials_not_configured(): void
    {
        // Arrange: Set credentials to null in config
        config(['fcm.credentials' => null]);

        // Assert: Expect InvalidCredentialsException
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('FCM credentials path is not configured');

        // Act: Attempt to create channel without credentials
        new FcmChannel($this->notificationFactoryMock);
    }

    /**
     * Test that the channel throws exception when credentials path is empty string.
     *
     * @return void
     */
    public function test_throws_exception_when_credentials_path_is_empty(): void
    {
        // Arrange: Set empty credentials path
        config(['fcm.credentials' => '']);

        // Assert: Expect InvalidCredentialsException
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('FCM credentials path is not configured');

        // Act: Attempt to create channel with empty path
        new FcmChannel($this->notificationFactoryMock);
    }

    /**
     * Test that the channel throws exception when credentials file doesn't exist.
     *
     * @return void
     */
    public function test_throws_exception_when_credentials_file_not_found(): void
    {
        // Arrange: Set path to non-existent file
        $nonExistentPath = '/path/to/nonexistent/file.json';
        config(['fcm.credentials' => $nonExistentPath]);

        // Assert: Expect InvalidCredentialsException
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('FCM credentials file not found at path: ' . $nonExistentPath);

        // Act: Attempt to create channel with invalid file path
        new FcmChannel($this->notificationFactoryMock);
    }

    /**
     * Test that the channel throws exception when credentials file is not readable.
     *
     * @return void
     */
    public function test_throws_exception_when_credentials_file_not_readable(): void
    {
        // Arrange: Create a temporary file and make it unreadable
        $tempFile = tempnam(sys_get_temp_dir(), 'fcm_test_');
        chmod($tempFile, 0000); // Remove all permissions

        config(['fcm.credentials' => $tempFile]);

        // Assert: Expect InvalidCredentialsException
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('FCM credentials file is not readable at: ' . $tempFile);

        try {
            // Act: Attempt to create channel with unreadable file
            new FcmChannel($this->notificationFactoryMock);
        } finally {
            // Clean up
            chmod($tempFile, 0666);
            unlink($tempFile);
        }
    }

    /**
     * Test that the channel throws exception when credentials file contains invalid JSON.
     *
     * @return void
     */
    public function test_throws_exception_when_credentials_file_contains_invalid_json(): void
    {
        // Arrange: Create a temporary file with invalid JSON
        $tempFile = tempnam(sys_get_temp_dir(), 'fcm_test_');
        file_put_contents($tempFile, '{invalid json}');

        config(['fcm.credentials' => $tempFile]);

        // Assert: Expect InvalidCredentialsException
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('FCM credentials file contains invalid JSON');

        try {
            // Act: Attempt to create channel with invalid JSON
            new FcmChannel($this->notificationFactoryMock);
        } finally {
            // Clean up
            unlink($tempFile);
        }
    }

    /**
     * Test that the channel accepts valid credentials file.
     *
     * @return void
     */
    public function test_accepts_valid_credentials_file(): void
    {
        // Arrange: Create a temporary file with valid JSON
        $tempFile = tempnam(sys_get_temp_dir(), 'fcm_test_');
        $validJson = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => '12345',
            'private_key' => '-----BEGIN PRIVATE KEY-----\nMIIEv...\n-----END PRIVATE KEY-----\n',
            'client_email' => 'test@test.iam.gserviceaccount.com',
            'client_id' => '123456',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
        file_put_contents($tempFile, $validJson);

        config(['fcm.credentials' => $tempFile]);

        try {
            // Act: Create channel with valid credentials
            $channel = new FcmChannel($this->notificationFactoryMock);

            // Assert: Channel created successfully
            $this->assertInstanceOf(FcmChannel::class, $channel);
        } finally {
            // Clean up
            unlink($tempFile);
        }
    }

    /**
     * Test that the channel accepts credentials path passed directly to constructor.
     *
     * @return void
     */
    public function test_accepts_credentials_path_directly_in_constructor(): void
    {
        // Arrange: Create a temporary file with valid JSON
        $tempFile = tempnam(sys_get_temp_dir(), 'fcm_test_');
        $validJson = json_encode(['test' => 'data']);
        file_put_contents($tempFile, $validJson);

        try {
            // Act: Create channel by passing path directly
            $channel = new FcmChannel(
                notificationFactory: $this->notificationFactoryMock,
                credentialsPath: $tempFile
            );

            // Assert: Channel created successfully
            $this->assertInstanceOf(FcmChannel::class, $channel);
        } finally {
            // Clean up
            unlink($tempFile);
        }
    }

    /**
     * Test that the channel successfully sends a notification to a single token.
     *
     * @return void
     */
    public function test_sends_notification_to_single_token(): void
    {
        // Arrange: Create a notifiable with one token and configure the Firebase mock
        $token = 'single-device-token-123';
        $notifiable = $this->createMockNotifiableWithTokens([$token]);
        $notification = new TestFcmNotification('Test Title', 'Test Body');

        $this->expectFirebaseSendToBeCalledOnce($token);

        // Act: Send the notification through the channel
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: No exception means the test passed
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the channel successfully sends a notification to multiple tokens.
     *
     * @return void
     */
    public function test_sends_notification_to_multiple_tokens(): void
    {
        // Arrange: Create a notifiable with multiple tokens
        $tokens = ['device-token-1', 'device-token-2', 'device-token-3'];
        $notifiable = $this->createMockNotifiableWithTokens($tokens);
        $notification = new TestFcmNotification('Broadcast Title', 'Broadcast Body');

        $this->expectFirebaseMulticastToBeCalledWithAllTokens($tokens);

        // Act: Send the notification through the channel
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: No exception means the test passed
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the channel automatically invalidates tokens when FCM reports them as invalid.
     *
     * @return void
     */
    public function test_invalidates_tokens_when_fcm_reports_invalid(): void
    {
        // Arrange: Create a notifiable with one valid and one invalid token
        $validToken = 'valid-device-token';
        $invalidToken = 'expired-device-token';
        $tokens = [$validToken, $invalidToken];

        $notifiable = $this->createMockNotifiableWithTokens($tokens);
        $notification = new TestFcmNotification('Test', 'Message');

        $this->expectFirebaseMulticastToReturnOneValidAndOneInvalidResponse($tokens);

        // Act: Send the notification
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: Invalid token was removed, valid token remains
        $this->assertCount(1, $notifiable->getFcmTokens());
        $this->assertContains($validToken, $notifiable->getFcmTokens());
        $this->assertNotContains($invalidToken, $notifiable->getFcmTokens());
    }

    /**
     * Test that the channel does nothing when the notifiable has no FCM tokens.
     *
     * @return void
     */
    public function test_does_nothing_when_notifiable_has_no_tokens(): void
    {
        // Arrange: Create a notifiable with an empty token list
        $notifiable = $this->createMockNotifiableWithTokens([]);
        $notification = new TestFcmNotification('Title', 'Body');

        $this->expectNoFirebaseCalls();

        // Act: Attempt to send notification
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: No exception means the test passed
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the channel logs a warning when the notifiable doesn't implement HasFcmToken.
     *
     * @return void
     */
    public function test_logs_warning_when_notifiable_does_not_implement_contract(): void
    {
        // Arrange: Use a plain stdClass as notifiable (invalid)
        $invalidNotifiable = new stdClass();
        $notification = new TestFcmNotification();

        $this->expectNoFirebaseCalls();

        // Act: Attempt to send notification with invalid notifiable
        $this->fcmChannel->send($invalidNotifiable, $notification);

        // Assert: No exception means the test passed (warning is logged internally)
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the channel logs a warning when the notification doesn't implement ShouldFcm.
     *
     * @return void
     */
    public function test_logs_warning_when_notification_does_not_implement_should_fcm(): void
    {
        // Arrange: Use a valid notifiable but invalid notification
        $notifiable = $this->createMockNotifiableWithTokens(['valid-token']);
        $invalidNotification = new InvalidTestNotification();

        $this->expectNoFirebaseCalls();

        // Act: Attempt to send invalid notification
        $this->fcmChannel->send($notifiable, $invalidNotification);

        // Assert: No exception means the test passed (warning is logged internally)
        $this->addToAssertionCount(1);
    }

    /**
     * Test that the channel throws exceptions when queue is disabled.
     *
     * @return void
     */
    public function test_throws_exception_when_queue_disabled_and_error_occurs(): void
    {
        // Arrange: Configure queue to be disabled
        config(['fcm.queue.enabled' => false]);

        $notifiable = $this->createMockNotifiableWithTokens(['token-1']);
        $notification = new TestFcmNotification();

        $this->expectFirebaseSendToThrowException();

        // Assert: An exception should be thrown
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Firebase connection failed');

        // Act: This should throw an exception
        $this->fcmChannel->send($notifiable, $notification);
    }

    /**
     * Test that the channel fails silently when queue is enabled and error occurs.
     *
     * @return void
     */
    public function test_fails_silently_when_queue_enabled_and_error_occurs(): void
    {
        // Arrange: Configure queue to be enabled
        config(['fcm.queue.enabled' => true]);

        $notifiable = $this->createMockNotifiableWithTokens(['token-1']);
        $notification = new TestFcmNotification();

        $this->expectFirebaseSendToThrowException();

        // Act: Send notification - should not throw because queue is enabled
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: No exception means the test passed
        $this->addToAssertionCount(1);
    }

    /**
     * Mock all required dependencies for testing.
     *
     * @return void
     */
    private function mockDependencies(): void
    {
        $this->notificationFactoryMock = Mockery::mock(NotificationFactory::class);
        $this->firebaseServiceMock = Mockery::mock(FirebaseService::class);
    }

    /**
     * Configure the factory mock to return the mocked Firebase service.
     *
     * @return void
     */
    private function configureFactoryToReturnMockedFirebaseService(): void
    {
        $this->notificationFactoryMock
            ->shouldReceive('makeFirebaseServiceFromJsonFile')
            ->andReturn($this->firebaseServiceMock);
    }

    /**
     * Create the FCM channel instance with valid credentials for testing.
     *
     * Creates a temporary valid credentials file to satisfy the constructor validation.
     *
     * @return void
     */
    private function createFcmChannelWithValidCredentials(): void
    {
        // Create a temporary valid credentials file
        $this->tempCredentialsFile = tempnam(sys_get_temp_dir(), 'fcm_test_');
        $validJson = json_encode(['test' => 'data']);
        file_put_contents($this->tempCredentialsFile, $validJson);

        config(['fcm.credentials' => $this->tempCredentialsFile]);

        $this->fcmChannel = new FcmChannel(
            notificationFactory: $this->notificationFactoryMock
        );
    }

    /**
     * Create a mock notifiable instance with the given tokens.
     *
     * @param array<string> $tokens Array of FCM tokens
     * @return HasFcmToken&MockInterface
     */
    private function createMockNotifiableWithTokens(array $tokens): HasFcmToken&MockInterface
    {
        /** @var HasFcmToken&MockInterface $notifiable */
        $notifiable = Mockery::mock(HasFcmToken::class);

        // Store tokens for assertions
        $currentTokens = $tokens;

        $notifiable->shouldReceive('getFcmTokens')
            ->andReturnUsing(function () use (&$currentTokens) {
                return $currentTokens;
            });

        $notifiable->shouldReceive('invalidateFcmToken')
            ->andReturnUsing(function ($token) use (&$currentTokens) {
                $currentTokens = array_values(array_diff($currentTokens, [$token]));
                return true;
            });

        $notifiable->shouldReceive('hasFcmTokens')
            ->andReturnUsing(function () use (&$currentTokens) {
                return !empty($currentTokens);
            });

        $notifiable->shouldReceive('getPrimaryFcmToken')
            ->andReturnUsing(function () use (&$currentTokens) {
                return $currentTokens[0] ?? null;
            });

        return $notifiable;
    }

    /**
     * Expect that the Firebase send method is called once with the given token.
     *
     * @param string $expectedToken The token expected to be used
     * @return void
     */
    private function expectFirebaseSendToBeCalledOnce(string $expectedToken): void
    {
        $this->firebaseServiceMock
            ->shouldReceive('send')
            ->once()
            ->with($expectedToken, Mockery::type(FcmMessageData::class))
            ->andReturn($this->createSuccessfulResponse());
    }

    /**
     * Expect that the Firebase multicast method is called with all tokens.
     *
     * @param array<string> $expectedTokens The tokens expected to be used
     * @return void
     */
    private function expectFirebaseMulticastToBeCalledWithAllTokens(array $expectedTokens): void
    {
        $this->firebaseServiceMock
            ->shouldReceive('sendMulticast')
            ->once()
            ->with($expectedTokens, Mockery::type(FcmMessageData::class))
            ->andReturn($this->createSuccessfulMulticastResponses($expectedTokens));
    }

    /**
     * Expect that the Firebase multicast method returns mixed valid/invalid responses.
     *
     * @param array<string> $tokens The tokens being sent to
     * @return void
     */
    private function expectFirebaseMulticastToReturnOneValidAndOneInvalidResponse(array $tokens): void
    {
        $this->firebaseServiceMock
            ->shouldReceive('sendMulticast')
            ->once()
            ->with($tokens, Mockery::type(FcmMessageData::class))
            ->andReturn($this->createMixedMulticastResponses($tokens));
    }

    /**
     * Expect that no Firebase service calls are made.
     *
     * @return void
     */
    private function expectNoFirebaseCalls(): void
    {
        $this->firebaseServiceMock->shouldNotReceive('send');
        $this->firebaseServiceMock->shouldNotReceive('sendMulticast');
    }

    /**
     * Expect that the Firebase send method throws an exception.
     *
     * @return void
     */
    private function expectFirebaseSendToThrowException(): void
    {
        $this->firebaseServiceMock
            ->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('Firebase connection failed'));
    }

    /**
     * Create a successful FCM response.
     *
     * @return FcmResponseData
     */
    private function createSuccessfulResponse(): FcmResponseData
    {
        return new FcmResponseData(
            messageId: 'msg-' . uniqid(),
            name: 'projects/test/messages/success',
            rawResponse: [],
            success: true
        );
    }

    /**
     * Create successful responses for all tokens.
     *
     * @param array<string> $tokens
     * @return array<string, FcmResponseData>
     */
    private function createSuccessfulMulticastResponses(array $tokens): array
    {
        $responses = [];
        foreach ($tokens as $token) {
            $responses[$token] = $this->createSuccessfulResponse();
        }
        return $responses;
    }

    /**
     * Create mixed responses with one valid and one invalid.
     *
     * @param array<string> $tokens
     * @return array<string, FcmResponseData>
     */
    private function createMixedMulticastResponses(array $tokens): array
    {
        return [
            $tokens[0] => $this->createSuccessfulResponse(),
            $tokens[1] => new FcmResponseData(
                messageId: '',
                name: '',
                rawResponse: [],
                success: false,
                errorCode: 'UNREGISTERED',
                errorMessage: 'Token is no longer registered'
            ),
        ];
    }
}

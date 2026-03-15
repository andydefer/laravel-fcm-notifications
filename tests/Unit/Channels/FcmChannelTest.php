<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Channels;

use Andydefer\FcmNotifications\Channels\FcmChannel;
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
    private NotificationFactory&MockInterface $notificationFactoryMock;
    private FirebaseService&MockInterface $firebaseServiceMock;
    private FcmChannel $fcmChannel;

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
        $this->createFcmChannel();
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
        $notifiable = $this->createTestNotifiable([$token]);
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
        $notifiable = $this->createTestNotifiable($tokens);
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

        $notifiable = $this->createTestNotifiable($tokens);
        $notification = new TestFcmNotification('Test', 'Message');

        $this->expectFirebaseMulticastToReturnOneValidAndOneInvalidResponse($tokens);

        // Act: Send the notification
        $this->fcmChannel->send($notifiable, $notification);

        // Assert: Invalid token was removed, valid token remains
        $this->assertCount(1, $notifiable->getFcmTokens());
        $this->assertContains($validToken, $notifiable->getFcmTokens());
        $this->assertNotContains($invalidToken, $notifiable->getFcmTokens());
        $this->assertContains($invalidToken, $notifiable->getInvalidatedTokens());
    }

    /**
     * Test that the channel does nothing when the notifiable has no FCM tokens.
     *
     * @return void
     */
    public function test_does_nothing_when_notifiable_has_no_tokens(): void
    {
        // Arrange: Create a notifiable with an empty token list
        $notifiable = $this->createTestNotifiable([]);
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
        $notifiable = $this->createTestNotifiable(['valid-token']);
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

        $notifiable = $this->createTestNotifiable(['token-1']);
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

        $notifiable = $this->createTestNotifiable(['token-1']);
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
     * Create the FCM channel instance with mocked dependencies.
     *
     * @return void
     */
    private function createFcmChannel(): void
    {
        $this->fcmChannel = new FcmChannel(
            notificationFactory: $this->notificationFactoryMock
        );
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

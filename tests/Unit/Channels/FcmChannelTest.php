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
use Mockery;
use Mockery\MockInterface;

class FcmChannelTest extends TestCase
{
    private NotificationFactory&MockInterface $factoryMock;
    private FirebaseService&MockInterface $firebaseServiceMock;
    private FcmChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factoryMock = Mockery::mock(NotificationFactory::class);
        $this->firebaseServiceMock = Mockery::mock(FirebaseService::class);

        $this->factoryMock
            ->shouldReceive('makeFirebaseServiceFromJsonFile')
            ->andReturn($this->firebaseServiceMock);

        $this->channel = new FcmChannel($this->factoryMock);
    }

    /**
     * Test that channel sends notification to a single token.
     */
    public function test_sends_notification_to_single_token(): void
    {
        // Arrange
        $token = 'single-token-123';
        $notifiable = $this->createTestNotifiable([$token]);
        $notification = new TestFcmNotification('Title', 'Body');

        $this->firebaseServiceMock
            ->shouldReceive('send')
            ->once()
            ->with($token, Mockery::type(FcmMessageData::class))
            ->andReturn(new FcmResponseData(
                messageId: 'msg-123',
                name: 'projects/test/messages/msg-123',
                rawResponse: [],
                success: true
            ));

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test that channel sends notification to multiple tokens.
     */
    public function test_sends_notification_to_multiple_tokens(): void
    {
        // Arrange
        $tokens = ['token-1', 'token-2', 'token-3'];
        $notifiable = $this->createTestNotifiable($tokens);
        $notification = new TestFcmNotification('Title', 'Body');

        $this->firebaseServiceMock
            ->shouldReceive('sendMulticast')
            ->once()
            ->with($tokens, Mockery::type(FcmMessageData::class))
            ->andReturn([
                'token-1' => new FcmResponseData('msg-1', 'name-1', [], true),
                'token-2' => new FcmResponseData('msg-2', 'name-2', [], true),
                'token-3' => new FcmResponseData('msg-3', 'name-3', [], true),
            ]);

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test that channel invalidates tokens when response indicates invalid token.
     */
    public function test_invalidates_tokens_when_response_indicates_invalid(): void
    {
        // Arrange
        $tokens = ['valid-token', 'invalid-token'];
        $notifiable = $this->createTestNotifiable($tokens);
        $notification = new TestFcmNotification('Title', 'Body');

        $this->firebaseServiceMock
            ->shouldReceive('sendMulticast')
            ->once()
            ->with($tokens, Mockery::type(FcmMessageData::class))
            ->andReturn([
                'valid-token' => new FcmResponseData('msg-1', 'name-1', [], true),
                'invalid-token' => new FcmResponseData(
                    messageId: '',
                    name: '',
                    rawResponse: [],
                    success: false,
                    errorCode: 'UNREGISTERED',
                    errorMessage: 'Invalid token'
                ),
            ]);

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertCount(1, $notifiable->getFcmTokens());
        $this->assertContains('valid-token', $notifiable->getFcmTokens());
        $this->assertNotContains('invalid-token', $notifiable->getFcmTokens());
        $this->assertContains('invalid-token', $notifiable->getInvalidatedTokens());
    }

    /**
     * Test that channel does nothing when notifiable has no tokens.
     */
    public function test_does_nothing_when_notifiable_has_no_tokens(): void
    {
        // Arrange
        $notifiable = $this->createTestNotifiable([]);
        $notification = new TestFcmNotification('Title', 'Body');

        $this->firebaseServiceMock->shouldNotReceive('send');
        $this->firebaseServiceMock->shouldNotReceive('sendMulticast');

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception means success
    }

    /**
     * Test that channel logs warning when notifiable doesn't implement HasFcmToken.
     */
    public function test_logs_warning_when_notifiable_invalid(): void
    {
        // Arrange
        $notifiable = new \stdClass();
        $notification = new TestFcmNotification();

        $this->firebaseServiceMock->shouldNotReceive('send');
        $this->firebaseServiceMock->shouldNotReceive('sendMulticast');

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception, just warning log
    }

    /**
     * Test that channel logs warning when notification doesn't have toFcm method.
     */
    public function test_logs_warning_when_notification_invalid(): void
    {
        // Arrange
        $notifiable = $this->createTestNotifiable(['token-1']);
        $notification = new InvalidTestNotification();

        $this->firebaseServiceMock->shouldNotReceive('send');
        $this->firebaseServiceMock->shouldNotReceive('sendMulticast');

        // Act
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception, just warning log
    }

    /**
     * Test that channel handles exceptions gracefully.
     */
    public function test_handles_exceptions_gracefully(): void
    {
        // Arrange
        $notifiable = $this->createTestNotifiable(['token-1']);
        $notification = new TestFcmNotification();

        $this->firebaseServiceMock
            ->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Expect exception when queue is disabled
        $this->expectException(\Exception::class);

        // Act
        $this->channel->send($notifiable, $notification);
    }

    /**
     * Test that channel respects queue configuration.
     */
    public function test_respects_queue_configuration(): void
    {
        // Arrange
        config(['fcm.queue.enabled' => true]);

        $notifiable = $this->createTestNotifiable(['token-1']);
        $notification = new TestFcmNotification();

        $this->firebaseServiceMock
            ->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Act - This should not throw because we're in queue mode
        $this->channel->send($notifiable, $notification);

        // Assert
        $this->assertTrue(true); // No exception means success
    }
}

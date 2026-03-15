<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Console\Commands;

use Andydefer\FcmNotifications\Channels\FcmChannel;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Exception;

/**
 * Unit tests for the FCM test connection console command.
 *
 * This test suite verifies the behavior of the fcm:test-connection command:
 * - Token validation and requirement
 * - Successful notification sending
 * - Error handling with various exception types
 * - Default values for title and body
 * - Verbose mode output
 *
 * @package Andydefer\FcmNotifications\Tests\Unit\Console\Commands
 */
class TestFcmConnectionCommandTest extends TestCase
{
    /**
     * The FCM channel mock instance.
     */
    private FcmChannel|Mockery\MockInterface $channelMock;

    /**
     * Set up the test environment before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->channelMock = Mockery::mock(FcmChannel::class);
    }

    /**
     * Test that the command fails when no token is provided.
     */
    public function test_fails_when_no_token_provided(): void
    {
        // Act: Run the command without providing a token argument
        $exitCode = Artisan::call('fcm:test-connection');

        // Assert: Command returns failure status and displays token requirement message
        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('❌ A valid FCM token is required for testing.', $output);
        $this->assertStringContainsString(
            'Please provide a token as an argument: php artisan fcm:test-connection YOUR_TOKEN_HERE',
            $output
        );
    }

    /**
     * Test that the command successfully sends a notification with valid token.
     */
    public function test_successfully_sends_notification_with_valid_token(): void
    {
        // Arrange: Configure mock channel to expect a send call
        $token = 'valid-test-token-123';

        $this->channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::type(Notification::class)
            )
            ->andReturnNull();

        $this->app->instance(FcmChannel::class, $this->channelMock);

        // Act: Run the command with a token and custom message options
        $exitCode = Artisan::call('fcm:test-connection', [
            'token' => $token,
            '--title' => 'Custom Test Title',
            '--body' => 'Custom test body content',
        ]);

        // Assert: Command returns success and displays confirmation message
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Initiating FCM connection test...', $output);
        $this->assertStringContainsString('✅ Test notification sent successfully to FCM!', $output);
    }

    /**
     * Test that the command handles exceptions from the FCM channel appropriately.
     */
    public function test_handles_channel_exception_gracefully(): void
    {
        // Arrange: Configure mock channel to throw an exception
        $token = 'invalid-test-token-123';
        $errorMessage = 'Firebase authentication failed: Invalid credentials';

        $this->channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::type(Notification::class)
            )
            ->andThrow(new Exception($errorMessage));

        $this->app->instance(FcmChannel::class, $this->channelMock);

        // Act: Run the command with a token
        $exitCode = Artisan::call('fcm:test-connection', ['token' => $token]);

        // Assert: Command returns failure and displays error with troubleshooting tips
        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('❌ Failed to send test notification: ' . $errorMessage, $output);
        $this->assertStringContainsString('Common issues:', $output);
        $this->assertStringContainsString('Verify your FCM credentials file path', $output);
        $this->assertStringContainsString('Check that the FCM token is valid', $output);
    }

    /**
     * Test that the command uses default title and body when not provided.
     */
    public function test_uses_default_values_when_options_omitted(): void
    {
        // Arrange: Configure mock to capture the notification for inspection
        $token = 'test-token-456';
        $capturedNotification = null;

        $this->channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::capture($capturedNotification)
            )
            ->andReturnUsing(function ($notifiable, $notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return null;
            });

        $this->app->instance(FcmChannel::class, $this->channelMock);

        // Act: Run the command with only a token (using default title/body)
        $exitCode = Artisan::call('fcm:test-connection', ['token' => $token]);

        // Assert: Command succeeds and a notification was created
        $this->assertEquals(0, $exitCode);
        $this->assertNotNull($capturedNotification);
        $this->assertInstanceOf(Notification::class, $capturedNotification);

        $output = Artisan::output();
        $this->assertStringContainsString('✅ Test notification sent successfully to FCM!', $output);
    }

    /**
     * Test that verbose mode displays detailed exception stack trace.
     */
    public function test_verbose_mode_displays_exception_stack_trace(): void
    {
        // Arrange: Configure mock channel to throw an exception
        $token = 'test-token-789';
        $errorMessage = 'Connection timeout';

        $this->channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::type(Notification::class)
            )
            ->andThrow(new Exception($errorMessage));

        $this->app->instance(FcmChannel::class, $this->channelMock);

        // Act: Run the command in verbose mode
        $exitCode = Artisan::call('fcm:test-connection', [
            'token' => $token,
            '--verbose' => true,
        ]);

        // Assert: Command shows exception details and stack trace
        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('❌ Failed to send test notification: ' . $errorMessage, $output);
        $this->assertStringContainsString('Exception details:', $output);

        // Verify stack trace contains typical PHP debug information
        $this->assertStringContainsString('#0 ', $output, 'Stack trace should contain frame numbers');
        $this->assertStringContainsString('#1 ', $output, 'Stack trace should contain multiple frames');
        $this->assertStringContainsString(
            'TestFcmConnectionCommandTest',
            $output,
            'Stack trace should reference the test class'
        );
        $this->assertStringContainsString(
            '->test_verbose_mode_displays_exception_stack_trace',
            $output,
            'Stack trace should reference this test method'
        );
    }

    /**
     * Test that the command handles empty token string appropriately.
     */
    public function test_fails_when_token_is_empty_string(): void
    {
        // Act: Run the command with an empty string as token
        $exitCode = Artisan::call('fcm:test-connection', ['token' => '']);

        // Assert: Command fails and displays token requirement message
        $this->assertEquals(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('❌ A valid FCM token is required for testing.', $output);
    }

    /**
     * Clean up after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

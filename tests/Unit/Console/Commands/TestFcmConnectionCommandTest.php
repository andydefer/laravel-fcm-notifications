<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Console\Commands;

use Andydefer\FcmNotifications\Channels\FcmChannel;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Mockery;

class TestFcmConnectionCommandTest extends TestCase
{
    /**
     * Test that command requires a token.
     */
    public function test_requires_token(): void
    {
        // Act
        $exitCode = Artisan::call('fcm:test-connection');

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Please provide a test FCM token', $output);
    }

    /**
     * Test successful connection.
     */
    public function test_successful_connection(): void
    {
        // Arrange
        $token = 'test-token-123';
        $channelMock = Mockery::mock(FcmChannel::class);

        $channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::type(Notification::class)
            )
            ->andReturnNull();

        // Remplacer l'instance dans le conteneur
        $this->app->instance(FcmChannel::class, $channelMock);

        // Act
        $exitCode = Artisan::call('fcm:test-connection', [
            'token' => $token,
            '--title' => 'Custom Title',
            '--body' => 'Custom Body',
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Testing FCM connection...', $output);
        $this->assertStringContainsString('✅ Test notification sent successfully!', $output);
    }

    /**
     * Test failed connection.
     */
    public function test_failed_connection(): void
    {
        // Arrange
        $token = 'test-token-123';
        $channelMock = Mockery::mock(FcmChannel::class);

        $channelMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::type('object'),
                Mockery::type(Notification::class)
            )
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(FcmChannel::class, $channelMock);

        // Act
        $exitCode = Artisan::call('fcm:test-connection', ['token' => $token]);

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('❌ Failed to send test notification: Connection failed', $output);
    }
}

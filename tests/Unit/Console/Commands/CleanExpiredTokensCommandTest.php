<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Console\Commands;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class CleanExpiredTokensCommandTest extends TestCase
{
    /**
     * Test that command cleans expired tokens.
     */
    public function test_cleans_expired_tokens(): void
    {
        // Arrange
        config(['fcm.tokens.expire_inactive_days' => 30]);

        $user = $this->createTestUser();

        // Create tokens with different last_used_at
        $user->fcmTokens()->createMany([
            ['token' => 'expired-1', 'last_used_at' => now()->subDays(40), 'is_valid' => true],
            ['token' => 'expired-2', 'last_used_at' => now()->subDays(35), 'is_valid' => true],
            ['token' => 'valid-1', 'last_used_at' => now()->subDays(10), 'is_valid' => true],
            ['token' => 'valid-2', 'last_used_at' => now(), 'is_valid' => true],
            ['token' => 'already-invalid', 'last_used_at' => now()->subDays(50), 'is_valid' => false],
        ]);

        // Act
        Artisan::call('fcm:clean-tokens');

        // Assert
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);
        $this->assertEquals('valid-1', $validTokens[0]->token);
        $this->assertEquals('valid-2', $validTokens[1]->token);

        $output = Artisan::output();
        $this->assertStringContainsString('Cleaned 2 expired FCM tokens', $output);
    }

    /**
     * Test that command respects custom days option.
     */
    public function test_respects_custom_days_option(): void
    {
        // Arrange
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'old-1', 'last_used_at' => now()->subDays(15), 'is_valid' => true],
            ['token' => 'old-2', 'last_used_at' => now()->subDays(12), 'is_valid' => true],
            ['token' => 'recent', 'last_used_at' => now()->subDays(5), 'is_valid' => true],
        ]);

        // Act
        Artisan::call('fcm:clean-tokens', ['--days' => 10]);

        // Assert
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(1, $validTokens);
        $this->assertEquals('recent', $validTokens[0]->token);

        $output = Artisan::output();
        $this->assertStringContainsString('Cleaned 2 expired FCM tokens', $output);
    }

    /**
     * Test dry run option.
     */
    public function test_dry_run_option(): void
    {
        // Arrange
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'expired', 'last_used_at' => now()->subDays(40), 'is_valid' => true],
            ['token' => 'valid', 'last_used_at' => now(), 'is_valid' => true],
        ]);

        // Act
        Artisan::call('fcm:clean-tokens', ['--dry-run' => true]);

        // Assert - tokens should still be valid
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);

        $output = Artisan::output();
        $this->assertStringContainsString('Found 1 expired tokens that would be cleaned', $output);
    }

    /**
     * Test that command handles no expired tokens.
     */
    public function test_handles_no_expired_tokens(): void
    {
        // Arrange
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'valid-1', 'last_used_at' => now(), 'is_valid' => true],
            ['token' => 'valid-2', 'last_used_at' => now()->subDays(5), 'is_valid' => true],
        ]);

        // Act
        Artisan::call('fcm:clean-tokens');

        // Assert
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);

        $output = Artisan::output();
        $this->assertStringContainsString('Cleaned 0 expired FCM tokens', $output);
    }
}

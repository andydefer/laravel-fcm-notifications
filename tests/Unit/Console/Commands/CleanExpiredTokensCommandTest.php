<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Console\Commands;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for the CleanExpiredTokensCommand.
 *
 * This test suite verifies the behavior of the token cleanup command:
 * - Cleaning expired tokens based on inactivity period
 * - Respecting custom days threshold from command options
 * - Dry-run mode that shows preview without making changes
 * - Handling scenarios with no expired tokens
 *
 * @package Andydefer\FcmNotifications\Tests\Unit\Console\Commands
 */
class CleanExpiredTokensCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the command correctly identifies and invalidates expired tokens.
     *
     * This test verifies that tokens not used within the configured inactivity
     * period are properly invalidated, while recently used tokens remain valid.
     *
     * @return void
     */
    public function test_invalidates_tokens_not_used_within_configured_period(): void
    {
        // Arrange: Create a mix of expired and valid tokens
        config(['fcm.tokens.expire_inactive_days' => 30]);

        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'expired-1', 'last_used_at' => now()->subDays(40), 'is_valid' => true],
            ['token' => 'expired-2', 'last_used_at' => now()->subDays(35), 'is_valid' => true],
            ['token' => 'valid-1', 'last_used_at' => now()->subDays(10), 'is_valid' => true],
            ['token' => 'valid-2', 'last_used_at' => now(), 'is_valid' => true],
            ['token' => 'already-invalid', 'last_used_at' => now()->subDays(50), 'is_valid' => false],
        ]);

        // Act: Execute the cleanup command
        Artisan::call('fcm:clean-tokens');

        // Assert: Only valid tokens remain and correct output is displayed
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);
        $this->assertEquals('valid-1', $validTokens[0]->token);
        $this->assertEquals('valid-2', $validTokens[1]->token);

        $output = Artisan::output();
        $this->assertStringContainsString(
            'Successfully invalidated 2 token(s) inactive for 30 day(s)',
            $output
        );
    }

    /**
     * Test that the command respects a custom days threshold from the --days option.
     *
     * This test verifies that when a custom inactivity period is provided via
     * the command line, it overrides the configuration value.
     *
     * @return void
     */
    public function test_uses_custom_inactivity_period_from_command_option(): void
    {
        // Arrange: Create tokens with various last_used_at dates
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'old-1', 'last_used_at' => now()->subDays(15), 'is_valid' => true],
            ['token' => 'old-2', 'last_used_at' => now()->subDays(12), 'is_valid' => true],
            ['token' => 'recent', 'last_used_at' => now()->subDays(5), 'is_valid' => true],
        ]);

        // Act: Run cleanup with custom 10-day threshold
        Artisan::call('fcm:clean-tokens', ['--days' => 10]);

        // Assert: Only tokens used within last 10 days remain valid
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(1, $validTokens);
        $this->assertEquals('recent', $validTokens[0]->token);

        $output = Artisan::output();
        $this->assertStringContainsString(
            'Successfully invalidated 2 token(s) inactive for 10 day(s)',
            $output
        );
    }

    /**
     * Test that dry-run mode shows preview without actually invalidating tokens.
     *
     * This test verifies that when the --dry-run option is used, the command
     * reports which tokens would be invalidated but leaves them valid in the
     * database.
     *
     * @return void
     */
    public function test_dry_run_mode_previews_changes_without_persisting_them(): void
    {
        // Arrange: Create a mix of expired and valid tokens
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'expired', 'last_used_at' => now()->subDays(40), 'is_valid' => true],
            ['token' => 'valid', 'last_used_at' => now(), 'is_valid' => true],
        ]);

        // Act: Run command in dry-run mode
        Artisan::call('fcm:clean-tokens', ['--dry-run' => true]);

        // Assert: All tokens remain valid in database
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);

        // Assert: Output shows what would be cleaned
        $output = Artisan::output();
        $this->assertStringContainsString(
            'Found 1 token(s) inactive for 30 day(s) that would be invalidated. No changes were made.',
            $output
        );
    }

    /**
     * Test that the command handles the case when no tokens are expired.
     *
     * This test verifies that when all tokens are within the inactivity period,
     * the command reports zero invalidations and makes no changes.
     *
     * @return void
     */
    public function test_reports_zero_invalidations_when_no_tokens_are_expired(): void
    {
        // Arrange: Create only recently used tokens
        $user = $this->createTestUser();

        $user->fcmTokens()->createMany([
            ['token' => 'valid-1', 'last_used_at' => now(), 'is_valid' => true],
            ['token' => 'valid-2', 'last_used_at' => now()->subDays(5), 'is_valid' => true],
        ]);

        // Act: Run cleanup command
        Artisan::call('fcm:clean-tokens');

        // Assert: All tokens remain valid
        $validTokens = FcmToken::valid()->get();
        $this->assertCount(2, $validTokens);

        // Assert: Output confirms no tokens were invalidated
        $output = Artisan::output();
        $this->assertStringContainsString(
            'Successfully invalidated 0 token(s) inactive for 30 day(s)',
            $output
        );
    }
}

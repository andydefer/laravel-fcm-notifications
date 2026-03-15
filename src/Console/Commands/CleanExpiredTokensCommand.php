<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Console\Commands;

use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Console command to clean up expired and inactive FCM tokens.
 *
 * This command identifies and invalidates FCM tokens that haven't been used
 * for a specified period. It helps maintain database cleanliness and prevents
 * sending notifications to stale tokens. The command supports both actual
 * cleanup operations and dry-run simulations for safety.
 *
 * @package Andydefer\FcmNotifications\Console\Commands
 */
class CleanExpiredTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fcm:clean-tokens
                            {--days= : Number of days of inactivity before a token is considered expired}
                            {--dry-run : Simulate the cleaning process without permanently invalidating tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove or invalidate FCM tokens that have not been used recently';

    /**
     * Execute the console command.
     *
     * This method calculates the cutoff date based on the configured or provided
     * inactivity period, identifies tokens that haven't been used since that date,
     * and either simulates the cleanup (dry-run) or performs the actual invalidation.
     *
     * @return int Command execution status (0 for success)
     */
    public function handle(): int
    {
        $inactivityDays = $this->getInactivityPeriod();
        $cutoffDate = $this->calculateCutoffDate($inactivityDays);

        $expiredTokensQuery = $this->buildExpiredTokensQuery($cutoffDate);
        $expiredTokensCount = $expiredTokensQuery->count();

        if ($this->isDryRun()) {
            $this->displayDryRunResults($expiredTokensCount, $inactivityDays);
            return Command::SUCCESS;
        }

        $invalidatedTokensCount = $this->invalidateExpiredTokens($expiredTokensQuery);
        $this->displayCleanupResults($invalidatedTokensCount, $inactivityDays);

        return Command::SUCCESS;
    }

    /**
     * Get the inactivity period from command option or configuration.
     *
     * @return int Number of days of inactivity before token expiration
     */
    private function getInactivityPeriod(): int
    {
        return (int) ($this->option('days') ?? Config::get('fcm.tokens.expire_inactive_days', 30));
    }

    /**
     * Calculate the cutoff date based on inactivity period.
     *
     * @param int $inactivityDays Number of days of allowed inactivity
     * @return Carbon Cutoff date for token activity
     */
    private function calculateCutoffDate(int $inactivityDays): Carbon
    {
        return Carbon::now()->subDays($inactivityDays);
    }

    /**
     * Build the query for finding expired tokens.
     *
     * @param Carbon $cutoffDate Tokens not used since this date are considered expired
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildExpiredTokensQuery(Carbon $cutoffDate): \Illuminate\Database\Eloquent\Builder
    {
        return FcmToken::valid()->notUsedSince($cutoffDate);
    }

    /**
     * Determine if the command is running in dry-run mode.
     *
     * @return bool True if dry-run mode is enabled
     */
    private function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    /**
     * Display results for dry-run mode.
     *
     * @param int $expiredTokensCount Number of tokens that would be cleaned
     * @param int $inactivityDays The inactivity period used
     * @return void
     */
    private function displayDryRunResults(int $expiredTokensCount, int $inactivityDays): void
    {
        $message = sprintf(
            'Found %d token(s) inactive for %d day(s) that would be invalidated. No changes were made.',
            $expiredTokensCount,
            $inactivityDays
        );

        $this->info($message);
    }

    /**
     * Invalidate expired tokens.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query Query for expired tokens
     * @return int Number of tokens invalidated
     */
    private function invalidateExpiredTokens(\Illuminate\Database\Eloquent\Builder $query): int
    {
        return $query->update(['is_valid' => false]);
    }

    /**
     * Display cleanup operation results.
     *
     * @param int $invalidatedTokensCount Number of tokens successfully invalidated
     * @param int $inactivityDays The inactivity period used
     * @return void
     */
    private function displayCleanupResults(int $invalidatedTokensCount, int $inactivityDays): void
    {
        $message = sprintf(
            'Successfully invalidated %d token(s) inactive for %d day(s).',
            $invalidatedTokensCount,
            $inactivityDays
        );

        $this->info($message);
    }
}

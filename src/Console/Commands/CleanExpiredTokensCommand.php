<?php

namespace Andydefer\FcmNotifications\Console\Commands;

use Andydefer\FcmNotifications\Models\FcmToken;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class CleanExpiredTokensCommand extends Command
{
    protected $signature = 'fcm:clean-tokens
                            {--days= : Number of days of inactivity before expiration}
                            {--dry-run : Simulate the cleaning without actually deleting}';

    protected $description = 'Clean expired FCM tokens that have not been used recently';

    public function handle(): int
    {
        $days = $this->option('days') ?? Config::get('fcm.tokens.expire_inactive_days', 30);
        $cutoffDate = Carbon::now()->subDays($days);

        $query = FcmToken::valid()
            ->notUsedSince($cutoffDate);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("Found {$count} expired tokens that would be cleaned.");
            return Command::SUCCESS;
        }

        $deleted = $query->update(['is_valid' => false]);

        $this->info("Cleaned {$deleted} expired FCM tokens.");

        return Command::SUCCESS;
    }
}

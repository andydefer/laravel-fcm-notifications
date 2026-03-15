<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for creating the test_users table used in package testing.
 *
 * This migration is only used during package development and testing phases.
 * It creates a simple users table that can be used to test FCM token relationships
 * and notification functionality without requiring the application's actual users table.
 *
 * @package Andydefer\FcmNotifications\Database\Migrations
 */
return new class extends Migration
{
    /**
     * The name of the test users table.
     */
    private const TEST_USERS_TABLE = 'test_users';

    /**
     * Run the migrations.
     *
     * Creates the test_users table if it doesn't already exist.
     * The table includes basic fields needed for testing polymorphic relationships
     * with FCM tokens and notification delivery.
     *
     * @return void
     */
    public function up(): void
    {
        if ($this->testUsersTableExists()) {
            return;
        }

        $this->createTestUsersTable();
    }

    /**
     * Reverse the migrations.
     *
     * Drops the test_users table if it exists.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(self::TEST_USERS_TABLE);
    }

    /**
     * Check if the test users table already exists.
     *
     * @return bool True if the table exists, false otherwise
     */
    private function testUsersTableExists(): bool
    {
        return Schema::hasTable(self::TEST_USERS_TABLE);
    }

    /**
     * Create the test users table with all required columns.
     *
     * The table structure mimics a standard Laravel users table with:
     * - Auto-incrementing ID
     * - Name and email fields
     * - Email verification timestamp
     * - Standard timestamps
     *
     * @return void
     */
    private function createTestUsersTable(): void
    {
        Schema::create(self::TEST_USERS_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'name');
            $table->string(column: 'email')->unique();
            $table->timestamp(column: 'email_verified_at')->nullable();
            $table->timestamps();
        });
    }
};

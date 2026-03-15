<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name of the FCM tokens table.
     */
    private const TABLE_NAME = 'fcm_tokens';

    /**
     * Create the fcm_tokens table for storing Firebase Cloud Messaging device tokens.
     *
     * This table stores all FCM tokens associated with notifiable models (users, admins, etc.)
     * through a polymorphic relationship. It tracks token validity, metadata, and usage
     * timestamps for automatic cleanup of stale tokens.
     *
     * The "primary" token concept is now determined dynamically based on the most recent
     * last_used_at timestamp, eliminating the need for a dedicated database field.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(self::TABLE_NAME, function (Blueprint $table): void {
            $table->id();

            // Polymorphic relationship to any notifiable model (User, Admin, Device, etc.)
            // This automatically creates indexes on tokenable_type and tokenable_id
            $table->morphs('tokenable');

            // FCM device token - 500 chars supports Android, iOS, and Web tokens
            // The token length can vary, but 500 is safe for all platforms
            $table->string('token', 500);

            // Track token validity - invalid tokens are kept for historical/debugging purposes
            // but are excluded from notification sending
            $table->boolean('is_valid')->default(true);

            // Flexible storage for device information: platform, version, model, app version, etc.
            // Stored as JSON for maximum flexibility
            $table->json('metadata')->nullable();

            // Track last usage for cleaning inactive tokens and determining primary status
            // The most recent valid token is considered the "primary" token
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // Prevent duplicate tokens for the same notifiable entity
            // This ensures we don't store the same device token multiple times
            $table->unique(
                columns: ['tokenable_type', 'tokenable_id', 'token'],
                name: 'fcm_tokens_tokenable_token_unique'
            );

            // Index for efficiently finding valid tokens for a notifiable
            // Used extensively in getFcmTokens() and hasFcmTokens()
            // Note: morphs() already creates an index on (tokenable_type, tokenable_id)
            // so we only need to add is_valid to make it a composite index
            $table->index(
                columns: ['tokenable_type', 'tokenable_id', 'is_valid'],
                name: 'fcm_tokens_tokenable_valid_index'
            );

            // Index for finding stale tokens for cleanup operations
            // Used by the CleanExpiredTokensCommand
            $table->index(
                columns: ['last_used_at'],
                name: 'fcm_tokens_last_used_index'
            );
        });
    }

    /**
     * Drop the fcm_tokens table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists(self::TABLE_NAME);
    }
};

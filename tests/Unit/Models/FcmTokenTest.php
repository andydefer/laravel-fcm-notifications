<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Unit\Models;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;
use Illuminate\Support\Carbon;

class FcmTokenTest extends TestCase
{
    /**
     * Test that a token can be created.
     */
    public function test_can_create_token(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $token = $user->fcmTokens()->create([
            'token' => 'test-token-123',
            'is_valid' => true,
            'is_primary' => true,
            'metadata' => ['device' => 'iPhone 15'],
            'last_used_at' => now(),
        ]);

        // Assert
        $this->assertInstanceOf(FcmToken::class, $token);
        $this->assertEquals('test-token-123', $token->token);
        $this->assertTrue($token->is_valid);
        $this->assertTrue($token->is_primary);
        $this->assertEquals(['device' => 'iPhone 15'], $token->metadata);
    }

    /**
     * Test that last_used_at is automatically set on creation.
     */
    public function test_last_used_at_is_automatically_set(): void
    {
        // Arrange
        $user = $this->createTestUser();

        // Act
        $token = $user->fcmTokens()->create([
            'token' => 'test-token-123',
            'is_valid' => true,
        ]);

        // Assert
        $this->assertNotNull($token->last_used_at);
        $this->assertInstanceOf(Carbon::class, $token->last_used_at);
    }

    /**
     * Test valid scope.
     */
    public function test_valid_scope(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->fcmTokens()->createMany([
            ['token' => 'valid-1', 'is_valid' => true],
            ['token' => 'valid-2', 'is_valid' => true],
            ['token' => 'invalid-1', 'is_valid' => false],
        ]);

        // Act
        $validTokens = $user->fcmTokens()->valid()->get();

        // Assert
        $this->assertCount(2, $validTokens);
        $this->assertEquals('valid-1', $validTokens[0]->token);
        $this->assertEquals('valid-2', $validTokens[1]->token);
    }

    /**
     * Test primary scope.
     */
    public function test_primary_scope(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->fcmTokens()->createMany([
            ['token' => 'primary-1', 'is_primary' => true],
            ['token' => 'secondary-1', 'is_primary' => false],
            ['token' => 'secondary-2', 'is_primary' => false],
        ]);

        // Act
        $primaryTokens = $user->fcmTokens()->primary()->get();

        // Assert
        $this->assertCount(1, $primaryTokens);
        $this->assertEquals('primary-1', $primaryTokens[0]->token);
        $this->assertTrue($primaryTokens[0]->is_primary);
    }

    /**
     * Test notUsedSince scope.
     */
    public function test_not_used_since_scope(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $user->fcmTokens()->createMany([
            ['token' => 'old', 'last_used_at' => now()->subDays(40)],
            ['token' => 'recent', 'last_used_at' => now()->subDays(10)],
            ['token' => 'today', 'last_used_at' => now()],
        ]);

        // Act
        $cutoffDate = now()->subDays(30);
        $oldTokens = $user->fcmTokens()->notUsedSince($cutoffDate)->get();

        // Assert
        $this->assertCount(1, $oldTokens);
        $this->assertEquals('old', $oldTokens[0]->token);
    }

    /**
     * Test tokenable relationship.
     */
    public function test_tokenable_relationship(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $token = $user->fcmTokens()->create(['token' => 'test-token']);

        // Act
        $tokenable = $token->tokenable;

        // Assert
        $this->assertInstanceOf(TestUser::class, $tokenable);
        $this->assertEquals($user->id, $tokenable->id);
    }

    /**
     * Test that token can be invalidated.
     */
    public function test_can_invalidate_token(): void
    {
        // Arrange
        $user = $this->createTestUser();
        $token = $user->fcmTokens()->create([
            'token' => 'test-token',
            'is_valid' => true,
        ]);

        // Act
        $token->update(['is_valid' => false]);
        $refreshedToken = $token->fresh();

        // Assert
        $this->assertFalse($refreshedToken->is_valid);
    }
}

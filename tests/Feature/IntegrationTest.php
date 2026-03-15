<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests\Feature;

use Andydefer\FcmNotifications\Models\FcmToken;
use Andydefer\FcmNotifications\Tests\Fixtures\TestFcmNotification;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Andydefer\FcmNotifications\Tests\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * Test full lifecycle of FCM tokens.
     */
    public function test_full_token_lifecycle(): void
    {
        // ✅ Modifier pour que ce test n'utilise pas réellement Firebase
        // On va mocker le canal FCM pour éviter la vraie connexion

        // 1. User registers tokens
        $user = $this->createTestUser();
        $user->registerFcmToken('token-1', metadata: ['device' => 'iPhone']);
        $user->registerFcmToken('token-2', isPrimary: true, metadata: ['device' => 'iPad']);

        $this->assertCount(2, $user->getFcmTokens());
        $this->assertEquals('token-2', $user->getPrimaryFcmToken());

        // 2. Send notification (sans réellement envoyer)
        $notification = new TestFcmNotification('Test', 'Message');

        // On ne notifie pas réellement pour éviter la connexion Firebase
        // $user->notify($notification);

        // 3. Token usage updates last_used_at
        $token = $user->fcmTokens()->where('token', 'token-1')->first();
        $token->update(['last_used_at' => now()]);

        // 4. Token becomes invalid
        $user->invalidateFcmToken('token-1');
        $this->assertCount(1, $user->getFcmTokens());
        $this->assertEquals(['token-2'], $user->getFcmTokens());

        // 5. User registers new token, old primary is automatically unset
        $user->registerFcmToken('token-3', isPrimary: true);
        $this->assertCount(2, $user->getFcmTokens());
        $this->assertEquals('token-3', $user->getPrimaryFcmToken());

        $primaryTokens = $user->fcmTokens()->primary()->get();
        $this->assertCount(1, $primaryTokens);
        $this->assertEquals('token-3', $primaryTokens[0]->token);

        // 6. User is deleted - tokens are also deleted
        $userId = $user->id;
        $user->delete();

        $remainingTokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $userId)
            ->count();
        $this->assertEquals(0, $remainingTokens);
    }

    /**
     * Test multiple users with multiple tokens.
     */
    public function test_multiple_users_multiple_tokens(): void
    {
        // ✅ Correction : emails uniques
        $user1 = $this->createTestUser([
            'name' => 'User 1',
            'email' => 'user1@example.com'  // Email unique
        ]);
        $user1->registerFcmToken('user1-token1');
        $user1->registerFcmToken('user1-token2');

        $user2 = $this->createTestUser([
            'name' => 'User 2',
            'email' => 'user2@example.com'  // Email unique
        ]);
        $user2->registerFcmToken('user2-token1');

        $user3 = $this->createTestUser([
            'name' => 'User 3',
            'email' => 'user3@example.com'  // Email unique
        ]); // No tokens

        // Assert counts
        $this->assertCount(2, $user1->getFcmTokens());
        $this->assertCount(1, $user2->getFcmTokens());
        $this->assertCount(0, $user3->getFcmTokens());

        $this->assertTrue($user1->hasFcmTokens());
        $this->assertTrue($user2->hasFcmTokens());
        $this->assertFalse($user3->hasFcmTokens());

        // Total tokens in database
        $totalTokens = FcmToken::count();
        $this->assertEquals(3, $totalTokens);

        // Filter by user
        $user1Tokens = FcmToken::where('tokenable_type', TestUser::class)
            ->where('tokenable_id', $user1->id)
            ->count();
        $this->assertEquals(2, $user1Tokens);
    }

    /**
     * Test token limits per user.
     */
    public function test_token_limits_per_user(): void
    {
        config(['fcm.tokens.max_per_notifiable' => 3]);

        $user = $this->createTestUser();

        // Register up to limit
        $user->registerFcmToken('token-1');
        $user->registerFcmToken('token-2');
        $user->registerFcmToken('token-3');

        $this->assertCount(3, $user->getFcmTokens());

        // This should replace the oldest token
        sleep(1); // Ensure different timestamps
        $user->registerFcmToken('token-4');

        $this->assertCount(3, $user->getFcmTokens());
        $this->assertContains('token-4', $user->getFcmTokens());
        $this->assertNotContains('token-1', $user->getFcmTokens());
    }
}

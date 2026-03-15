<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests;

use Andydefer\FcmNotifications\FcmNotificationServiceProvider;
use Andydefer\FcmNotifications\Tests\Fixtures\TestNotifiable;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use LazilyRefreshDatabase;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestEnvironment();
    }

    /**
     * Get package service providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            FcmNotificationServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // ✅ Correction : Fixtures avec F majuscule
        $app['config']->set('fcm.credentials', __DIR__ . '/Fixtures/firebase-credentials.json');

        $app['config']->set('fcm.tokens.expire_inactive_days', 30);
        $app['config']->set('fcm.tokens.max_per_notifiable', 10);
        $app['config']->set('fcm.logging.enabled', false);
        $app['config']->set('fcm.queue.enabled', false);
        $app['config']->set('fcm.channel_name', 'fcm');
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        // ✅ Charger la migration du package
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // ✅ Charger les migrations de test
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Configure test environment.
     */
    protected function configureTestEnvironment(): void
    {
        Config::set('fcm.logging.enabled', false);
        Config::set('fcm.queue.enabled', false);
    }

    /**
     * Create a test user with FCM capabilities.
     */
    protected function createTestUser(array $attributes = []): TestUser
    {
        return TestUser::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }

    /**
     * Create a test notifiable object.
     */
    protected function createTestNotifiable(array $tokens = []): TestNotifiable
    {
        return new TestNotifiable($tokens);
    }

    /**
     * Create a mock FCM token.
     */
    protected function createMockToken(string $token = 'test-token-123'): string
    {
        return $token;
    }
}

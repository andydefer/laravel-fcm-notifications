<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Tests;

use Andydefer\FcmNotifications\FcmNotificationServiceProvider;
use Andydefer\FcmNotifications\Tests\Fixtures\TestNotifiable;
use Andydefer\FcmNotifications\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case for the FCM Notifications package.
 *
 * This abstract class provides the foundation for all tests in the package,
 * setting up the test environment, database configuration, and common helper
 * methods for creating test fixtures and mock objects.
 *
 * @package Andydefer\FcmNotifications\Tests
 */
abstract class TestCase extends OrchestraTestCase
{
    use LazilyRefreshDatabase;

    /**
     * The path to the test fixtures directory.
     */
    private const FIXTURES_PATH = __DIR__ . '/Fixtures';

    /**
     * The name of the Firebase credentials file for testing.
     */
    private const FIREBASE_CREDENTIALS_FILE = 'firebase-credentials.json';

    /**
     * Default test user attributes.
     */
    private const DEFAULT_USER_ATTRIBUTES = [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ];

    /**
     * Set up the test environment before each test.
     *
     * This method is called before every test method to ensure a clean
     * and properly configured testing environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->disableLoggingAndQueue();
    }

    /**
     * Get the package service providers that should be loaded.
     *
     * This method registers the FCM Notification Service Provider with
     * the testbench application.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            FcmNotificationServiceProvider::class,
        ];
    }

    /**
     * Define the test environment configuration.
     *
     * This method sets up the database configuration and package-specific
     * settings for the testing environment.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        $this->configureTestDatabase($app);
        $this->configurePackageSettings($app);
    }

    /**
     * Configure the test database connection.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    private function configureTestDatabase($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Configure package-specific settings for testing.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    private function configurePackageSettings($app): void
    {
        $app['config']->set('fcm.credentials', $this->getFirebaseCredentialsPath());
        $app['config']->set('fcm.tokens.expire_inactive_days', 30);
        $app['config']->set('fcm.tokens.max_per_notifiable', 10);
        $app['config']->set('fcm.logging.enabled', false);
        $app['config']->set('fcm.queue.enabled', false);
        $app['config']->set('fcm.channel_name', 'fcm');
    }

    /**
     * Define the database migrations for testing.
     *
     * This method loads both the package migrations and the test-specific
     * migrations required for the test suite.
     *
     * @return void
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadPackageMigrations();
        $this->loadTestMigrations();
    }

    /**
     * Load the package's migration files.
     *
     * @return void
     */
    private function loadPackageMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Load the test-specific migration files.
     *
     * @return void
     */
    private function loadTestMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Disable logging and queue features for testing.
     *
     * This ensures tests run faster and don't produce side effects
     * from logging or queued jobs.
     *
     * @return void
     */
    private function disableLoggingAndQueue(): void
    {
        Config::set('fcm.logging.enabled', false);
        Config::set('fcm.queue.enabled', false);
    }

    /**
     * Get the path to the Firebase credentials file for testing.
     *
     * @return string
     */
    private function getFirebaseCredentialsPath(): string
    {
        return self::FIXTURES_PATH . '/' . self::FIREBASE_CREDENTIALS_FILE;
    }

    /**
     * Create a test user with FCM capabilities.
     *
     * This factory method creates a TestUser instance with default or
     * custom attributes for use in tests.
     *
     * @param array<string, mixed> $attributes Custom attributes to override defaults
     * @return TestUser
     *
     * @example
     * ```php
     * $user = $this->createTestUser(['email' => 'custom@example.com']);
     * ```
     */
    protected function createTestUser(array $attributes = []): TestUser
    {
        $userAttributes = array_merge(self::DEFAULT_USER_ATTRIBUTES, $attributes);

        return TestUser::create($userAttributes);
    }

    /**
     * Create a test notifiable object.
     *
     * This factory method creates a TestNotifiable instance that implements
     * the HasFcmToken interface, useful for testing scenarios where a
     * database-backed model isn't needed.
     *
     * @param array<string> $tokens Initial FCM tokens to associate with the notifiable
     * @return TestNotifiable
     *
     * @example
     * ```php
     * $notifiable = $this->createTestNotifiable(['token-1', 'token-2']);
     * ```
     */
    protected function createTestNotifiable(array $tokens = []): TestNotifiable
    {
        return new TestNotifiable($tokens);
    }

    /**
     * Create a mock FCM token string for testing.
     *
     * This helper method generates consistent token strings for use in tests,
     * ensuring test isolation and reproducibility.
     *
     * @param string $token Optional custom token value
     * @return string
     *
     * @example
     * ```php
     * $token = $this->createMockToken(); // Returns 'test-token-123'
     * $customToken = $this->createMockToken('custom-device'); // Returns 'custom-device'
     * ```
     */
    protected function createMockToken(string $token = 'test-token-123'): string
    {
        return $token;
    }
}

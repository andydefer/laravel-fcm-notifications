<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications;

use Andydefer\FcmNotifications\Channels\FcmChannel;
use Andydefer\FcmNotifications\Console\Commands\CleanExpiredTokensCommand;
use Andydefer\FcmNotifications\Console\Commands\TestFcmConnectionCommand;
use Andydefer\PushNotifier\Core\NotificationFactory;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Laravel service provider for Firebase Cloud Messaging (FCM) notifications.
 *
 * This service provider handles the registration and bootstrapping of all FCM
 * notification package components including configuration, migrations,
 * translations, console commands, and the notification channel itself.
 *
 * @package Andydefer\FcmNotifications
 */
class FcmNotificationServiceProvider extends ServiceProvider
{
    /**
     * The package's configuration file name.
     */
    private const CONFIG_FILE = 'fcm.php';

    /**
     * The package's translation namespace.
     */
    private const TRANSLATION_NAMESPACE = 'fcm';

    /**
     * The default channel name for FCM notifications.
     */
    private const DEFAULT_CHANNEL_NAME = 'fcm';

    /**
     * Register any application services.
     *
     * This method merges the package configuration and registers the required
     * services in the container:
     * - NotificationFactory for creating Firebase services
     * - FcmChannel as a singleton for sending notifications
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergePackageConfiguration();

        $this->registerNotificationFactory();
        $this->registerFcmChannel();
    }

    /**
     * Bootstrap any application services.
     *
     * This method registers all package resources and functionality:
     * - Publishes configuration, migrations, and translations
     * - Registers console commands
     * - Registers the FCM notification channel
     * - Loads translation files
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishableResources();
        $this->registerConsoleCommands();
        $this->registerNotificationChannel();
        $this->loadTranslations();
    }

    /**
     * Merge the package configuration with the application's configuration.
     *
     * @return void
     */
    private function mergePackageConfiguration(): void
    {
        $this->mergeConfigFrom(
            path: $this->getPackageConfigPath(),
            key: self::CONFIG_FILE
        );
    }

    /**
     * Register the NotificationFactory as a singleton in the container.
     *
     * @return void
     */
    private function registerNotificationFactory(): void
    {
        $this->app->singleton(NotificationFactory::class, function ($app) {
            return new NotificationFactory();
        });
    }

    /**
     * Register the FcmChannel as a singleton in the container.
     *
     * @return void
     */
    private function registerFcmChannel(): void
    {
        $this->app->singleton(FcmChannel::class, function ($app) {
            return new FcmChannel(
                notificationFactory: $app->make(NotificationFactory::class),
                credentialsPath: Config::get('fcm.credentials')
            );
        });
    }

    /**
     * Register all publishable resources for the package.
     *
     * @return void
     */
    private function registerPublishableResources(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishesConfiguration();
        $this->publishesPackageMigrations();
        $this->publishesTranslations();
    }

    /**
     * Publish the package configuration file.
     *
     * @return void
     */
    private function publishesConfiguration(): void
    {
        $this->publishes(
            paths: [
                $this->getPackageConfigPath() => config_path(self::CONFIG_FILE),
            ],
            groups: 'fcm-config'
        );
    }

    /**
     * Publish the package migration files.
     *
     * @return void
     *
     * @throws RuntimeException If no migration file is found
     */
    private function publishesPackageMigrations(): void
    {
        $migrationPath = $this->discoverMigrationFile();

        if ($migrationPath === null) {
            throw new RuntimeException(
                'FCM migration file not found. Please ensure the package is properly installed.'
            );
        }

        $this->publishes(
            paths: [
                $migrationPath => $this->generateMigrationDestination(),
            ],
            groups: 'fcm-migrations'
        );
    }

    /**
     * Publish the package translation files.
     *
     * @return void
     */
    private function publishesTranslations(): void
    {
        $this->publishes(
            paths: [
                $this->getPackageLangPath() => $this->app->langPath('vendor/' . self::TRANSLATION_NAMESPACE),
            ],
            groups: 'fcm-translations'
        );
    }

    /**
     * Register the package's console commands.
     *
     * @return void
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanExpiredTokensCommand::class,
                TestFcmConnectionCommand::class,
            ]);
        }
    }

    /**
     * Register the FCM channel with Laravel's notification system.
     *
     * @return void
     */
    private function registerNotificationChannel(): void
    {
        $channelName = Config::get('fcm.channel_name', self::DEFAULT_CHANNEL_NAME);

        $this->app->make(ChannelManager::class)->extend(
            driver: $channelName,
            callback: function ($app) {
                return $app->make(FcmChannel::class);
            }
        );
    }

    /**
     * Load the package's translation files.
     *
     * @return void
     */
    private function loadTranslations(): void
    {
        $this->loadTranslationsFrom(
            path: $this->getPackageLangPath(),
            namespace: self::TRANSLATION_NAMESPACE
        );
    }

    /**
     * Get the path to the package configuration file.
     *
     * @return string
     */
    private function getPackageConfigPath(): string
    {
        return __DIR__ . '/../config/' . self::CONFIG_FILE;
    }

    /**
     * Get the path to the package language files.
     *
     * @return string
     */
    private function getPackageLangPath(): string
    {
        return __DIR__ . '/../resources/lang';
    }

    /**
     * Discover the migration file in the package.
     *
     * @return string|null The migration file path or null if not found
     */
    private function discoverMigrationFile(): ?string
    {
        $migrationFiles = glob(__DIR__ . '/../database/migrations/*_create_fcm_tokens_table.php');

        return $migrationFiles[0] ?? null;
    }

    /**
     * Generate the destination path for the migration file.
     *
     * @return string
     */
    private function generateMigrationDestination(): string
    {
        $timestamp = date('Y_m_d_His', time());

        return database_path("migrations/{$timestamp}_create_fcm_tokens_table.php");
    }
}

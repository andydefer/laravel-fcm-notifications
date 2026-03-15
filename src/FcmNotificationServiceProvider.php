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

class FcmNotificationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/fcm.php',
            'fcm'
        );

        $this->app->singleton(NotificationFactory::class, function ($app) {
            return new NotificationFactory();
        });

        $this->app->singleton(FcmChannel::class, function ($app) {
            return new FcmChannel(
                $app->make(NotificationFactory::class),
                Config::get('fcm.credentials')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerChannel();
        $this->registerTranslations();
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // ✅ Chemin corrigé pour la publication
            $this->publishes([
                __DIR__ . '/../config/fcm.php' => config_path('fcm.php'),
            ], 'fcm-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_fcm_tokens_table.php' => database_path(
                    'migrations/' . date('Y_m_d_His', time()) . '_create_fcm_tokens_table.php'
                ),
            ], 'fcm-migrations');

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/fcm'),
            ], 'fcm-translations');
        }
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
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
     */
    protected function registerChannel(): void
    {
        $this->app->make(ChannelManager::class)->extend(
            Config::get('fcm.channel_name', 'fcm'),
            function ($app) {
                return $app->make(FcmChannel::class);
            }
        );
    }

    /**
     * Register the package's translation files.
     */
    protected function registerTranslations(): void
    {
        // ✅ Chemin corrigé pour les traductions
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'fcm');
    }
}

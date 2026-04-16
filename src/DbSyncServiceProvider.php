<?php

namespace Nodir\DbSync;

use Illuminate\Support\ServiceProvider;
use Nodir\DbSync\Commands\SyncProdDatabase;

class DbSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/db-sync.php',
            'db-sync'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Config faylni publish qilish imkoniyati
            $this->publishes([
                __DIR__ . '/../config/db-sync.php' => config_path('db-sync.php'),
            ], 'db-sync-config');

            // Command'ni ro'yxatdan o'tkazish
            $this->commands([
                SyncProdDatabase::class,
            ]);
        }
    }
}
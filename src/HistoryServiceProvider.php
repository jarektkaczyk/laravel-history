<?php

namespace Sofa\History;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Sofa\History\Commands\HistoryCommand;

class HistoryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishAssets();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'history');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/history.php', 'history');
        $this->registerHistoryModelMacro();

        Event::listen('eloquent.*', HistoryListener::class);
    }

    private function registerHistoryModelMacro()
    {
        Builder::macro('recreate', function (...$args) {
            /** @var Builder $this */
            return History::recreate($this->getModel()::class, ...$args);
        });
    }

    private function publishAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/history.php' => config_path('history.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => base_path('resources/views/vendor/history'),
        ], 'views');

        $migrationFileName = 'create_history_table.php';
        if (!$this->migrationFileExists($migrationFileName)) {
            $this->publishes([
                __DIR__ . "/../database/migrations/{$migrationFileName}.stub" => database_path('migrations/' . date('Y_m_d_His', time()) . '_' . $migrationFileName),
            ], 'migrations');
        }

        $this->commands([
            HistoryCommand::class,
        ]);
    }

    private function migrationFileExists(string $migrationFileName): bool
    {
        $len = strlen($migrationFileName);
        foreach (glob(database_path("migrations/*.php")) as $filename) {
            if ((substr($filename, -$len) === $migrationFileName)) {
                return true;
            }
        }

        return false;
    }
}

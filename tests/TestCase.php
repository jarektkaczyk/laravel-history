<?php

namespace Sofa\History\Tests;

use CreateHistoryTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Sofa\History\HistoryServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Sofa\\History\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function defineDatabaseMigrations()
    {
        $this->artisan('migrate')->run();

        include_once __DIR__.'/../database/migrations/create_history_table.php.stub';
        (new CreateHistoryTable())->up();

        $this->beforeApplicationDestroyed(function () {
            (new CreateHistoryTable())->down();
            $this->artisan('migrate:rollback')->run();
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            HistoryServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // In order to test HasXxxThrough relations we need driver supporting WHERE IN on json column
//        $app['config']->set('database.default', 'mysql');
//        $app['config']->set('database.connections.mysql', [
//            'driver' => 'mysql',
//            'host' => '127.0.0.1',
//            'port' => '3306',
//            'database' => 'history_tests',
//            'username' => 'root',
//            'password' => '',
//            'unix_socket' => '',
//            'charset' => 'utf8mb4',
//            'collation' => 'utf8mb4_unicode_ci',
//            'prefix' => '',
//            'prefix_indexes' => true,
//            'strict' => true,
//        ]);
    }
}

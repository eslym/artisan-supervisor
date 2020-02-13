<?php


namespace Eslym\Laravel\Supervisor;


use Eslym\Laravel\Supervisor\Commands\SuperviseCommand;
use Eslym\Laravel\Supervisor\Commands\Windows\PipeCommand;
use Eslym\Laravel\Supervisor\Commands\Windows\SuperviseCommand as WindowsSuperviseCommand;
use Illuminate\Support\ServiceProvider;

class SupervisorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->publishes([
            __DIR__ . '/../config/supervisor.php' => $this->app->configPath('supervisor.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/supervisor.php', 'supervisor');

        if (DIRECTORY_SEPARATOR === '\\') {
            $this->app->singleton(SuperviseCommand::class, WindowsSuperviseCommand::class);
            $this->app->singleton(PipeCommand::class);
            $this->app->alias(PipeCommand::class, 'command.supervisor.pipe');
            $this->commands(['command.supervisor.pipe']);
        } else {
            $this->app->singleton(SuperviseCommand::class);
        }

        $this->app->alias(SuperviseCommand::class, 'command.supervisor.run');
        $this->commands(['command.supervisor.run']);
    }
}
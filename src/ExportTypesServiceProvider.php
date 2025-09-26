<?php

namespace SynergiTech\ExportTypes;

use Illuminate\Support\ServiceProvider;
use SynergiTech\ExportTypes\Commands\GenerateInterfaceUnionsCommand;

class ExportTypesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                    GenerateInterfaceUnionsCommand::class,
                ]
            );
        }
    }
}

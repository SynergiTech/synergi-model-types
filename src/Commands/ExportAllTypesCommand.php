<?php

namespace SynergiTech\ExportTypes\Commands;

use Illuminate\Console\Command;

class ExportAllTypesCommand extends Command
{
    protected $signature = 'synergi-types:all
         {--form-input=app/Http/Requests}
         {--form-output=resources/js/form-requests}
         {--model-input=app/Models}
         {--model-output=resources/js/models}
         {--format}
         {--prettier=}';

    protected $description = 'Export all types.';

    public function handle()
    {
        $this->call(GenerateFormRequestsCommand::class, [
            '--input' => $this->option('form-input'),
            '--output' => $this->option('form-output'),
            '--format' => $this->option('format'),
            '--prettier' => $this->option('prettier'),
        ]);

        $this->call(GenerateInterfaceUnionsCommand::class, [
            '--input' => $this->option('model-input'),
            '--output' => $this->option('model-output'),
            '--format' => $this->option('format'),
            '--prettier' => $this->option('prettier'),
        ]);
    }
}

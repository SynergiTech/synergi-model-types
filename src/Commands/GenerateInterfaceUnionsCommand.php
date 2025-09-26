<?php

namespace SynergiTech\ExportTypes\Commands;
 
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GenerateInterfaceUnionsCommand extends BaseCommand
{
    protected $signature = 'export-interface-unions:generate
        {--input=app/Models}
        {--output=resources/js/models}
        {--format}
        {--prettier=}';

    protected $description = 'Export models that implement an interface to your frontend.';

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct($files);
    }
 
    protected function process(): void
    {
        $path = $this->option('output');

        $interfaces = $this->readInterfaces(
            path: $this->base(),
            interfaces: config('export-types.interfaces', [])
        );

        $tsContent = collect($interfaces)
            ->map(function ($classes, $interface) {
                $shortInterface = Str::afterLast($interface, '\\');
                $classNames = collect($classes)
                    ->map(fn ($class) => '"'. addslashes($class) . '"')
                    ->implode(' | ');

                return "export type {$shortInterface} = {$classNames};";
            })
            ->implode("\n\n");

        $tsContent .= "\n\nexport {};\n";

        $this->files->put($this->tsFilePath($path), $tsContent);
    }

    protected function readInterfaces(string $path, array $interfaces)
    {
        /**
          * @var iterable<string,\SplFileInfo>
          */
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        $rootNamespace = $this->determineRootNamespace($interfaces);

        return collect($interfaces)
            ->mapWithKeys(fn ($interface) => [
                $this->chopStart($interface, $rootNamespace) => collect($paths)
                    ->reject(fn ($i) => $i->isDir() || str_ends_with($i->getRealPath(), '/..'))
                    ->map(fn ($item) => $this->fqcnFromPath($item->getRealPath()))
                    ->filter(fn ($i) => is_subclass_of($i, $interface))
                    ->values()
                    ->toArray()
            ])
            ->toArray();
    }
}

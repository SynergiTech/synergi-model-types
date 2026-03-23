<?php

namespace SynergiTech\ExportTypes\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GenerateInterfaceUnionsCommand extends BaseCommand
{
    protected $signature = 'synergi-types:interface-unions
        {--input=app/Models}
        {--output=resources/js/models}
        {--format}
        {--prettier=}';

    protected $description = 'Export models that implement an interface as TypeScript union types.';

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
            interfaces: config('synergi-types.interfaces', [])
        );

        // All interface unions should be exported under App.Interfaces
        $tsContent = "declare namespace App.Interfaces {\n";
        foreach ($interfaces as $interface => $classes) {
            $shortInterface = Str::afterLast($interface, '\\');
            $classNames = collect($classes)
                ->map(fn ($class) => '"' . addslashes($class) . '"')
                ->implode(' | ');
            $tsContent .= "  export type {$shortInterface} = {$classNames};\n";
        }
        $tsContent .= "}\n";

        $this->files->put($this->tsFilePath($path), $tsContent);
    }

    protected function readInterfaces(string $path, array $interfaces)
    {
        /**
          * @var iterable<string,\SplFileInfo>
          */
        $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        $rootNamespace = $this->determineRootNamespace($interfaces);

        $classes = collect($paths)
            ->reject(fn ($i) => !$i->isFile() || !str_ends_with($i->getRealPath(), '.php'))
            ->map(fn ($item) => $this->classInfoFromPath($item->getRealPath()))
            ->keyBy('fqcn');

        return collect($interfaces)
            ->mapWithKeys(fn ($interface) => [
                $this->chopStart($interface, $rootNamespace) => $classes
                    ->filter(fn ($info) => $this->classImplementsInterface($info, $interface, $classes))
                    ->map(fn ($info) => $info['fqcn'])
                    ->values()
                    ->toArray()
            ])
            ->toArray();
    }

    protected function classImplementsInterface(array $info, string $interface, $classes): bool
    {
        if (in_array($interface, $info['implements'], true)) {
            return true;
        }

        if ($info['extends'] === '' || !$classes->has($info['extends'])) {
            return false;
        }

        return $this->classImplementsInterface($classes->get($info['extends']), $interface, $classes);
    }
}

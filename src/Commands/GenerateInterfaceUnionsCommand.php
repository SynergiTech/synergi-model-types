<?php

namespace SynergiTech\ExportTypes\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GenerateInterfaceUnionsCommand extends Command
{
    protected $signature = 'export-interface-unions:generate
        {--input=app/Models}
        {--output=resources/js/models}
        {--format}
        {--prettier=}';

    protected $description = 'Export models that implement an interface to your frontend.';

    public function __construct(
        private Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $output = $this->readInterfaces($this->base(), config('export-types.interfaces', []));

        $this->writeFiles($this->option('output'), $output);

        if ($this->option('format')) {
            $this->runPrettier($this->option('output'));
        }
    }

    private function readInterfaces(string $path, array $interfaces)
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

    private function tsFilePath(string $path): string
    {
        return $this->joinPaths($path, 'index.ts');
    }

    private function writeFiles($path, $content)
    {

        $this->files->ensureDirectoryExists(dirname($this->option('input')));

        if ($this->files->exists($path)) {
            $this->files->deleteDirectory($path);
        }

        $this->files->makeDirectory(
            path: $path,
            recursive: true,
        );

        $tsContent = collect($content)
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

        $this->info("Wrote types to {$this->tsFilePath($path)}!");
    }

    private function runPrettier(string $path, string $prettierCommand = 'npm exec prettier -- '): void
    {
        $prettier = $this->option('prettier') ?: $prettierCommand;
        exec("{$prettier} {$path} --write");
    }

    private function base(): string
    {
        return $this->joinPaths(base_path(), $this->option('input'));
    }

    private function determineRootNamespace(array $classes): string
    {
        // Loop through the array of enum class names. Find the root namespace that all enums share.
        // This is done by finding the longest common prefix of all class names.
        // Then, remove that prefix from each class name to get the relative class name.
        // Finally, use that relative class name as the key in the output array.
        if (count($classes) === 0) {
            return '';
        }

        $commonPrefix = $classes[0];
        foreach ($classes as $class) {
            $i = 0;
            while (isset($commonPrefix[$i], $class[$i]) && $commonPrefix[$i] === $class[$i]) {
                $i++;
            }
            $commonPrefix = substr($commonPrefix, 0, $i);
        }
        // Ensure prefix ends at a namespace separator
        $lastSep = strrpos($commonPrefix, '\\');
        if ($lastSep !== false) {
            return substr($commonPrefix, 0, $lastSep + 1);
        }
        return '';
    }

    protected function fqcnFromPath(string $path): string
    {
        $namespace = $class = $buffer = '';

        $handle = fopen($path, 'r');

        while (!feof($handle)) {
            $buffer .= fread($handle, 512);

            // Suppress warnings for cases where `$buffer` ends in the middle of a PHP comment.
            $tokens = @token_get_all($buffer);

            // Filter out whitespace and comments from the tokens, as they are irrelevant.
            $tokens = array_filter($tokens, fn($token) => $token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT);

            // Reset array indexes after filtering.
            $tokens = array_values($tokens);

            foreach ($tokens as $index => $token) {
                // The namespace is a `T_NAME_QUALIFIED` that is immediately preceded by a `T_NAMESPACE`.
                if (
                    $token[0] === T_NAMESPACE && isset($tokens[$index + 1])
                    && $tokens[$index + 1][0] === T_NAME_QUALIFIED
                ) {
                    $namespace = $tokens[$index + 1][1];
                    continue;
                }

                // The class name is a `T_STRING` which makes it unreliable to match against, so check if we have a
                // `T_CLASS` token with a `T_STRING` token ahead of it.
                if ($token[0] === T_CLASS && isset($tokens[$index + 1]) && $tokens[$index + 1][0] === T_STRING) {
                    $class = $tokens[$index + 1][1];
                }
            }

            if ($namespace && $class) {
                // We've found both the namespace and the class, we can now stop reading and parsing the file.
                break;
            }
        }

        fclose($handle);
        return $namespace . '\\' . $class;
    }

    // Laravel < 11 doesn't have Str::chopStart
    private function chopStart($subject, $needle)
    {
        foreach ((array) $needle as $n) {
            if (str_starts_with($subject, $n)) {
                return substr($subject, strlen($n));
            }
        }

        return $subject;
    }

    // Laravel < 10 doesn't have Illuminate\Filesystem\join_paths
    private function joinPaths($basePath, ...$paths): string
    {
        foreach ($paths as $index => $path) {
            if (empty($path) && $path !== '0') {
                unset($paths[$index]);
            } else {
                $paths[$index] = DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
            }
        }

        return $basePath . implode('', $paths);
    }
}

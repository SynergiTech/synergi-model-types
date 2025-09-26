<?php

namespace SynergiTech\ExportTypes\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem; 

abstract class BaseCommand extends Command
{
    protected $signature = '';

    protected $description = '';

    public function __construct(
        private Filesystem $files
    ) {
        parent::__construct();
    }

    protected function preprocess(): void {
      $path = $this->option('output');

      $this->files->ensureDirectoryExists(dirname($this->option('input')));

        if ($this->files->exists($path)) {
            $this->files->deleteDirectory($path);
        }

        $this->files->makeDirectory(
            path: $path,
            recursive: true,
        );
    }

    protected function runPostProcessingHooks(): void {
        if ($this->option('format')) {
            $this->runPrettier($this->option('output'));
        }
    }

    abstract protected function process(): void;

    public function handle(): void
    {
      $this->preprocess();
      $this->process();
      $this->runPostProcessingHooks();
      $this->done();
    }

    protected function done(): void
    {
        $path = $this->option('output');
        $this->info("Wrote types to {$this->tsFilePath($path)}!");
    }
 
    protected function tsFilePath(string $path): string
    {
        return $this->joinPaths($path, 'index.ts');
    }


    protected function runPrettier(string $path, string $prettierCommand = 'npm exec prettier -- '): void
    {
        $prettier = $this->option('prettier') ?: $prettierCommand;
        exec("{$prettier} {$path} --write");
    }

    protected function base(): string
    {
        return $this->joinPaths(base_path(), $this->option('input'));
    }

    protected function determineRootNamespace(array $classes): string
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
    protected function chopStart($subject, $needle)
    {
        foreach ((array) $needle as $n) {
            if (str_starts_with($subject, $n)) {
                return substr($subject, strlen($n));
            }
        }

        return $subject;
    }

    // Laravel < 10 doesn't have Illuminate\Filesystem\join_paths
    protected function joinPaths($basePath, ...$paths): string
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

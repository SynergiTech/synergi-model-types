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

    protected function preprocess(): void
    {
        $path = $this->option('output');
        $basePath = $this->base();

        if (!$this->files->isDirectory($basePath) || !$this->files->isReadable($basePath)) {
            throw new \RuntimeException("Input path [{$basePath}] does not exist or is not readable.");
        }

        if ($this->files->exists($path)) {
            $this->files->deleteDirectory($path);
        }

        $this->files->makeDirectory(
            path: $path,
            recursive: true,
        );
    }

    protected function runPostProcessingHooks(): void
    {
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
        return $this->joinPaths($path, 'index.d.ts');
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
        return $this->classInfoFromPath($path)['fqcn'];
    }

    /**
     * @return array{namespace:string,class:string,fqcn:string,extends:string,implements:array<int,string>}
     */
    protected function classInfoFromPath(string $path): array
    {
        $declaration = $this->readClassDeclaration($path);

        if ($declaration === '') {
            return [
                'namespace' => '',
                'class' => '',
                'fqcn' => '',
                'extends' => '',
                'implements' => [],
            ];
        }

        $namespace = '';
        $class = '';
        $extends = '';
        $implements = [];
        $imports = [];

        $tokens = token_get_all($declaration);
        $classIndex = $this->findClassTokenIndex($tokens);

        if ($classIndex === null) {
            return [
                'namespace' => '',
                'class' => '',
                'fqcn' => '',
                'extends' => '',
                'implements' => [],
            ];
        }

        for ($index = 0; $index < $classIndex; $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->parseQualifiedName($tokens, $index + 1);
                continue;
            }

            if ($token[0] === T_USE) {
                $imports = array_merge($imports, $this->parseUseStatement($tokens, $index));
            }
        }

        $class = $this->parseClassName($tokens, $classIndex + 1);
        $extends = $this->parseExtendedClass($tokens, $classIndex + 1, $namespace, $imports);
        $implements = $this->parseImplementedInterfaces($tokens, $classIndex + 1, $namespace, $imports);

        return [
            'namespace' => $namespace,
            'class' => $class,
            'fqcn' => trim($namespace . '\\' . $class, '\\'),
            'extends' => $extends,
            'implements' => $implements,
        ];
    }

    protected function findClassTokenIndex(array $tokens): ?int
    {
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            return $index;
        }

        return null;
    }

    protected function parseClassName(array $tokens, int $index): string
    {
        for (; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token[0] === T_STRING) {
                return $token[1];
            }

            break;
        }

        return '';
    }

    protected function readClassDeclaration(string $path): string
    {
        $buffer = '';
        $classOffset = null;
        $braceOffset = null;
        $state = 'code';
        $currentWord = '';
        $currentWordOffset = null;
        $lastWord = '';
        $previousCharacter = '';
        $scanOffset = 0;

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return '';
        }

        while (!feof($handle)) {
            $buffer .= fread($handle, 512);

            $length = strlen($buffer);
            for ($index = $scanOffset; $index < $length; $index++) {
                $character = $buffer[$index];
                $nextCharacter = $buffer[$index + 1] ?? '';

                if ($state === 'line_comment') {
                    if ($character === "\n") {
                        $state = 'code';
                    }

                    continue;
                }

                if ($state === 'block_comment') {
                    if ($previousCharacter === '*' && $character === '/') {
                        $state = 'code';
                    }

                    $previousCharacter = $character;
                    continue;
                }

                if ($state === 'single_quote') {
                    if ($character === '\'' && $previousCharacter !== '\\') {
                        $state = 'code';
                    }

                    $previousCharacter = $character;
                    continue;
                }

                if ($state === 'double_quote') {
                    if ($character === '"' && $previousCharacter !== '\\') {
                        $state = 'code';
                    }

                    $previousCharacter = $character;
                    continue;
                }

                if ($character === '/' && ($nextCharacter === '/' || $nextCharacter === '*')) {
                    $state = $nextCharacter === '/' ? 'line_comment' : 'block_comment';
                    $previousCharacter = $character;
                    $index++;
                    continue;
                }

                if ($character === '#' && $nextCharacter !== '[') {
                    $state = 'line_comment';
                    continue;
                }

                if ($character === '\'' && $previousCharacter !== '\\') {
                    $state = 'single_quote';
                    $previousCharacter = $character;
                    continue;
                }

                if ($character === '"' && $previousCharacter !== '\\') {
                    $state = 'double_quote';
                    $previousCharacter = $character;
                    continue;
                }

                if (ctype_alnum($character) || $character === '_') {
                    if ($currentWord === '') {
                        $currentWordOffset = $index;
                    }

                    $currentWord .= $character;
                    $previousCharacter = $character;
                    continue;
                }

                if ($currentWord !== '') {
                    if ($currentWord === 'class' && $lastWord !== 'new') {
                        $classOffset = $currentWordOffset;
                    }

                    $lastWord = $currentWord;
                    $currentWord = '';
                    $currentWordOffset = null;
                }

                if ($classOffset !== null && $character === '{') {
                    $braceOffset = $index;
                    break 2;
                }

                if (!ctype_space($character)) {
                    $lastWord = '';
                }

                $previousCharacter = $character;
            }

            $scanOffset = $length;
        }

        fclose($handle);

        if ($braceOffset === null) {
            return '';
        }

        return substr($buffer, 0, $braceOffset + 1);
    }

    protected function parseQualifiedName(array $tokens, int $index): string
    {
        $name = '';

        for (; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === ';' || $token === '{') {
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                continue;
            }

            break;
        }

        return $name;
    }

    protected function parseUseStatement(array $tokens, int &$index): array
    {
        $imports = [];
        $prefix = '';
        $name = '';
        $alias = '';
        $mode = 'name';
        $grouped = false;
        $statementStarted = false;

        for ($index++; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === '{') {
                    $grouped = true;
                    $prefix = trim($name, '\\');
                    $name = '';
                    $alias = '';
                    $mode = 'name';
                    continue;
                }

                if ($token === ',') {
                    $this->appendImport($imports, $prefix, $name, $alias, $grouped);
                    $name = '';
                    $alias = '';
                    $mode = 'name';
                    continue;
                }

                if ($token === '}') {
                    $this->appendImport($imports, $prefix, $name, $alias, $grouped);
                    $name = '';
                    $alias = '';
                    $mode = 'name';
                    continue;
                }

                if ($token === ';') {
                    $this->appendImport($imports, $prefix, $name, $alias, $grouped);
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if (!$statementStarted && in_array($token[0], [T_FUNCTION, T_CONST], true)) {
                while (isset($tokens[$index]) && $tokens[$index] !== ';') {
                    $index++;
                }

                break;
            }

            $statementStarted = true;

            if ($token[0] === T_AS) {
                $mode = 'alias';
                continue;
            }

            if (!$this->isNameToken($token)) {
                continue;
            }

            if ($mode === 'alias') {
                $alias .= $token[1];
                continue;
            }

            $name .= $token[1];
        }

        return $imports;
    }

    protected function appendImport(array &$imports, string $prefix, string $name, string $alias, bool $grouped): void
    {
        if ($name === '') {
            return;
        }

        $fqcn = $grouped
            ? trim($prefix . '\\' . ltrim($name, '\\'), '\\')
            : trim($name, '\\');

        if ($fqcn === '') {
            return;
        }

        $resolvedAlias = $alias !== ''
            ? $alias
            : $this->defaultImportAlias($fqcn);

        $imports[$resolvedAlias] = $fqcn;
    }

    protected function defaultImportAlias(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }

    protected function parseImplementedInterfaces(array $tokens, int $index, string $namespace, array $imports): array
    {
        $implements = [];
        $name = '';
        $parsingImplements = false;

        for (; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === ',') {
                    $this->appendResolvedInterface($implements, $name, $namespace, $imports);
                    $name = '';
                    continue;
                }

                if ($token === '{') {
                    $this->appendResolvedInterface($implements, $name, $namespace, $imports);
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                $parsingImplements = true;
                continue;
            }

            if (!$parsingImplements) {
                continue;
            }

            if (!$this->isNameToken($token)) {
                continue;
            }

            $name .= $token[1];
        }

        return array_values(array_unique($implements));
    }

    protected function parseExtendedClass(array $tokens, int $index, string $namespace, array $imports): string
    {
        $name = '';
        $parsingExtends = false;

        for (; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];

            if (!is_array($token)) {
                if ($token === '{' || $token === ',') {
                    break;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token[0] === T_EXTENDS) {
                $parsingExtends = true;
                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                break;
            }

            if (!$parsingExtends) {
                continue;
            }

            if (!$this->isNameToken($token)) {
                continue;
            }

            $name .= $token[1];
        }

        return $this->resolveImportedName($name, $namespace, $imports);
    }

    protected function appendResolvedInterface(
        array &$implements,
        string $name,
        string $namespace,
        array $imports
    ): void {
        $resolved = $this->resolveImportedName($name, $namespace, $imports);

        if ($resolved !== '') {
            $implements[] = $resolved;
        }
    }

    protected function resolveImportedName(string $name, string $namespace, array $imports): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $segments = explode('\\', $name);
        $root = $segments[0];

        if (isset($imports[$root])) {
            $suffix = array_slice($segments, 1);

            return implode('\\', array_filter([$imports[$root], ...$suffix]));
        }

        if (str_contains($name, '\\')) {
            return trim($namespace . '\\' . $name, '\\');
        }

        if (isset($imports[$name])) {
            return $imports[$name];
        }

        return trim($namespace . '\\' . $name, '\\');
    }

    protected function isNameToken(array $token): bool
    {
        return in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true);
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

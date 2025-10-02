<?php

namespace SynergiTech\ExportTypes\Commands;
 
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GenerateFormRequestsCommand extends BaseCommand
{
    protected $signature = 'export-form-requests:generate
        {--input=app/Http/Requests}
        {--output=resources/js/form-requests}
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

        $formRequests = $this->readFormRequests($this->base());

        // Group form requests by namespace
        $grouped = collect($formRequests)
            ->groupBy(function ($formRequest) {
            return Str::of($formRequest['class'])
                ->beforeLast('\\')
                ->replace('\\', '.')
                ->toString();
            });

        $tsContent = $grouped->map(function ($requests, $namespace) {
            $interfaces = collect($requests)->map(function ($formRequest) {
            $rulesString = collect($formRequest['rules'])
                ->map(fn($type, $field) => "{$field}{$type};")
                ->join("\n");
            return <<<TS
    /**
     * @see {$formRequest['class']}
     */
        export interface {$formRequest['entity']} {
        {$rulesString}
        }
    TS;
            })->join("\n\n");

            return <<<JAVASCRIPT
    export namespace {$namespace} {
    {$interfaces}
    }
    JAVASCRIPT;
        })->join("\n\n");

        $tsContent .= "\n\nexport {};\n";
        $this->files->put($this->tsFilePath($path), $tsContent);

    }

    protected function parseRules(FormRequest $formRequest) {
        $mappings = [
            // TS -> PHP
            'string' => ['string', 'email'],
            'boolean' => ['boolean'],
            'number' => ['number', 'integer'],
            'any[]' => ['array'],
        ];

        $rules = $formRequest->rules();

        // Preparse the rules into a consistent format for easier processing
        $r = collect($rules)
            ->map(function ($rules, $field) use ($mappings, $formRequest) {    
                $adjustedRules = is_array($rules) ? $rules : explode('|', $rules);

                return [ 
                    'rules' => $adjustedRules
                ];
            })
            // Strip array value types (e.g., rule_array.*) because we don't support that yet
            ->reject(fn($v, $k) => str_contains($k, '.*'))
            ->map(function ($item, $field) use ($mappings) {
                $isNullable = in_array('nullable', $item['rules']);
                $isSometimes = in_array('sometimes', $item['rules']);
                $isArray = in_array('array', $item['rules']);
                $type = 'any';

                foreach ($mappings as $tsType => $phpTypes) {
                    if (count(array_intersect($phpTypes, $item['rules'])) > 0) {
                        $type = $tsType;
                        break;
                    }
                }

                $prefix = ($isNullable || $isSometimes) ? '?:' : ':';
                return "{$prefix} {$type}";
            });

        return $r->toArray(); 
    }

    protected function readFormRequests(string $path)
    {
        $classes = collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path))))
            ->reject(fn ($i) => $i->isDir() || str_ends_with($i->getRealPath(), '/..'))
            ->map(fn ($item) => $this->fqcnFromPath($item->getRealPath()))
            ->filter( fn($class) => is_subclass_of($class, FormRequest::class)) 
            ->values();

        $rootNamespace = $this->determineRootNamespace($classes->toArray());

        return $classes
            ->map(function ($class) use ($rootNamespace) {
                $classKey = Str::of($this->chopStart($class, $rootNamespace))
                    ->replace('\\', '')
                    ->toString();
                    
                    return [
                        'entity' => $classKey,
                        'rules' => $this->parseRules(new $class()),
                        'class' => $class
                    ];  
            })
            ->toArray();
    }
}

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
        $tsContent = collect($formRequests)
            ->map(function ($formRequest) {
                $rulesString = collect($formRequest['rules'])
                    ->map(fn($type, $field) => "{$field}{$type};")
                    ->join("\n");
                    // Derive namespace from the class, e.g.,
                    //  App\Http\Requests\MemberRequest => App.Http.Requests
                    $namespace = Str::of($formRequest['class'])
                        ->beforeLast('\\')
                        ->replace('\\', '.')
                        ->toString();

                    return <<<JAVASCRIPT
/**
 * @see {$formRequest['class']}
 */
export namespace {$namespace} {
    export interface {$formRequest['entity']} {
        {$rulesString}
    }
}
JAVASCRIPT;
            })
            ->join("\n\n");
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

        return collect($formRequest->rules())
            ->mapWithKeys(function ($rules, $field) use ($mappings) {
                $type = 'any';
                $adjustedRules = is_string($rules) ? explode('|', $rules) : (is_array($rules) ? $rules : []);
                $divider = in_array('nullable', $adjustedRules) ? '?' : '';

                // Determine type by iterating through our mappings.
                foreach ($mappings as $tsType => $phpTypes) {
                    foreach ($phpTypes as $phpType) {
                        if (in_array($phpType, $adjustedRules)) {
                            $type = $tsType;
                            break 2;
                        }
                    }
                }
                return [$field => "{$divider}: {$type}"];
            });
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

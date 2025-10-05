<?php

namespace SynergiTech\ExportTypes\Commands;
 
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class GenerateFormRequestsCommand extends BaseCommand
{
    protected $signature = 'synergi-types:requests
        {--input=app/Http/Requests}
        {--output=resources/js/form-requests}
        {--format}
        {--prettier=}';

    protected $description = 'Export Form Requests as TypeScript types.';

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct($files);
    }

    protected function process(): void
    {
        // https://laravel.com/docs/12.x/validation#available-validation-rules
        $typeMap = [
            'string' => 'string',
            'integer' => 'number',
            'int' => 'number',
            'numeric' => 'number',
            'float' => 'number',
            'double' => 'number',
            'decimal' => 'number',
            'boolean' => 'boolean',
            'bool' => 'boolean',
            'accepted' => 'boolean',
            'array' => 'any[]',
            'object' => 'Record<string, any>',
            'date' => 'string',
            'email' => 'string',
            'file' => 'File',
            'image' => 'File',
            'json' => 'any',
            'url' => 'string',
            'uuid' => 'string',
            'ip' => 'string',
            'active_url' => 'string',
            'timezone' => 'string',
            'digits' => 'string',
            'digits_between' => 'number',
            'ends_with' => 'string',
            'starts_with' => 'string',
            'alpha' => 'string',
            'alpha_dash' => 'string',
            'alpha_num' => 'string',
            'regex' => 'string',
            'present' => '',
            'distinct' => '',
            'nullable' => '',
            'required' => '',
            'sometimes' => '',
            'confirmed' => '',
            'between' => '',
            'in' => '',
            'not_in' => '',
            'size' => '',
            'min' => '',
            'max' => '',
        ];

        $path = $this->option('output');
        $tsContent = '';

        $formRequests = $this->readFormRequests($this->base()); 

        $formRequests
            ->groupBy(function ($formRequest) {
                return Str::of($formRequest['class'])
                    ->beforeLast('\\')
                    ->replace('\\', '.')
                    ->toString();
            })
            ->each(function ($requests, $namespace) use (&$tsContent, $typeMap) {
                $tsContent .= "declare namespace {$namespace} {\n";
                foreach ($requests as $request) {
                    $entity = $request['entity'];
                    $fields = $request['rules'];


                    $tsFields = [];
                    foreach ($fields as $field => $rules) {
                        $tsType = 'string'; // default
                        $isNullable = false;
                        $isRequired = false;

                        foreach ($rules as $rule) {
                            $rule = strtolower($rule);
                            if (isset($typeMap[$rule]) && $typeMap[$rule] !== '') {
                                $tsType = $typeMap[$rule];
                            }
                            if ($rule === 'nullable') {
                                $isNullable = true;
                            }
                            if ($rule === 'required') {
                                $isRequired = true;
                            }
                        }

                        if ($isNullable) {
                            $tsType .= ' | null';
                        }

                        $optional = $isRequired ? '' : '?';
                        $tsFields[] = "    {$field}{$optional}: {$tsType};";
                    }

                    $tsContent .= "
/**
 * @see {$request['class']}
 * @see filePath
 */
export type {$entity} = {\n";
                    $tsContent .= implode("\n", $tsFields);
                    $tsContent .= "\n  };\n";
                }
                $tsContent .= "}\n";
            });

        $this->files->put($this->tsFilePath($path), $tsContent);
    }

    protected function parseRules(FormRequest $formRequest) {          
        $arrayOfRulesOrString = function ($rules) {
            if (is_string($rules)) {
                return explode('|', $rules);
            }
            // If it's an array, flatten and explode any pipe-separated strings
            return collect($rules)
                ->flatten()
                ->filter(fn ($rule) => is_string($rule))
                ->flatMap(function ($rule) {
                    return str_contains($rule, '|') ? explode('|', $rule) : [$rule];
                })
                ->map(fn($rule) => trim($rule))
                ->values()
                ->toArray();
        };

        $fields = collect($formRequest->rules());

        // Find all child keys (e.g., field.something) so we can remove them from the top level
        $childKeys = collect($fields)
            ->keys()
            ->filter(fn($k) => str_contains($k, '.') && $fields->has(Str::before($k, '.')))
            ->values();

        return $fields
            // Normalise all rules to arrays of strings for each field
            ->map($arrayOfRulesOrString)
            // TODO: Support array/object inference
            /*
            ->map(function ($adjusted, $field) use ($fields, $arrayOfRulesOrString) {
                if (in_array('array', $adjusted)) {
                    // Find all rules for keys like 'field.*'
                    $children = collect($fields)
                        ->filter(fn ($v, $k) => str_starts_with($k, $field . '.') && preg_match('/^' . preg_quote($field, '/') . '\.\*$/', $k))
                        ->map($arrayOfRulesOrString);
                    
                    if ($children->isNotEmpty()) {
                        return [
                            'rules' => $adjusted,
                            'children' => $children->toArray(),
                        ];
                    }
                }

                return $adjusted;
            })
            */
            // Remove child keys from the top level
            ->reject(fn  ($v, $k) => $childKeys->contains($k))
            ->toArray();
    }

    protected function readFormRequests(string $path)
    {
        $classes = collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path))))
            ->reject(fn ($i) =>
                $i->isDir()
                || str_ends_with($i->getRealPath(), '/..')
                || ! str_ends_with($i->getRealPath(), '.php')
            )
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
            }) ;
    }
}

<?php

namespace SynergiTech\ExportTypes\Tests;

use SynergiTech\ExportTypes\Commands\GenerateInterfaceUnionsCommand;

class TestableGenerateInterfaceUnionsCommand extends GenerateInterfaceUnionsCommand
{
    /**
     * @return array{namespace:string,class:string,fqcn:string,extends:string,implements:array<int,string>}
     */
    public function classInfo(string $path): array
    {
        return $this->classInfoFromPath($path);
    }
}

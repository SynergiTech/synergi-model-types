<?php

namespace SynergiTech\ExportTypes\Tests\Commands;

use SynergiTech\ExportTypes\Tests\TestCase;

use Illuminate\Testing\PendingCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class GenerateInterfaceUnionsCommandTest extends TestCase
{
    public function testCommandExports(): void
    { 
        $command = $this->artisan('export-interface-unions:generate:generate --input=app/models --output=models');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $result = $command->run();
        $this->assertSame(SymfonyCommand::SUCCESS, $result);
        $this->assertFileExists('./models/index.ts'); 
    }
}

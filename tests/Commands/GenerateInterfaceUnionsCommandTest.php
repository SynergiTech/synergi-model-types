<?php

namespace SynergiTech\ExportTypes\Tests\Commands;

use SynergiTech\ExportTypes\Tests\TestCase;

// use Illuminate\Testing\PendingCommand;
// use Symfony\Component\Console\Command\Command as SymfonyCommand;

class GenerateInterfaceUnionsCommandTest extends TestCase
{
    public function testCommandExports(): void
    {
        $this->markTestSkipped('Requires implementation.');
       /*  $command = $this->artisan('laravel-magic-enums:generate --input=app/Enums --output=enums');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $result = $command->run();
        $this->assertSame(SymfonyCommand::SUCCESS, $result);
        $this->assertFileExists('./enums/index.js'); */
    }
}

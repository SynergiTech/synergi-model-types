<?php

namespace SynergiTech\ExportTypes\Tests\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use SynergiTech\ExportTypes\Tests\TestCase;
use SynergiTech\ExportTypes\Tests\TestableGenerateInterfaceUnionsCommand;

class GenerateInterfaceUnionsCommandTest extends TestCase
{
    public function testCommandExports(): void
    {
        $command = $this->artisan('synergi-types:interface-unions --input=app/Models --output=models');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $result = $command->run();
        $this->assertSame(SymfonyCommand::SUCCESS, $result);
        $this->assertFileExists('./models/index.d.ts');

        $output = file_get_contents('./models/index.d.ts');

        $this->assertIsString($output);
        $this->assertStringContainsString('"App\\\\Models\\\\AliasAnimal"', $output);
        $this->assertStringContainsString('"App\\\\Models\\\\FullyQualifiedAnimal"', $output);
        $this->assertStringContainsString('"App\\\\Models\\\\GroupedUseAnimal"', $output);
        $this->assertStringContainsString('"App\\\\Models\\\\InheritedAnimal"', $output);
    }

    public function testCommandExportsFullClassNameWhenDeclarationCrossesChunkBoundary(): void
    {
        $fixture = './workbench/app/Models/SomeLongClassNameThatCrossesTheLegacyChunkBoundary.php';
        $contents = file_get_contents($fixture);
        $classPosition = strpos($contents ?: '', 'class SomeLongClassNameThatCrossesTheLegacyChunkBoundary');
        $bracePosition = strpos($contents ?: '', '{');

        $this->assertIsString($contents);
        $this->assertNotFalse($classPosition);
        $this->assertNotFalse($bracePosition);
        $this->assertLessThan(512, $classPosition);
        $this->assertGreaterThan(512, $bracePosition);

        $command = $this->artisan('synergi-types:interface-unions --input=app/Models --output=models-boundary');
        $this->assertInstanceOf(PendingCommand::class, $command);

        $result = $command->run();

        $this->assertSame(SymfonyCommand::SUCCESS, $result);
        $this->assertFileExists('./models-boundary/index.d.ts');

        $output = file_get_contents('./models-boundary/index.d.ts');

        $this->assertIsString($output);
        $this->assertStringContainsString(
            '"App\\\\Models\\\\SomeLongClassNameThatCrossesTheLegacyChunkBoundary"',
            $output
        );
    }

    public function testParserExtractsNamespaceClassAndImplementedInterfaces(): void
    {
        $command = $this->makeParserCommand();

        $info = $command->classInfo('./workbench/app/Models/SomeLongClassNameThatCrossesTheLegacyChunkBoundary.php');

        $this->assertSame('App\\Models', $info['namespace']);
        $this->assertSame('SomeLongClassNameThatCrossesTheLegacyChunkBoundary', $info['class']);
        $this->assertSame(
            'App\\Models\\SomeLongClassNameThatCrossesTheLegacyChunkBoundary',
            $info['fqcn']
        );
        $this->assertSame(['App\\Interfaces\\AnimalInterface'], $info['implements']);
    }

    public function testParserResolvesAliasedImportedInterfaces(): void
    {
        $info = $this->makeParserCommand()->classInfo('./workbench/app/Models/AliasAnimal.php');

        $this->assertSame(['App\\Interfaces\\AnimalInterface'], $info['implements']);
    }

    public function testParserResolvesFullyQualifiedImplementedInterfaces(): void
    {
        $info = $this->makeParserCommand()->classInfo('./workbench/app/Models/FullyQualifiedAnimal.php');

        $this->assertSame(['App\\Interfaces\\AnimalInterface'], $info['implements']);
    }

    public function testParserResolvesGroupedUseImportsAndMultipleInterfaces(): void
    {
        $info = $this->makeParserCommand()->classInfo('./workbench/app/Models/GroupedUseAnimal.php');

        $this->assertSame(
            ['App\\Interfaces\\AnimalInterface', 'App\\Interfaces\\CompanionInterface'],
            $info['implements']
        );
    }

    public function testParserExtractsInheritedParentClass(): void
    {
        $info = $this->makeParserCommand()->classInfo('./workbench/app/Models/InheritedAnimal.php');

        $this->assertSame('App\\Models\\ParentAnimal', $info['extends']);
        $this->assertSame([], $info['implements']);
    }

    public function testParserResolvesSingleSegmentImports(): void
    {
        $info = $this->makeParserCommand()->classInfo('./workbench/app/Models/GlobalImportArrayAccessible.php');

        $this->assertSame(['ArrayAccess'], $info['implements']);
    }

    public function testCommandFailsForInvalidInputPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $command = $this->artisan('synergi-types:interface-unions --input=app/DoesNotExist --output=invalid-models');

        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->run();
    }

    protected function makeParserCommand(): TestableGenerateInterfaceUnionsCommand
    {
        return new TestableGenerateInterfaceUnionsCommand(new Filesystem());
    }
}

<?php

namespace SynergiTech\ExportTypes\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use WithWorkbench;

    protected function getPackageProviders($app)
    {
        return [\SynergiTech\ExportTypes\ExportTypesServiceProvider::class];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }

    public function setUp(): void
    {
        parent::setUp();

        // $this->artisan('migrate')->run();
    }
}

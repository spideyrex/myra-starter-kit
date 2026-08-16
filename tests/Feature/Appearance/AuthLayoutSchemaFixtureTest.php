<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AuthLayoutRegistry;
use Tests\TestCase;

class AuthLayoutSchemaFixtureTest extends TestCase
{
    use SyncsAppearanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        AuthLayoutRegistry::flush();
    }

    public function test_the_registry_schema_matches_the_committed_fixture(): void
    {
        $schema = AuthLayoutRegistry::toClientSchema();

        $this->assertCount(4, $schema);

        // Lowercase `fixtures`, matching tests/fixtures/tenancy-baseline.json —
        // the repo's real casing, which is what a case-sensitive CI box sees.
        $this->syncFixture('tests/fixtures/appearance/layouts.json', $schema);
    }

    public function test_every_registered_layout_declares_a_mountable_component(): void
    {
        foreach (AuthLayoutRegistry::all() as $layout) {
            $this->assertMatchesRegularExpression('/^[A-Z][A-Za-z0-9]*Layout$/', $layout->componentName());
            $this->assertNotSame('', $layout->key());
        }
    }
}

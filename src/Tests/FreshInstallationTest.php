<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Tests;

use Tests\TestCase;
use Ethernick\ActivityPubCore\Tests\Concerns\ProvidesSandbox;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;
use Statamic\Facades\Collection;
use Statamic\Facades\File;
use Statamic\Facades\YAML;
use PHPUnit\Framework\Attributes\Test;

class FreshInstallationTest extends TestCase
{
    use ProvidesSandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSandbox();
    }

    protected function tearDown(): void
    {
        $this->teardownSandbox();
        parent::tearDown();
    }

    #[Test]
    public function it_executes_events_install_command_in_isolation(): void
    {
        $settingsPath = ActivityPubUtils::settingsPath();
        if (File::exists($settingsPath)) {
            File::delete($settingsPath);
        }

        $blueprintPath = ActivityPubUtils::blueprintPath('collections/events/event.yaml');
        if (File::exists($blueprintPath)) {
            File::delete($blueprintPath);
        }

        $this->artisan('activitypub:events:install')
            ->expectsOutput('Installing ActivityPub Events...')
            ->expectsOutput('ActivityPub Events installation complete!')
            ->assertExitCode(0);

        // Verify events collection exists
        $collection = Collection::findByHandle('events');
        $this->assertNotNull($collection);
        $this->assertEquals('Events', $collection->title());

        // Verify blueprint template was installed
        $this->assertFileExists($blueprintPath);
        $blueprintData = YAML::file($blueprintPath)->parse();
        $this->assertEquals('Event', $blueprintData['title'] ?? null);

        // Verify activitypub.yaml has events configured
        $this->assertFileExists($settingsPath);
        $settings = YAML::file($settingsPath)->parse();
        $this->assertArrayHasKey('events', $settings);
        $this->assertTrue($settings['events']['enabled'] ?? false);
        $this->assertTrue($settings['events']['federated'] ?? false);
        $this->assertEquals('Event', $settings['events']['type'] ?? null);
    }
}

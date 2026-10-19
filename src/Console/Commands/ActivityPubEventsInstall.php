<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Collection;
use Statamic\Facades\File;
use Statamic\Facades\YAML;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;

class ActivityPubEventsInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activitypub:events:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and configure ActivityPub Events.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Installing ActivityPub Events...');

        $this->ensureEventsCollection();
        $this->configureActivityPubSettings();
        $this->publishAssets();

        $this->info('ActivityPub Events installation complete!');
    }


    protected function ensureEventsCollection(): void
    {
        $handle = 'events';
        $title = 'Events';

        if (Collection::findByHandle($handle)) {
            $this->comment("✓ Collection [{$handle}] already exists.");
        } else {
            $this->info("Creating collection: {$title} ({$handle})...");
            Collection::make($handle)
                ->title($title)
                ->save();
        }

        $this->ensureBlueprint($handle);
    }

    protected function ensureBlueprint(string $handle): void
    {
        $blueprintPath = ActivityPubUtils::blueprintPath("collections/{$handle}/event.yaml");
        $blueprintDir = dirname($blueprintPath);
        if (!File::exists($blueprintDir)) {
            File::makeDirectory($blueprintDir, 0755, true);
        }

        $stubPath = __DIR__ . '/../../../resources/blueprints/events/event.yaml';

        if (!File::exists($stubPath)) {
            $this->error("Blueprint template for Events not found at [{$stubPath}]");
            return;
        }

        $targetFields = YAML::parse(File::get($stubPath));

        if (!File::exists($blueprintPath)) {
            $this->info("Creating default blueprint for Events at [{$blueprintPath}]...");
            File::put($blueprintPath, YAML::dump($targetFields));
        } else {
            $this->info("Blueprint already exists at [{$blueprintPath}]. Skipping override.");
        }
    }

    protected function configureActivityPubSettings(): void
    {
        $settingsPath = ActivityPubUtils::settingsPath();
        $settingsDir = dirname($settingsPath);

        if (!File::exists($settingsDir)) {
            File::makeDirectory($settingsDir, 0755, true);
        }

        if (!File::exists($settingsPath)) {
            File::put($settingsPath, YAML::dump([]));
        }

        $settings = YAML::parse(File::get($settingsPath));
        
        if (isset($settings['events'])) {
            $this->comment("✓ Events already configured in activitypub.yaml.");
        } else {
            $this->info("Enabling Events in activitypub.yaml...");
            $settings['events'] = [
                'enabled' => true,
                'federated' => true,
                'type' => 'Event',
            ];
            File::put($settingsPath, YAML::dump($settings));
        }

        // Update events.yaml content collection settings to ensure date: true and route
        $collectionPath = ActivityPubUtils::collectionPath('events');
        if (File::exists($collectionPath)) {
            $this->info("Updating Events collection configuration...");
            $config = YAML::parse(File::get($collectionPath));
            $config['date'] = true;
            $config['route'] = '/events/{slug}';
            $config['template'] = 'events/show';
            $config['blueprints'] = ['event'];
            File::put($collectionPath, YAML::dump($config));
        }
    }


    protected function publishAssets(): void
    {
        $this->info('Publishing ActivityPub Events assets...');
        $this->call('vendor:publish', [
            '--provider' => 'Ethernick\ActivityPubEvents\ActivityPubEventsServiceProvider',
            '--tag' => 'activitypub-events',
            '--force' => true,
        ]);
    }
}


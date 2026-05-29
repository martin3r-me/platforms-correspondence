<?php

namespace Platform\Correspondence;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CorrespondenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/correspondence.php', 'correspondence');
    }

    public function boot(): void
    {
        // Morph-Map
        Relation::morphMap([
            'correspondence_thread' => \Platform\Correspondence\Models\CorrespondenceThread::class,
            'correspondence_item' => \Platform\Correspondence\Models\CorrespondenceItem::class,
        ]);

        // EntityLinkProvider registrieren (loose Kopplung mit Organization-Modul)
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\Correspondence\Organization\CorrespondenceEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        // Modul-Registrierung
        if (
            config()->has('correspondence.routing') &&
            config()->has('correspondence.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key' => 'correspondence',
                'title' => 'Korrespondenz',
                'routing' => config('correspondence.routing'),
                'guard' => config('correspondence.guard'),
                'navigation' => config('correspondence.navigation'),
                'sidebar' => config('correspondence.sidebar'),
            ]);
        }

        // Routes
        if (PlatformCore::getModule('correspondence')) {
            ModuleRouter::group('correspondence', function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__ . '/../config/correspondence.php' => config_path('correspondence.php'),
        ], 'config');

        // Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'correspondence');

        // Livewire Components
        $this->registerLivewireComponents();

        // Tools registrieren
        $this->registerTools();
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            $registry->register(new \Platform\Correspondence\Tools\CorrespondenceOverviewTool());
            $registry->register(new \Platform\Correspondence\Tools\ImportEmailTool());
            $registry->register(new \Platform\Correspondence\Tools\ImportLetterTool());
            $registry->register(new \Platform\Correspondence\Tools\ListThreadsTool());
            $registry->register(new \Platform\Correspondence\Tools\GetThreadTool());
            $registry->register(new \Platform\Correspondence\Tools\ListItemsTool());
            $registry->register(new \Platform\Correspondence\Tools\GetItemTool());
            $registry->register(new \Platform\Correspondence\Tools\AssignThreadTool());
            $registry->register(new \Platform\Correspondence\Tools\SearchCorrespondenceTool());
            $registry->register(new \Platform\Correspondence\Tools\MergeThreadsTool());
            $registry->register(new \Platform\Correspondence\Tools\SplitThreadTool());
            $registry->register(new \Platform\Correspondence\Tools\DeleteThreadTool());
        } catch (\Throwable $e) {
            \Log::warning('Correspondence: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Correspondence\\Livewire';
        $prefix = 'correspondence';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}

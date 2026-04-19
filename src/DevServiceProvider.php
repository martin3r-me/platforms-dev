<?php

namespace Platform\Dev;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DevServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dev.php', 'dev');

        $this->app->singleton('dev.error-tracker', Services\DevErrorTrackingService::class);
        $this->app->singleton(Contracts\DevErrorTrackerContract::class, Services\DevErrorTrackingService::class);
    }

    public function boot(): void
    {
        // Morph Map
        Relation::morphMap([
            'dev_package' => \Platform\Dev\Models\DevPackage::class,
            'dev_issue' => \Platform\Dev\Models\DevIssue::class,
            'dev_error_occurrence' => \Platform\Dev\Models\DevErrorOccurrence::class,
        ]);

        if (
            config()->has('dev.routing') &&
            config()->has('dev.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'dev',
                'title'      => 'Dev',
                'routing'    => config('dev.routing'),
                'guard'      => config('dev.guard'),
                'navigation' => config('dev.navigation'),
                'sidebar'    => config('dev.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('dev')) {
            ModuleRouter::group('dev', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // API Route für Error Ingest (immer laden, auch ohne UI-Modul)
        \Illuminate\Support\Facades\Route::domain(parse_url(config('app.url'), PHP_URL_HOST))
            ->middleware(['api'])
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/dev.php' => config_path('dev.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'dev');

        // Organization Entity Links (loose coupling)
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\Dev\Organization\DevEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        $this->registerLivewireComponents();
        $this->registerTools();

        // Error Reporter Registration
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('dev', 'Platform\\Dev');
        } catch (\Throwable $e) {}
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Dev\\Livewire';
        $prefix = 'dev';

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

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Overview
            $registry->register(new \Platform\Dev\Tools\DevOverviewTool());

            // Package CRUD
            $registry->register(new \Platform\Dev\Tools\ListPackagesTool());
            $registry->register(new \Platform\Dev\Tools\GetPackageTool());
            $registry->register(new \Platform\Dev\Tools\ActivatePackageTool());
            $registry->register(new \Platform\Dev\Tools\UpdatePackageTool());
            $registry->register(new \Platform\Dev\Tools\DeactivatePackageTool());

            // Board CRUD
            $registry->register(new \Platform\Dev\Tools\ListBoardsTool());
            $registry->register(new \Platform\Dev\Tools\GetBoardTool());
            $registry->register(new \Platform\Dev\Tools\CreateBoardTool());
            $registry->register(new \Platform\Dev\Tools\UpdateBoardTool());
            $registry->register(new \Platform\Dev\Tools\DeleteBoardTool());
            $registry->register(new \Platform\Dev\Tools\ArchiveBoardTool());

            // Issue CRUD
            $registry->register(new \Platform\Dev\Tools\ListIssuesTool());
            $registry->register(new \Platform\Dev\Tools\GetIssueTool());
            $registry->register(new \Platform\Dev\Tools\CreateIssueTool());
            $registry->register(new \Platform\Dev\Tools\UpdateIssueTool());
            $registry->register(new \Platform\Dev\Tools\DeleteIssueTool());
            $registry->register(new \Platform\Dev\Tools\BulkCreateIssuesTool());

            // Discussion CRUD
            $registry->register(new \Platform\Dev\Tools\ListDiscussionsTool());
            $registry->register(new \Platform\Dev\Tools\GetDiscussionTool());
            $registry->register(new \Platform\Dev\Tools\CreateDiscussionTool());
            $registry->register(new \Platform\Dev\Tools\ReplyToDiscussionTool());

            // Error Tracking Debug
            $registry->register(new \Platform\Dev\Tools\ErrorTrackingDebugTool());

            // Documentation
            $registry->register(new \Platform\Dev\Tools\GetDocOverviewTool());
            $registry->register(new \Platform\Dev\Tools\GetDocPageTool());
            $registry->register(new \Platform\Dev\Tools\UpdateDocPageTool());
            $registry->register(new \Platform\Dev\Tools\CreateDocPageTool());
            $registry->register(new \Platform\Dev\Tools\DeleteDocPageTool());
            $registry->register(new \Platform\Dev\Tools\ListDocRevisionsTool());
            $registry->register(new \Platform\Dev\Tools\RestoreDocRevisionTool());
        } catch (\Throwable $e) {
            \Log::warning('Dev: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}

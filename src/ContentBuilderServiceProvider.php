<?php

namespace Reno\ContentBuilder;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Reno\Cms\Events\AdminApiRoutesRegistering;
use Reno\Cms\Events\FieldTypesRegistering;
use Reno\Cms\Events\Resources\ResourcesReindexing;
use Reno\ContentBuilder\Services\ContentBuilderSyncService;
use Reno\ContentBuilder\FieldTypes\ContentBuilderFieldType;
use Reno\ContentBuilder\Repositories\ContentBlockRepository;
use Reno\ContentBuilder\Http\Controllers\ContentBuilderController;
use Reno\ContentBuilder\Interfaces\ContentBlockRepositoryInterface;
use Reno\ContentBuilder\Interfaces\ContentBuilderRepositoryInterface;
use Reno\ContentBuilder\Listeners\AddContentBuilderDataToSearch;
use Reno\ContentBuilder\Repositories\ContentBuilderRepository;

class ContentBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentBuilderRepositoryInterface::class, ContentBuilderRepository::class);
        $this->app->singleton(ContentBlockRepositoryInterface::class, ContentBlockRepository::class);
        $this->app->singleton(ContentBuilderSyncService::class);

        Event::listen(FieldTypesRegistering::class, function (FieldTypesRegistering $event) {
            $event->addFieldType(new ContentBuilderFieldType());
        });

        Event::listen(AdminApiRoutesRegistering::class, function (AdminApiRoutesRegistering $event) {
            Route::get('/content-builder', [ContentBuilderController::class, 'index']);
        });

        Event::listen(ResourcesReindexing::class, AddContentBuilderDataToSearch::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'content-builder-migrations');

        $this->publishes([
            __DIR__ . '/../public/build' => public_path('js/reno/content-builder/build'),
        ], 'cms-assets');
    }
}

<?php

declare(strict_types=1);

namespace LynxCms\Media;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Content\PageFiles;
use LynxCms\Core\Events\PageDeleted;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Media\Actions\PruneMedia;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\AdminIntegration;
use LynxCms\Media\Console\PruneMediaCommand;
use LynxCms\Media\Fields\FilesType;
use LynxCms\Media\Fields\ImagesType;
use LynxCms\Media\View\ImageComponent;

/**
 * Images and files for Lynx (ADR-0046): the images and files field types, media.store and
 * media.prune, the lynx-image Blade component and, with the admin, the upload input.
 */
final class MediaServiceProvider extends ServiceProvider
{
    private const string ROOT = __DIR__.'/..';

    public function register(): void
    {
        $this->mergeConfigFrom(self::ROOT.'/config/lynx-media.php', 'lynx-media');
        $this->app->singleton(MediaPaths::class);
        $this->app->singleton(PageFiles::class, MediaPageFiles::class);
    }

    public function boot(): void
    {
        $this->app->make(FieldTypeRegistry::class)->register(ImagesType::class, FilesType::class);
        $this->app->make(ActionRegistry::class)->register(StoreMedia::class, PruneMedia::class);
        Event::listen(PageDeleted::class, DeleteMediaFolders::class);
        $this->loadViewsFrom(self::ROOT.'/resources/views', 'lynx-media');
        Blade::component('lynx-image', ImageComponent::class);

        if (class_exists(AdminServiceProvider::class)) {
            AdminIntegration::register();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneMediaCommand::class]);
            $this->publishes([self::ROOT.'/config/lynx-media.php' => config_path('lynx-media.php')], 'lynx-media-config');
        }

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('lynx:media:prune')->daily()->withoutOverlapping());
    }
}

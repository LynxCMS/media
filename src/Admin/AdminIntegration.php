<?php

declare(strict_types=1);

namespace LynxCms\Media\Admin;

use Livewire\Livewire;
use LynxCms\Admin\Editor\FieldInputs;
use LynxCms\Admin\Editor\LinkFiles;
use LynxCms\Media\Admin\Livewire\MediaField;

/**
 * The media fields' admin input, and the page's files for the rich text link dialog, registered
 * only when the admin package is installed.
 *
 * @internal
 */
final class AdminIntegration
{
    public static function register(): void
    {
        $inputs = app(FieldInputs::class);
        $inputs->register('images', 'lynx-media::inputs.media', MediaConverter::class);
        $inputs->register('files', 'lynx-media::inputs.media', MediaConverter::class);
        Livewire::component('lynx-media.field', MediaField::class);
        app(LinkFiles::class)->add((new PageMediaFiles)(...));
    }
}

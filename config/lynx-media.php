<?php

declare(strict_types=1);

return [

    /*
    | The Laravel disk that holds media files. Files of a page live in {root}/{page id}/; drafts
    | share their page's folder. Use a public disk (run php artisan storage:link) or a CDN disk.
    */
    'disk' => env('LYNX_MEDIA_DISK', 'public'),

    /*
    | The disk for files of private fields (a files or images field with "private" on, ADR-0077):
    | never public, served through /lynx-media/{page}/{file}, which asks the page's own questions.
    */
    'private_disk' => env('LYNX_MEDIA_PRIVATE_DISK', 'local'),

    'root' => 'lynx',

    /*
    | JPEG and WebP quality of variations, 1 to 100.
    */
    'quality' => (int) env('LYNX_MEDIA_QUALITY', 82),

    /*
    | Named sizes for $image->preset('card'): [width, height] (0 keeps the aspect ratio).
    */
    'presets' => [
        'thumb' => [320, 320],
        'card' => [800, 600],
        'hero' => [1920, 1080],
    ],

    /*
    | Uploaded files no value, draft or revision refers to are deleted by lynx:media:prune (run
    | daily by the scheduler) once older than this many hours.
    */
    'prune_after_hours' => 24,
];

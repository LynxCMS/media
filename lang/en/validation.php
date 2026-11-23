<?php

declare(strict_types=1);

return [
    'page' => 'The page does not exist.',
    'type' => 'Files of type [:type] are not allowed here. Allowed: :types.',
    'too_large' => 'The file is larger than this field allows.',
    'field' => 'Template [:template] has no images or files field [:field].',
    'source' => 'Give a url, or base64 content with a filename.',
    'pixels' => 'The image is too large to process (:width × :height).',
    'unreadable' => 'This is not an image Lynx can read.',
    'too_small' => 'The image is smaller than this field allows (:width × :height).',
    'download' => [
        'failed' => 'The file at [:url] could not be downloaded.',
        'scheme' => 'Only http and https URLs can be downloaded, not [:url].',
        'private' => 'The file at [:url] is not on a public web server, so it cannot be downloaded.',
        'status' => 'The file at [:url] could not be downloaded (HTTP :status).',
    ],
    'never' => 'Files of type [:types] can run code on your site, so a field can never allow them.',
    'max_files' => 'Only one file is allowed.|At most :count files are allowed.',
    'stored_name' => 'Each file needs its stored name, such as "42/photo.jpg" (media.store returns it).',
    'tag' => 'Tag [:tag] is not one of: :tags.',
];

<?php

declare(strict_types=1);

return [
    'page' => 'La página no existe.',
    'type' => 'Los archivos de tipo [:type] no se permiten aquí. Permitidos: :types.',
    'too_large' => 'El archivo es más grande de lo que permite este campo.',
    'field' => 'La plantilla [:template] no tiene un campo de imágenes o archivos [:field].',
    'source' => 'Indica una url, o contenido en base64 con un nombre de archivo (filename).',
    'pixels' => 'La imagen es demasiado grande para procesarla (:width × :height).',
    'unreadable' => 'Esto no es una imagen que Lynx pueda leer.',
    'too_small' => 'La imagen es más pequeña de lo que permite este campo (:width × :height).',
    'download' => [
        'failed' => 'No se pudo descargar el archivo en [:url].',
        'scheme' => 'Solo se pueden descargar URL http y https; [:url] no.',
        'private' => 'El archivo en [:url] no está en un servidor web público, así que no se puede descargar.',
        'status' => 'No se pudo descargar el archivo en [:url] (HTTP :status).',
    ],
    'never' => 'Los archivos de tipo [:types] pueden ejecutar código en tu sitio, así que un campo nunca puede permitirlos.',
    'max_files' => 'Solo se permite un archivo.|Se permiten como máximo :count archivos.',
    'stored_name' => 'Cada archivo necesita el nombre con el que se guardó, como "42/photo.jpg" (media.store lo devuelve).',
    'tag' => 'La etiqueta [:tag] no es una de estas: :tags.',
    'language' => 'Este sitio no tiene el idioma [:language].',
];

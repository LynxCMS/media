<?php

declare(strict_types=1);

return [
    'page' => 'A página não existe.',
    'type' => 'Os ficheiros do tipo [:type] não são permitidos aqui. Permitidos: :types.',
    'too_large' => 'O ficheiro é maior do que este campo permite.',
    'field' => 'O modelo [:template] não tem um campo de imagens ou ficheiros [:field].',
    'source' => 'Indique um url, ou conteúdo em base64 com um nome de ficheiro (filename).',
    'pixels' => 'A imagem é demasiado grande para processar (:width × :height).',
    'unreadable' => 'Isto não é uma imagem que o Lynx consiga ler.',
    'too_small' => 'A imagem é mais pequena do que este campo permite (:width × :height).',
    'download' => [
        'failed' => 'Não foi possível transferir o ficheiro em [:url].',
        'scheme' => 'Só é possível transferir URLs http e https; [:url] não.',
        'private' => 'O ficheiro em [:url] não está num servidor web público, por isso não pode ser transferido.',
        'status' => 'Não foi possível transferir o ficheiro em [:url] (HTTP :status).',
    ],
    'never' => 'Os ficheiros do tipo [:types] podem executar código no seu site, por isso um campo nunca os pode permitir.',
    'max_files' => 'Só é permitido um ficheiro.|São permitidos no máximo :count ficheiros.',
    'stored_name' => 'Cada ficheiro precisa do nome com que foi guardado, como "42/photo.jpg" (media.store devolve-o).',
    'tag' => 'A etiqueta [:tag] não é uma destas: :tags.',
    'language' => 'Este site não tem a língua [:language].',
];

<?
use Glory\Class\PostTypeManager;

PostTypeManager::define(
    'mision',
    [
        'has_archive' => true,
        'rewrite'     => ['slug' => 'misiones'],
        'menu_icon'   => 'dashicons-awards',
        'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    ],
    'Misión',
    'Misiones'
);

PostTypeManager::define(
    'repositorio',
    [
        'has_archive' => true,
        'rewrite'     => ['slug' => 'repositorios'],
        'menu_icon'   => 'dashicons-admin-git',
        'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    ],
    'Repositorio',
    'Repositorios'
);

PostTypeManager::define(
    'resumen',
    [
        'has_archive' => true,
        'rewrite'     => ['slug' => 'resumenes'],
        'menu_icon'   => 'dashicons-clipboard',
        'supports'    => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    ],
    'Resumen',
    'Resúmenes'
);

PostTypeManager::register();
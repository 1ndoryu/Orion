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

PostTypeManager::register();


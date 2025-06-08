<?php 

use Glory\Core\PageManager;
use Glory\Core\ScriptManager;
use Glory\Core\StyleManager;

ScriptManager::setGlobalDevMode(true);
ScriptManager::setThemeVersion('0.1.2');

StyleManager::setGlobalDevMode(true);
StyleManager::setThemeVersion('0.1.2');

ScriptManager::defineFolder('/js');
StyleManager::defineFolder('/assets/css');

PageManager::define('home');

ScriptManager::register();
StyleManager::register();
PageManager::register();


function themeSetup()
{
    # Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
}

add_action('after_setup_theme', 'themeSetup');

function themeEnqueueStyles()
{
    wp_enqueue_style('themeStyle', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'themeEnqueueStyles');

add_filter('show_admin_bar', '__return_false');

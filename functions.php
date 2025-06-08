<?php

$directorioTemaActivo = get_stylesheet_directory();



$autoloader = get_template_directory() . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    error_log('Error: Composer autoload no encontrado. Ejecuta "composer install".');
}

$glory_loader = get_template_directory() . '/Glory/load.php';
if (file_exists($glory_loader)) {
    require_once $glory_loader;
} else {
    error_log('Error: Glory Framework loader no encontrado.');
}

require_once __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    error_log('Error al cargar el archivo .env: ' . $e->getMessage());
}


function incluirArchivos($directorio)
{
    $ruta_completa = get_template_directory() . "/$directorio";

    $archivos = glob($ruta_completa . "*.php");
    foreach ($archivos as $archivo) {
        if (strpos($archivo, 'Glory/Class/ServidorChat.php') === false) {
            include_once $archivo;
        }
    }

    $subdirectorios = glob($ruta_completa . "*/", GLOB_ONLYDIR);
    foreach ($subdirectorios as $subdirectorio) {
        $ruta_relativa = str_replace(get_template_directory() . '/', '', $subdirectorio);
        incluirArchivos($ruta_relativa);
    }
}

$directorios = [
    'App/',
    'Glory/',

];

foreach ($directorios as $directorio) {
    incluirArchivos($directorio);
}


function fuentes()
{
    $theme_url = get_template_directory_uri();
    echo '<link rel="preload" href="' . $theme_url . '/assets/fonts/SourceSans3-Regular.woff2" as="font" type="font/woff2" crossorigin>';
    echo '<link rel="preload" href="' . $theme_url . '/assets/fonts/SourceSans3-Bold.woff2" as="font" type="font/woff2" crossorigin>';
}
add_action('wp_head', 'fuentes', 1);

/*
function activarModoMantenimiento()
{

    $titulo = 'Mantenimiento';
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>$titulo</title>
	<style>
		body {
			background-color: #050505;
		}
        html {
    height: 100%;
    background: #050505;
    display: flex;
        align-items: center;
        justify-content: center;
    }
    body#error-page {
        border: unset;
    }
    body {
        background-color: #050505;
        border: unset !important;
        box-shadow: unset;
    }
	</style>
</head>
<body>
	<h1>2upra pronto volverá y será genial</h1>
</body>
</html>
HTML;

    wp_die($html, $titulo, ['response' => 503, 'back_link' => false]);
}

add_action('template_redirect', 'activarModoMantenimiento');
*/
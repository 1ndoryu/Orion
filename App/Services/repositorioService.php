<?php

use Glory\Class\ManejadorGit;

function clonarRepo()
{
    if (!is_user_logged_in()) {
        error_log('clonarRepo: Acceso denegado. Usuario no logueado.');
        wp_send_json_error(['mensaje' => 'Acceso denegado. Debes iniciar sesión.'], 403);
    }

    $idUsuario = get_current_user_id();

    $urlRepoPorDefecto     = 'https://github.com/1ndoryu/Glory.git';
    $ramaTrabajoPorDefecto = 'main';

    $urlRepo = get_user_meta($idUsuario, 'metaRepo', true);
    if (empty($urlRepo) || !filter_var($urlRepo, FILTER_VALIDATE_URL)) {
        $urlRepo = $urlRepoPorDefecto;
    }

    $ramaTrabajo = get_user_meta($idUsuario, 'ramaTrabajo', true);
    if (empty($ramaTrabajo)) {
        $ramaTrabajo = $ramaTrabajoPorDefecto;
    }

    $nombreRepoBase = basename(parse_url($urlRepo, PHP_URL_PATH), '.git');

    $nombreRepoUnico      = $nombreRepoBase . '_' . $idUsuario;
    $rutaBaseRepositorios = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'repositorios';
    $rutaLocalRepo        = $rutaBaseRepositorios . DIRECTORY_SEPARATOR . $nombreRepoUnico;

    if (!is_dir($rutaBaseRepositorios)) {
        if (!wp_mkdir_p($rutaBaseRepositorios)) {
            error_log('clonarRepo: Error al crear directorio de repositorios: ' . $rutaBaseRepositorios);
            wp_send_json_error(['mensaje' => 'Error al configurar el directorio de repositorios.'], 500);
        }
    }

    try {
        $manejadorGit = new ManejadorGit();
    } catch (\Throwable $e) {
        error_log('Error al instanciar ManejadorGit: ' . $e->getMessage());
        wp_send_json_error(['mensaje' => 'Error interno del servidor (GIT_INIT_FAIL).'], 500);
    }

    $exito = $manejadorGit->clonarOActualizarRepo($urlRepo, $rutaLocalRepo, $ramaTrabajo);

    if ($exito) {
        update_user_meta($idUsuario, 'metaRutaLocalRepo', wp_normalize_path($rutaLocalRepo));
        update_user_meta($idUsuario, 'metaUltimaClonacionExitosa', current_time('timestamp'));
        update_user_meta($idUsuario, 'metaRepoClonadoUrl', $urlRepo);
        update_user_meta($idUsuario, 'metaRepoClonadoBranch', $ramaTrabajo);

        wp_send_json_success([
            'mensaje'          => "El repositorio '{$nombreRepoBase}' ha sido clonado/actualizado correctamente.",
            'rutaRepositorio'  => $rutaLocalRepo,
            'urlRepositorio'   => $urlRepo,
            'ramaSeleccionada' => $ramaTrabajo
        ]);
    } else {
        error_log("clonarRepo: Fallo al clonar/actualizar repo '{$urlRepo}' en '{$rutaLocalRepo}'. Rama: '{$ramaTrabajo}'.");
        wp_send_json_error([
            'mensaje'        => "Error al clonar o actualizar el repositorio '{$nombreRepoBase}'. Consulte los registros del sistema para detalles.",
            'urlRepositorio' => $urlRepo,
            'rutaIntentada'  => $rutaLocalRepo
        ], 500);
    }
}

add_action('wp_ajax_clonarRepo', 'clonarRepo');

function listarArchivosRepositorio($rutaRepositorio)
{
    if (!is_dir($rutaRepositorio) || !is_readable($rutaRepositorio)) {
        error_log("listarArchivosRepositorio: La ruta proporcionada no es un directorio válido o no es legible: {$rutaRepositorio}");
        return null;
    }

    $archivosRepositorio = [];
    $longitudRutaBase = strlen(rtrim($rutaRepositorio, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

    try {
        $iteradorDirectorio = new RecursiveDirectoryIterator(
            $rutaRepositorio,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::UNIX_PATHS
        );

        $iteradorRecursivo = new RecursiveIteratorIterator(
            $iteradorDirectorio,
            RecursiveIteratorIterator::SELF_FIRST
        );

        $ignorarPatrones = [
            '.git',
            'node_modules/',
            'vendor/',
            '__pycache__',
            '.idea/',
            '.vscode/',
            '.DS_Store',
            '*.log',
            '*.tmp',
            '*.bak',
            '*.swp',
            '*.swo'
        ];

        foreach ($iteradorRecursivo as $item) {
            $rutaCompletaItem = $item->getPathname();
            $rutaRelativaItem = substr($rutaCompletaItem, $longitudRutaBase);

            // Check if the item should be ignored
            foreach ($ignorarPatrones as $patron) {
                if (fnmatch($patron, $rutaRelativaItem, FNM_PATHNAME)) {
                    continue 2;
                }
            }

            if ($item->isFile()) {
                $archivosRepositorio[] = $rutaRelativaItem;
            }
        }
    } catch (UnexpectedValueException $e) {
        error_log("listarArchivosRepositorio: Error al iterar el directorio {$rutaRepositorio}: " . $e->getMessage());
        return null;
    }

    sort($archivosRepositorio); // Opcional: ordenar la lista de archivos
    return $archivosRepositorio;
}

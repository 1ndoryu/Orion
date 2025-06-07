<?php
use Glory\Class\PostActionManager;

/**
 * Gestiona la creación de una misión de forma iterativa, enviando feedback
 * en tiempo real al frontend sobre los archivos que se están analizando.
 * MODIFICADO: Ahora crea un Custom Post Type 'mision'.
 */
function comandoCrearMision($idUsuario, $instruccionOriginal, $motivoDetectadoPorIa, &$promptsEnviados, &$respuestasIaIntermedias)
{
    // Ya no se necesita obtener las misiones del user_meta.
    // $misionesUsuario = get_user_meta($idUsuario, 'iaMisionesUsuario', true); ...

    $rutaRepositorio = get_user_meta($idUsuario, 'metaRutaLocalRepo', true);

    if (empty($rutaRepositorio) || !is_dir($rutaRepositorio)) {
        wp_send_json_error([
            'mensaje' => 'Para crear una misión, primero necesitas clonar un repositorio.',
            'comandoReconocido' => 'crearMision',
            'requiereClonacion' => true,
        ]);
        return true;
    }

    $archivosRepoOriginales = listarArchivosRepositorio($rutaRepositorio);
    $archivosRepo = array_filter($archivosRepoOriginales, function ($rutaArchivo) {
        if (strpos($rutaArchivo, '.git/') === 0 || $rutaArchivo === '.git') return false;
        if (substr($rutaArchivo, -7) === '.sample') return false;
        return true;
    });
    $archivosRepo = array_values($archivosRepo);

    if (empty($archivosRepo)) {
        wp_send_json_error(['mensaje' => 'El repositorio está vacío o no contiene archivos relevantes.']);
        return true;
    }
    
    $contextoArchivos = [];
    $archivosAnalizadosRutas = [];
    $contextoTemporal = get_user_meta($idUsuario, 'iaContextoMisionTemporal', true);

    if (!empty($contextoTemporal) && is_array($contextoTemporal['archivosAnalizados'])) {
        $archivosAnalizadosRutas = $contextoTemporal['archivosAnalizados'];
        $respuestasIaIntermedias['contextoRecuperado'] = 'Contexto de intento anterior recuperado. Archivos ya analizados: ' . implode(', ', $archivosAnalizadosRutas);
        foreach ($archivosAnalizadosRutas as $rutaArchivo) {
            $rutaCompleta = $rutaRepositorio . '/' . $rutaArchivo;
            if (is_readable($rutaCompleta)) {
                $contextoArchivos[$rutaArchivo] = file_get_contents($rutaCompleta);
            }
        }
    }
    
    $listaArchivosCompletaParaIa = implode("\n", $archivosRepo);

    for ($i = count($archivosAnalizadosRutas) + 1; $i <= 4; $i++) {
        $contextoPrevioParaPrompt = !empty($archivosAnalizadosRutas)
            ? "Ya he analizado los siguientes archivos: " . implode(', ', $archivosAnalizadosRutas) . ". No los sugieras de nuevo. "
            : "";

        $promptSeleccion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\". Intención: \"{$motivoDetectadoPorIa}\". {$contextoPrevioParaPrompt}\n"
            . "Tarea: De la siguiente lista, ¿cuál es el SIGUIENTE archivo más relevante a examinar?\n\n"
            . "LISTA DE ARCHIVOS:\n{$listaArchivosCompletaParaIa}\n\n"
            . "Si tienes suficiente contexto, responde con 'contextoSuficiente': true. Si no, con la ruta en un JSON con la clave 'archivoAAnalizar'.";

        $promptsEnviados["seleccionArchivo_Paso{$i}"] = $promptSeleccion;
        $respuestaSeleccion = api($promptSeleccion, null, null, true);
        $respuestasIaIntermedias["seleccionArchivo_Paso{$i}"] = $respuestaSeleccion;

        if (isset($respuestaSeleccion['contextoSuficiente']) && $respuestaSeleccion['contextoSuficiente'] === true) {
            break;
        }

        $archivoAAnalizar = $respuestaSeleccion['archivoAAnalizar'] ?? null;

        if (!$archivoAAnalizar || !in_array($archivoAAnalizar, $archivosRepo) || isset($contextoArchivos[$archivoAAnalizar])) {
            break;
        }

        $rutaCompletaArchivo = $rutaRepositorio . '/' . $archivoAAnalizar;
        if (is_readable($rutaCompletaArchivo)) {
            $respuestasIaIntermedias["estadoAnalisis_Paso{$i}"] = "Analizando archivo: {$archivoAAnalizar}...";
            
            $contextoArchivos[$archivoAAnalizar] = file_get_contents($rutaCompletaArchivo);
            $archivosAnalizadosRutas[] = $archivoAAnalizar;
        } else {
            break;
        }
    }

    if (empty($contextoArchivos)) {
        delete_user_meta($idUsuario, 'iaContextoMisionTemporal');
        wp_send_json_error([
            'mensaje' => 'La IA no pudo identificar archivos relevantes. Por favor, sé más específico.',
            'requiereEspecificacionArchivos' => true,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }

    $contextoParaPromptFinal = '';
    foreach ($contextoArchivos as $ruta => $contenido) {
        $contextoParaPromptFinal .= "--- INICIO CONTENIDO: {$ruta} ---\n" . substr($contenido, 0, 25000) . "\n--- FIN CONTENIDO: {$ruta} ---\n\n";
    }

    $promptDefinicion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\".\n\n"
        . "He analizado los siguientes archivos:\n" . implode("\n", $archivosAnalizadosRutas) . "\n\n"
        . "CONTENIDO DE ARCHIVOS:\n{$contextoParaPromptFinal}\n"
        . "Tarea: Define la misión en un JSON con: 'nombreMision', 'objetivoMision', 'pasosSugeridos'.";
    
    $promptsEnviados['definicionMisionFinal'] = $promptDefinicion;
    $datosMisionIa = api($promptDefinicion, null, null, true);
    $respuestasIaIntermedias['definicionMisionFinal'] = $datosMisionIa;

    if ($datosMisionIa === null || !isset($datosMisionIa['objetivoMision'])) {
        if (!empty($archivosAnalizadosRutas)) {
            update_user_meta($idUsuario, 'iaContextoMisionTemporal', ['archivosAnalizados' => $archivosAnalizadosRutas]);
        }
        wp_send_json_error([
            'mensaje' => 'La IA no pudo definir la misión. El progreso ha sido guardado. Inténtalo de nuevo.',
            'errorDefinicionFinal' => true,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }
    
    // ** INICIO: Bloque modificado para crear CPT 'mision' **
    $nombreMision = $datosMisionIa['nombreMision'] ?? 'Misión Generada por IA ' . time();
    $objetivoMision = $datosMisionIa['objetivoMision'];
    
    $datosParaPost = [
        'post_title'   => $nombreMision,
        'post_content' => $objetivoMision,
        'post_status'  => 'publish', // o 'draft' si prefieres
        'post_author'  => $idUsuario,
        'meta_input'   => [
            'archivoPrincipal'             => $archivosAnalizadosRutas[0] ?? null,
            'archivosSecundarios'          => array_slice($archivosAnalizadosRutas, 1),
            'pasosSugeridos'               => $datosMisionIa['pasosSugeridos'] ?? [],
            'estado'                       => 'definida',
            'instruccionOriginalUsuario'   => $instruccionOriginal,
            'motivoDetectadoPorIaOriginal' => $motivoDetectadoPorIa,
            // 'fechaCreacion' no es necesario, el post ya tiene su fecha.
            'logIaDefinicion'              => $datosMisionIa,
        ],
    ];

    $idPostMision = PostActionManager::crearPost('mision', $datosParaPost);

    if (is_wp_error($idPostMision) || $idPostMision === 0) {
        wp_send_json_error([
            'mensaje' => 'Hubo un error al guardar la misión en la base de datos.',
            'detallesError' => is_wp_error($idPostMision) ? $idPostMision->get_error_message() : 'La función crearPost devolvió 0.',
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }
    // ** FIN: Bloque modificado **

    delete_user_meta($idUsuario, 'iaContextoMisionTemporal');

    // Preparar la respuesta con los datos del nuevo post creado.
    $misionCreada = [
        'idMision' => $idPostMision,
        'nombreMision' => $nombreMision,
        'objetivoMision' => $objetivoMision,
        'meta' => $datosParaPost['meta_input']
    ];

    wp_send_json_success([
        'mensaje' => "¡Misión creada con ID #{$idPostMision} tras analizar " . count($archivosAnalizadosRutas) . " archivo(s)!",
        'mision' => $misionCreada,
        'prompts' => $promptsEnviados,
        'respuestasIaIntermedias' => $respuestasIaIntermedias,
    ]);
    return true;
}

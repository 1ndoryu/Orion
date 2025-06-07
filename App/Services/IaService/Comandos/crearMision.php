<?php

use Glory\Class\PostActionManager;

/**
 * Gestiona la creación de una misión, utilizando un sistema de caché inteligente para los resúmenes de archivos
 * que invalida y elimina resúmenes obsoletos cuando el contenido de un archivo cambia.
 */
function comandoCrearMision($idUsuario, $instruccionOriginal, $motivoDetectadoPorIa, &$promptsEnviados, &$respuestasIaIntermedias)
{
    // Obtener datos clave del repositorio desde las metas del usuario.
    $idPostRepositorio = get_user_meta($idUsuario, 'metaIdPostRepositorio', true);
    $rutaRepositorio = get_user_meta($idUsuario, 'metaRutaLocalRepo', true);

    if (empty($idPostRepositorio) || empty($rutaRepositorio) || !is_dir($rutaRepositorio)) {
        error_log("ComandoCrearMision: No se encontró ID de repositorio o ruta para el usuario ID: $idUsuario");
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
        error_log("ComandoCrearMision: El repositorio en $rutaRepositorio está vacío o sin archivos relevantes.");
        wp_send_json_error(['mensaje' => 'El repositorio está vacío o no contiene archivos relevantes.']);
        return true;
    }

    $archivosAnalizadosDetalles = [];
    $resumenesContexto = '';
    $archivosYaSeleccionadosRutas = [];

    for ($i = 1; $i <= 4; $i++) {
        $archivosDisponibles = array_diff($archivosRepo, $archivosYaSeleccionadosRutas);
        if (empty($archivosDisponibles)) {
            $respuestasIaIntermedias["estadoAnalisis_Paso{$i}"] = "No hay más archivos para analizar.";
            break;
        }

        $listaArchivosParaIa = implode("\n", $archivosDisponibles);
        $contextoPrevioParaPrompt = !empty($resumenesContexto) ? "Ya he analizado otros archivos y he extraído los siguientes resúmenes:\n{$resumenesContexto}\n" : "";

        $promptSeleccion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\". Intención: \"{$motivoDetectadoPorIa}\".\n"
            . "{$contextoPrevioParaPrompt}"
            . "Tarea: Basado en el contexto y los resúmenes, ¿cuál es el SIGUIENTE archivo más relevante a examinar de la lista para cumplir la instrucción? "
            . "Si crees que tienes suficiente contexto con los archivos ya resumidos, responde solo con un JSON `{\"contextoSuficiente\": true}`. "
            . "De lo contrario, responde con un JSON que contenga la clave 'archivoAAnalizar' con la ruta completa del archivo seleccionado.\n\n"
            . "LISTA DE ARCHIVOS DISPONIBLES:\n{$listaArchivosParaIa}\n\n";

        $promptsEnviados["seleccionArchivo_Paso{$i}"] = $promptSeleccion;
        $respuestaSeleccion = api($promptSeleccion, null, null, true);
        $respuestasIaIntermedias["seleccionArchivo_Paso{$i}"] = $respuestaSeleccion;

        if (isset($respuestaSeleccion['contextoSuficiente']) && $respuestaSeleccion['contextoSuficiente'] === true) {
            $respuestasIaIntermedias["estadoAnalisis_Paso{$i}"] = "La IA determinó que el contexto es suficiente.";
            break;
        }

        $archivoAAnalizar = $respuestaSeleccion['archivoAAnalizar'] ?? null;
        if (!$archivoAAnalizar || !in_array($archivoAAnalizar, $archivosRepo)) {
            error_log("ComandoCrearMision: La IA devolvió un archivo inválido o nulo en el paso $i: " . print_r($archivoAAnalizar, true));
            break;
        }

        $rutaCompletaArchivo = $rutaRepositorio . '/' . $archivoAAnalizar;
        if (is_readable($rutaCompletaArchivo)) {
            $archivosYaSeleccionadosRutas[] = $archivoAAnalizar;
            $contenidoArchivo = file_get_contents($rutaCompletaArchivo);
            $hashContenidoActual = hash('sha256', $contenidoArchivo);

            // --- INICIO LÓGICA DE CACHÉ MEJORADA ---
            $resumenesPrevios = get_posts([
                'post_type' => 'resumen',
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'idPostRepositorio', 'value' => $idPostRepositorio],
                    ['key' => 'rutaArchivo', 'value' => $archivoAAnalizar],
                ],
            ]);

            $resumenCacheado = !empty($resumenesPrevios) ? $resumenesPrevios[0] : null;
            $hashCacheado = $resumenCacheado ? get_post_meta($resumenCacheado->ID, 'hashContenido', true) : null;

            if ($resumenCacheado && $hashCacheado === $hashContenidoActual) {
                // CACHE HIT: El hash coincide, usamos el resumen de la caché.
                $resumen = $resumenCacheado->post_content;
                $respuestasIaIntermedias["cache_Paso{$i}"] = "HIT para {$archivoAAnalizar}";
            } else {
                // CACHE MISS o STALE (obsoleto): No hay resumen o el contenido del archivo ha cambiado.
                if ($resumenCacheado) {
                    // El hash no coincide. Borramos el resumen antiguo para mantener la limpieza.
                    wp_delete_post($resumenCacheado->ID, true); // true for forzar borrado permanente
                    $respuestasIaIntermedias["cache_Paso{$i}"] = "STALE (eliminado y regenerado) para {$archivoAAnalizar}";
                } else {
                    $respuestasIaIntermedias["cache_Paso{$i}"] = "MISS para {$archivoAAnalizar}";
                }

                $promptResumen = "Contexto: Estoy tratando de cumplir esta instrucción de usuario: \"{$instruccionOriginal}\".\n"
                    . "Tarea: Has elegido este archivo para analizar. Resume su propósito y las funciones clave que contiene. "
                    . "Este resumen servirá de contexto para decidir qué archivo analizar a continuación. Sé conciso y técnico. "
                    . "Responde únicamente con un JSON que contenga la clave 'resumen'.\n\n"
                    . "--- INICIO CONTENIDO: {$archivoAAnalizar} ---\n" . substr($contenidoArchivo, 0, 25000) . "\n--- FIN CONTENIDO ---";

                $promptsEnviados["resumenArchivo_Paso{$i}"] = $promptResumen;
                $respuestaResumen = api($promptResumen, null, null, true);
                $respuestasIaIntermedias["resumenArchivo_Paso{$i}"] = $respuestaResumen;
                $resumen = $respuestaResumen['resumen'] ?? 'La IA no pudo generar un resumen para este archivo.';

                // Guardar el nuevo resumen en la caché (nuevo CPT 'resumen').
                PostActionManager::crearPost('resumen', [
                    'post_title'   => $archivoAAnalizar,
                    'post_content' => $resumen,
                    'post_author'  => $idUsuario,
                    'meta_input'   => [
                        'idPostRepositorio' => $idPostRepositorio,
                        'rutaArchivo'       => $archivoAAnalizar,
                        'hashContenido'     => $hashContenidoActual,
                    ],
                ]);
            }
            // --- FIN LÓGICA DE CACHÉ MEJORADA ---

            $archivosAnalizadosDetalles[] = ['ruta' => $archivoAAnalizar, 'resumen' => $resumen];
            $resumenesContexto .= "- Archivo `{$archivoAAnalizar}`: {$resumen}\n";
        } else {
            error_log("ComandoCrearMision: No se pudo leer el archivo seleccionado por la IA: {$rutaCompletaArchivo}");
            $respuestasIaIntermedias["errorAnalisis_Paso{$i}"] = "No se pudo leer el archivo: {$archivoAAnalizar}";
            break;
        }
    }

    if (empty($archivosAnalizadosDetalles)) {
        wp_send_json_error([
            'mensaje' => 'La IA no pudo identificar y analizar archivos relevantes. Por favor, sé más específico.',
            'requiereEspecificacionArchivos' => true,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }

    $promptDefinicion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\".\n\n"
        . "He analizado los siguientes archivos y he generado estos resúmenes:\n{$resumenesContexto}\n\n"
        . "Tarea: Basado en TODA la información anterior, define la misión. Responde con un único JSON que contenga estas claves: "
        . "'nombreMision', 'objetivoMision', 'pasosSugeridos' (array de strings).";

    $promptsEnviados['definicionMisionFinal'] = $promptDefinicion;
    $datosMisionIa = api($promptDefinicion, null, null, true);
    $respuestasIaIntermedias['definicionMisionFinal'] = $datosMisionIa;

    if ($datosMisionIa === null || !isset($datosMisionIa['objetivoMision']) || !isset($datosMisionIa['pasosSugeridos'])) {
        error_log("ComandoCrearMision: La IA no pudo definir la misión final. Respuesta recibida: " . print_r($datosMisionIa, true));
        wp_send_json_error([
            'mensaje' => 'La IA no pudo definir la misión con los datos analizados.',
            'errorDefinicionFinal' => true,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }

    $nombreMision = $datosMisionIa['nombreMision'] ?? 'Misión Generada por IA ' . time();
    $idPostMision = PostActionManager::crearPost('mision', [
        'post_title'   => $nombreMision,
        'post_content' => $datosMisionIa['objetivoMision'],
        'post_status'  => 'publish',
        'post_author'  => $idUsuario,
        'meta_input'   => [
            'archivosRelacionados'        => $archivosAnalizadosDetalles,
            'pasosSugeridos'              => $datosMisionIa['pasosSugeridos'] ?? [],
            'estado'                      => 'definida',
            'instruccionOriginalUsuario'  => $instruccionOriginal,
            'motivoDetectadoPorIaOriginal' => $motivoDetectadoPorIa,
            'logIaDefinicion'             => $datosMisionIa,
            'idPostRepositorioAsociado'   => $idPostRepositorio, // Guardamos la relación
        ],
    ]);

    if (is_wp_error($idPostMision) || $idPostMision === 0) {
        $errorMsg = is_wp_error($idPostMision) ? $idPostMision->get_error_message() : 'La función crearPost devolvió 0.';
        error_log("ComandoCrearMision: Error al guardar la misión en la BBDD. Detalles: " . $errorMsg);
        wp_send_json_error([
            'mensaje' => 'Hubo un error al guardar la misión en la base de datos.',
            'detallesError' => $errorMsg,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }

    $misionCreada = [
        'idMision'       => $idPostMision,
        'nombreMision'   => $nombreMision,
        'objetivoMision' => $datosMisionIa['objetivoMision'],
        'meta'           => ['pasosSugeridos' => $datosMisionIa['pasosSugeridos']]
    ];

    wp_send_json_success([
        'mensaje' => "¡Misión creada con ID #{$idPostMision} tras analizar " . count($archivosAnalizadosDetalles) . " archivo(s)!",
        'mision' => $misionCreada,
        'prompts' => $promptsEnviados,
        'respuestasIaIntermedias' => $respuestasIaIntermedias,
    ]);
    return true;
}

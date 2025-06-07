<?php
use Glory\Class\PostActionManager;

/**
 * Gestiona la creación de una misión de forma iterativa, generando resúmenes
 * en cadena para mejorar el contexto de la IA y guardando los resultados detallados.
 */
function comandoCrearMision($idUsuario, $instruccionOriginal, $motivoDetectadoPorIa, &$promptsEnviados, &$respuestasIaIntermedias)
{
    $rutaRepositorio = get_user_meta($idUsuario, 'metaRutaLocalRepo', true);

    if (empty($rutaRepositorio) || !is_dir($rutaRepositorio)) {
        error_log("ComandoCrearMision: No se encontró la ruta del repositorio para el usuario ID: $idUsuario");
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

    $archivosAnalizadosDetalles = []; // Almacenará {ruta, resumen}
    $resumenesContexto = '';
    $archivosYaSeleccionadosRutas = [];
    $listaArchivosCompletaParaIa = implode("\n", $archivosRepo);

    for ($i = 1; $i <= 4; $i++) {
        $archivosDisponibles = array_diff($archivosRepo, $archivosYaSeleccionadosRutas);
        if (empty($archivosDisponibles)) {
            $respuestasIaIntermedias["estadoAnalisis_Paso{$i}"] = "No hay más archivos para analizar.";
            break;
        }
        $listaArchivosParaIa = implode("\n", $archivosDisponibles);

        $contextoPrevioParaPrompt = !empty($resumenesContexto)
            ? "Ya he analizado otros archivos y he extraído los siguientes resúmenes:\n{$resumenesContexto}\n"
            : "";

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
            $respuestasIaIntermedias["errorAnalisis_Paso{$i}"] = "La IA no pudo seleccionar un archivo válido en este paso.";
            break;
        }

        $rutaCompletaArchivo = $rutaRepositorio . '/' . $archivoAAnalizar;
        if (is_readable($rutaCompletaArchivo)) {
            $respuestasIaIntermedias["estadoAnalisis_Paso{$i}"] = "Analizando y resumiendo archivo: {$archivoAAnalizar}...";
            $archivosYaSeleccionadosRutas[] = $archivoAAnalizar;
            $contenidoArchivo = file_get_contents($rutaCompletaArchivo);

            $promptResumen = "Contexto: Estoy tratando de cumplir esta instrucción de usuario: \"{$instruccionOriginal}\".\n"
                . "Tarea: Has elegido este archivo para analizar. Resume su propósito y las funciones clave que contiene. "
                . "Este resumen servirá de contexto para decidir qué archivo analizar a continuación. Sé conciso y técnico. "
                . "Responde únicamente con un JSON que contenga la clave 'resumen'.\n\n"
                . "--- INICIO CONTENIDO: {$archivoAAnalizar} ---\n" . substr($contenidoArchivo, 0, 25000) . "\n--- FIN CONTENIDO: {$archivoAAnalizar} ---";
            
            $promptsEnviados["resumenArchivo_Paso{$i}"] = $promptResumen;
            $respuestaResumen = api($promptResumen, null, null, true);
            $respuestasIaIntermedias["resumenArchivo_Paso{$i}"] = $respuestaResumen;
            
            $resumen = $respuestaResumen['resumen'] ?? 'La IA no pudo generar un resumen para este archivo.';
            
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
        . "'nombreMision' (un título breve y descriptivo), "
        . "'objetivoMision' (una descripción clara de lo que se debe lograr), "
        . "'pasosSugeridos' (un array de strings con los pasos técnicos para completar la misión).";
    
    $promptsEnviados['definicionMisionFinal'] = $promptDefinicion;
    $datosMisionIa = api($promptDefinicion, null, null, true);
    $respuestasIaIntermedias['definicionMisionFinal'] = $datosMisionIa;

    if ($datosMisionIa === null || !isset($datosMisionIa['objetivoMision']) || !isset($datosMisionIa['pasosSugeridos'])) {
        error_log("ComandoCrearMision: La IA no pudo definir la misión final. Respuesta recibida: " . print_r($datosMisionIa, true));
        wp_send_json_error([
            'mensaje' => 'La IA no pudo definir la misión con los datos analizados. Inténtalo de nuevo, quizás con una instrucción más clara.',
            'errorDefinicionFinal' => true,
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias,
        ]);
        return true;
    }
    
    $nombreMision = $datosMisionIa['nombreMision'] ?? 'Misión Generada por IA ' . time();
    $objetivoMision = $datosMisionIa['objetivoMision'];
    $pasosSugeridos = $datosMisionIa['pasosSugeridos'] ?? [];
    
    $datosParaPost = [
        'post_title'   => $nombreMision,
        'post_content' => $objetivoMision,
        'post_status'  => 'publish',
        'post_author'  => $idUsuario,
        'meta_input'   => [
            'archivosRelacionados'         => $archivosAnalizadosDetalles,
            'pasosSugeridos'               => $pasosSugeridos,
            'estado'                       => 'definida',
            'instruccionOriginalUsuario'   => $instruccionOriginal,
            'motivoDetectadoPorIaOriginal' => $motivoDetectadoPorIa,
            'logIaDefinicion'              => $datosMisionIa,
        ],
    ];

    $idPostMision = PostActionManager::crearPost('mision', $datosParaPost);

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
        'objetivoMision' => $objetivoMision,
        'meta'           => $datosParaPost['meta_input']
    ];

    wp_send_json_success([
        'mensaje' => "¡Misión creada con ID #{$idPostMision} tras analizar " . count($archivosAnalizadosDetalles) . " archivo(s)!",
        'mision' => $misionCreada,
        'prompts' => $promptsEnviados,
        'respuestasIaIntermedias' => $respuestasIaIntermedias,
    ]);
    return true;
}

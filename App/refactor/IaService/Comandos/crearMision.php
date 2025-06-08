<?php
// App/Services/IaService/Comandos/crearMision.php
use Glory\Class\PostActionManager;

/**
 * Orquesta el proceso de creación de una misión.
 * Coordina la preparación, el análisis de archivos, la definición por IA y el guardado.
 */
function comandoCrearMision($idUsuario, $instruccionOriginal, $motivoDetectadoPorIa)
{
    # 1. Preparar contexto y validar repositorio
    $preparacion = prepararContextoMision($idUsuario);
    if (!$preparacion['success']) {
        #Depuración detallada
        enviarMensajeSocket($idUsuario, array_merge($preparacion, ['comandoReconocido' => 'crearMision']));
        return true;
    }
    $idPostRepositorio = $preparacion['idPostRepositorio'];
    $rutaRepositorio = $preparacion['rutaRepositorio'];

    # 2. Analizar archivos relevantes con la IA para obtener contexto
    $analisis = analizarArchivosRelevantes($idUsuario, $idPostRepositorio, $rutaRepositorio, $preparacion['archivosRepo'], $instruccionOriginal, $motivoDetectadoPorIa);
    if (!$analisis['success']) {
        #Depuración detallada
        enviarMensajeSocket($idUsuario, array_merge($analisis, ['requiereEspecificacionArchivos' => true]));
        return true;
    }

    # 3. Definir la misión usando el contexto recopilado
    $definicion = definirMisionConIA($idUsuario, $instruccionOriginal, $analisis['resumenesContexto']);
    if (!$definicion['success']) {
        #Depuración detallada
        enviarMensajeSocket($idUsuario, array_merge($definicion, ['errorDefinicionFinal' => true]));
        return true;
    }
    $datosMisionIa = $definicion['datos'];

    # 4. Guardar la misión en la base de datos
    $resultadoGuardado = guardarMisionEnBD($idUsuario, $idPostRepositorio, $datosMisionIa, $analisis['archivosAnalizadosDetalles'], $instruccionOriginal, $motivoDetectadoPorIa);
    if (!$resultadoGuardado['success']) {
        #Depuración detallada
        enviarMensajeSocket($idUsuario, $resultadoGuardado);
        return true;
    }

    # 5. Enviar confirmación final al usuario
    enviarMensajeSocket($idUsuario, [
        'success' => true,
        'mensaje' => "¡Misión creada con éxito! ID #{$resultadoGuardado['idMision']}.",
        'mision' => $resultadoGuardado['mision'],
        'tipo' => 'resultado-final'
    ]);

    return true;
}

/**
 * Valida los requisitos iniciales, obtiene los datos del repositorio y lista los archivos.
 * @return array ['success', 'mensaje', 'idPostRepositorio', 'rutaRepositorio', 'archivosRepo']
 */
function prepararContextoMision($idUsuario)
{
    $idPostRepositorio = get_user_meta($idUsuario, 'metaIdPostRepositorio', true);
    $rutaRepositorio = get_user_meta($idUsuario, 'metaRutaLocalRepo', true);

    if (empty($idPostRepositorio) || empty($rutaRepositorio) || !is_dir($rutaRepositorio)) {
        return ['success' => false, 'mensaje' => 'Para crear una misión, primero necesitas clonar un repositorio.', 'requiereClonacion' => true];
    }

    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'Analizando repositorio...']);
    $archivosRepo = array_values(array_filter(listarArchivosRepositorio($rutaRepositorio), function ($rutaArchivo) {
        return strpos($rutaArchivo, '.git/') === false && $rutaArchivo !== '.git' && substr($rutaArchivo, -7) !== '.sample';
    }));

    if (empty($archivosRepo)) {
        return ['success' => false, 'mensaje' => 'El repositorio está vacío o no contiene archivos relevantes.'];
    }

    return [
        'success' => true,
        'idPostRepositorio' => $idPostRepositorio,
        'rutaRepositorio' => $rutaRepositorio,
        'archivosRepo' => $archivosRepo
    ];
}

/**
 * Itera para seleccionar y resumir los archivos más relevantes usando la IA.
 * @return array ['success', 'mensaje', 'archivosAnalizadosDetalles', 'resumenesContexto']
 */
function analizarArchivosRelevantes($idUsuario, $idPostRepositorio, $rutaRepositorio, $archivosRepo, $instruccionOriginal, $motivoDetectadoPorIa)
{
    $archivosAnalizadosDetalles = [];
    $resumenesContexto = '';
    $archivosYaSeleccionadosRutas = [];

    for ($i = 1; $i <= 4; $i++) {
        $archivosDisponibles = array_diff($archivosRepo, $archivosYaSeleccionadosRutas);
        if (empty($archivosDisponibles)) {
            #Depuración detallada
            enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'No hay más archivos para analizar.']);
            break;
        }

        #Depuración detallada
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => "Paso {$i}: Solicitando a la IA la selección del archivo más relevante..."]);
        $contextoPrevioParaPrompt = !empty($resumenesContexto) ? "Ya he analizado otros archivos y he extraído los siguientes resúmenes:\n{$resumenesContexto}\n" : "";
        $promptSeleccion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\". Intención: \"{$motivoDetectadoPorIa}\".\n{$contextoPrevioParaPrompt}Tarea: Basado en el contexto y los resúmenes, ¿cuál es el SIGUIENTE archivo más relevante a examinar de la lista para cumplir la instrucción? Si crees que tienes suficiente contexto con los archivos ya resumidos, responde solo con un JSON `{\"contextoSuficiente\": true}`. De lo contrario, responde con un JSON que contenga la clave 'archivoAAnalizar' con la ruta completa del archivo seleccionado.\n\nLISTA DE ARCHIVOS DISPONIBLES:\n" . implode("\n", $archivosDisponibles) . "\n\n";

        #Depuración detallada
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'prompt-sistema', 'mensaje' => $promptSeleccion]);
        $respuestaSeleccion = api($promptSeleccion, null, null, true);
        #Depuración detallada
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'respuesta-ia', 'mensaje' => $respuestaSeleccion]);

        if (isset($respuestaSeleccion['contextoSuficiente']) && $respuestaSeleccion['contextoSuficiente'] === true) {
            #Depuración detallada
            enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'La IA determinó que el contexto es suficiente.']);
            break;
        }

        $archivoAAnalizar = $respuestaSeleccion['archivoAAnalizar'] ?? null;
        if (!$archivoAAnalizar || !in_array($archivoAAnalizar, $archivosRepo)) {
            error_log("ComandoCrearMision: La IA devolvió un archivo inválido o nulo en el paso $i: " . print_r($archivoAAnalizar, true));
            break;
        }
        
        #Depuración detallada
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => "IA seleccionó: `{$archivoAAnalizar}`."]);
        $rutaCompletaArchivo = $rutaRepositorio . '/' . $archivoAAnalizar;

        if (!is_readable($rutaCompletaArchivo)) {
            #Depuración detallada
            enviarMensajeSocket($idUsuario, ['success' => false, 'tipo' => 'error', 'mensaje' => "No se pudo leer el archivo: {$archivoAAnalizar}"]);
            break;
        }

        $archivosYaSeleccionadosRutas[] = $archivoAAnalizar;
        $resultadoResumen = obtenerOcrearResumenArchivo($idUsuario, $idPostRepositorio, $rutaCompletaArchivo, $archivoAAnalizar, $instruccionOriginal);
        
        $archivosAnalizadosDetalles[] = ['ruta' => $archivoAAnalizar, 'resumen' => $resultadoResumen];
        $resumenesContexto .= "- Archivo `{$archivoAAnalizar}`: {$resultadoResumen}\n";
    }

    if (empty($archivosAnalizadosDetalles)) {
        return ['success' => false, 'mensaje' => 'La IA no pudo identificar y analizar archivos relevantes. Por favor, sé más específico.'];
    }

    return [
        'success' => true,
        'archivosAnalizadosDetalles' => $archivosAnalizadosDetalles,
        'resumenesContexto' => $resumenesContexto
    ];
}

/**
 * Obtiene el resumen de un archivo (desde caché o generándolo) y lo guarda.
 * @return string El contenido del resumen.
 */
function obtenerOcrearResumenArchivo($idUsuario, $idPostRepositorio, $rutaCompletaArchivo, $archivoAAnalizar, $instruccionOriginal)
{
    $contenidoArchivo = mb_convert_encoding(file_get_contents($rutaCompletaArchivo), 'UTF-8', 'UTF-8');
    $hashContenidoActual = hash('sha256', $contenidoArchivo);

    $resumenesPrevios = get_posts(['post_type' => 'resumen', 'posts_per_page' => 1, 'post_status' => 'any', 'meta_query' => ['relation' => 'AND', ['key' => 'idPostRepositorio', 'value' => $idPostRepositorio], ['key' => 'rutaArchivo', 'value' => $archivoAAnalizar]]]);
    $resumenCacheado = !empty($resumenesPrevios) ? $resumenesPrevios[0] : null;
    $hashCacheado = $resumenCacheado ? get_post_meta($resumenCacheado->ID, 'hashContenido', true) : null;

    if ($resumenCacheado && $hashCacheado === $hashContenidoActual) {
        #Depuración detallada
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'cache-hit', 'mensaje' => "Resumen para `{$archivoAAnalizar}` encontrado en caché."]);
        return $resumenCacheado->post_content;
    }
    
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => "Generando nuevo resumen para `{$archivoAAnalizar}`..."]);
    if ($resumenCacheado) {
        wp_delete_post($resumenCacheado->ID, true);
    }

    $promptResumen = "Contexto: Estoy tratando de cumplir esta instrucción de usuario: \"{$instruccionOriginal}\".\nTarea: Has elegido este archivo para analizar. Resume su propósito y las funciones clave que contiene, posibilidades de refactorización, posibles problemas, posibles, vulnerabilidades, dudas. Este resumen servirá de contexto para decidir qué archivo analizar a continuación. Sé conciso y técnico. Responde únicamente con un JSON que contenga la clave 'resumen'.\n\n--- INICIO CONTENIDO: {$archivoAAnalizar} ---\n" . substr($contenidoArchivo, 0, 25000) . "\n--- FIN CONTENIDO ---";
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'prompt-sistema', 'mensaje' => $promptResumen]);
    $respuestaResumen = api($promptResumen, null, null, true);
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'respuesta-ia', 'mensaje' => $respuestaResumen]);

    $resumen = $respuestaResumen['resumen'] ?? 'La IA no pudo generar un resumen para este archivo.';
    PostActionManager::crearPost('resumen', ['post_title' => $archivoAAnalizar, 'post_content' => $resumen, 'post_author' => $idUsuario, 'meta_input' => ['idPostRepositorio' => $idPostRepositorio, 'rutaArchivo' => $archivoAAnalizar, 'hashContenido' => $hashContenidoActual]]);
    
    return $resumen;
}

/**
 * Llama a la IA con el contexto final para definir el nombre, objetivo y pasos de la misión.
 * @return array ['success', 'datos', 'mensaje']
 */
function definirMisionConIA($idUsuario, $instruccionOriginal, $resumenesContexto)
{
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'Análisis completado. Definiendo la misión...']);
    $promptDefinicion = "Contexto: Instrucción de usuario: \"{$instruccionOriginal}\".\n\nHe analizado los siguientes archivos y he generado estos resúmenes:\n{$resumenesContexto}\n\nTarea: Basado en TODA la información anterior, define la misión. Responde con un único JSON que contenga estas claves: 'nombreMision', 'objetivoMision', 'pasosSugeridos' (array de strings).";
    
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'prompt-sistema', 'mensaje' => $promptDefinicion]);
    $datosMisionIa = api($promptDefinicion, null, null, true);
    #Depuración detallada
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'respuesta-ia', 'mensaje' => $datosMisionIa]);

    if ($datosMisionIa === null || !isset($datosMisionIa['objetivoMision']) || !isset($datosMisionIa['pasosSugeridos'])) {
        return ['success' => false, 'mensaje' => 'La IA no pudo definir la misión con los datos analizados.'];
    }

    return ['success' => true, 'datos' => $datosMisionIa];
}

/**
 * Crea el post de tipo 'mision' y guarda todos los datos relevantes.
 * @return array ['success', 'mensaje', 'idMision', 'mision', 'detallesError']
 */
function guardarMisionEnBD($idUsuario, $idPostRepositorio, $datosMisionIa, $archivosAnalizadosDetalles, $instruccionOriginal, $motivoDetectadoPorIa)
{
    $nombreMision = $datosMisionIa['nombreMision'] ?? 'Misión Generada por IA ' . time();
    $post_data = [
        'post_title' => $nombreMision,
        'post_content' => $datosMisionIa['objetivoMision'],
        'post_status' => 'publish',
        'post_author' => $idUsuario,
        'meta_input' => [
            'archivosRelacionados' => $archivosAnalizadosDetalles,
            'pasosSugeridos' => $datosMisionIa['pasosSugeridos'] ?? [],
            'estado' => 'definida',
            'instruccionOriginalUsuario' => $instruccionOriginal,
            'motivoDetectadoPorIaOriginal' => $motivoDetectadoPorIa,
            'logIaDefinicion' => $datosMisionIa,
            'idPostRepositorioAsociado' => $idPostRepositorio
        ]
    ];

    $idPostMision = PostActionManager::crearPost('mision', $post_data);

    if (is_wp_error($idPostMision) || $idPostMision === 0) {
        return [
            'success' => false,
            'mensaje' => 'Hubo un error al guardar la misión en la base de datos.',
            'detallesError' => is_wp_error($idPostMision) ? $idPostMision->get_error_message() : 'La función crearPost devolvió 0.'
        ];
    }

    $misionCreada = [
        'idMision' => $idPostMision,
        'nombreMision' => $nombreMision,
        'objetivoMision' => $datosMisionIa['objetivoMision'],
        'meta' => ['pasosSugeridos' => $datosMisionIa['pasosSugeridos']]
    ];

    return ['success' => true, 'idMision' => $idPostMision, 'mision' => $misionCreada];
}


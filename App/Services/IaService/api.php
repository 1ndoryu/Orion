<?php 

function api($prompt, $rutaArchivo = null, $mimeTypeArchivo = null, $esperarJson = false, $configuracionGeneracion = [], $configuracionSeguridad = [], $modeloId = 'gemini-2.5-flash-preview-05-20')
{
    $idUsuario = get_current_user_id();

    $apiKey = get_user_meta($idUsuario, 'googleApiKey', true);

    if (empty($apiKey) && current_user_can('administrator')) {
        if (defined('GOOGLE_API_KEY') && !empty(GOOGLE_API_KEY)) {
            $apiKey = GOOGLE_API_KEY;
        } else {
            $envKey = getenv('GOOGLE_API_KEY');
            if ($envKey !== false && !empty($envKey)) {
                $apiKey = $envKey;
            } elseif (isset($_ENV['GOOGLE_API_KEY']) && !empty($_ENV['GOOGLE_API_KEY'])) {
                $apiKey = $_ENV['GOOGLE_API_KEY'];
            }
        }
    }

    if (empty($apiKey)) {
        error_log('api: No se encontró una API key válida para Google API (verificado meta, constante, getenv y $_ENV).');
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modeloId}:generateContent?key={$apiKey}";

    $partesContenido   = [];
    $partesContenido[] = ['text' => $prompt];

    if ($rutaArchivo && $mimeTypeArchivo) {
        if (!file_exists($rutaArchivo) || !is_readable($rutaArchivo)) {
            error_log("api: El archivo {$rutaArchivo} no existe o no es legible.");
            return null;
        }
        $datosArchivo = file_get_contents($rutaArchivo);
        if ($datosArchivo === false) {
            error_log("api: No se pudo leer el contenido del archivo {$rutaArchivo}.");
            return null;
        }
        $partesContenido[] = [
            'inline_data' => [
                'mime_type' => $mimeTypeArchivo,
                'data'      => base64_encode($datosArchivo)
            ]
        ];
    }

    $cuerpoSolicitud = ['contents' => [['parts' => $partesContenido]]];


    $currentGenerationConfig = [
        'temperature'       => 0.5,
        'max_output_tokens' => 60000,
    ];

    if (strpos($modeloId, 'flash') !== false) {
        $currentGenerationConfig['thinking_config'] = ['thinking_budget' => 0];
    }

    $currentGenerationConfig = array_merge($currentGenerationConfig, $configuracionGeneracion);

    if (isset($currentGenerationConfig['thinking_config']) &&
        ($currentGenerationConfig['thinking_config'] === null || $currentGenerationConfig['thinking_config'] === false)) {
        unset($currentGenerationConfig['thinking_config']);
    }

    $responseMimeType = isset($currentGenerationConfig['response_mime_type']) ? $currentGenerationConfig['response_mime_type'] : null;

    if (strpos($modeloId, 'gemma') !== false) {
        $responseMimeType = 'text/plain';
        if ($esperarJson) {
            error_log("api: Se solicitó JSON ($esperarJson=true) pero el modelo es Gemma ({$modeloId}), que solo admite 'text/plain'. La solicitud a la API se hará con 'text/plain'.");
        }
    } elseif ($esperarJson) {
        // Si se espera JSON y NO es Gemma, se usa application/json.
        $responseMimeType = 'application/json';
    } elseif ($responseMimeType === null) {
        // Si no es Gemma, no se espera JSON explícitamente, y el usuario no especificó un mime_type,
        // el default general es text/plain.
        $responseMimeType = 'text/plain';
    }
    // Asignar el responseMimeType determinado al config final
    $currentGenerationConfig['response_mime_type'] = $responseMimeType;
    
    $cuerpoSolicitud['generation_config'] = $currentGenerationConfig;

    // Configuración de seguridad
    $configSeguridadPorDefecto          = [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
    ];
    $cuerpoSolicitud['safety_settings'] = array_merge($configSeguridadPorDefecto, $configuracionSeguridad);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpoSolicitud));

    $respuestaCurl = curl_exec($ch);

    if (curl_errno($ch)) {
        $mensajeError = 'api: Error en CURL: ' . curl_error($ch);
        error_log($mensajeError);
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $datosRespuesta = json_decode($respuestaCurl, true);

    if ($httpCode >= 400) {
        $mensajeError = "api: Error HTTP {$httpCode}. Respuesta: " . $respuestaCurl;
        error_log($mensajeError);
        if (isset($datosRespuesta['error']['message'])) {
            error_log('api: Mensaje de error de la API: ' . $datosRespuesta['error']['message']);
        }
        return null;
    }

    if (isset($datosRespuesta['candidates'][0]['content']['parts'][0]['text'])) {
        $textoGenerado = $datosRespuesta['candidates'][0]['content']['parts'][0]['text'];
        
        if ($esperarJson) {
            $jsonParseado = limpiarYParsearJsonInterno($textoGenerado); 
            if ($jsonParseado === null && !empty($textoGenerado)) { // Solo loguear error de parseo si habia texto
                error_log('api: Se esperaba JSON pero no se pudo parsear la respuesta de la API: ' . substr($textoGenerado, 0, 500));
            }
            return $jsonParseado;
        }
        return $textoGenerado;
    } elseif (isset($datosRespuesta['promptFeedback']['blockReason'])) {
        $razonBloqueo = $datosRespuesta['promptFeedback']['blockReason'];
        $mensajeError = "api: Prompt bloqueado por la API. Razón: {$razonBloqueo}.";
        if (isset($datosRespuesta['promptFeedback']['safetyRatings'])) {
            $mensajeError .= ' Ratings: ' . json_encode($datosRespuesta['promptFeedback']['safetyRatings']);
        }
        error_log($mensajeError);
        return null;
    } else {
        $mensajeError = 'api: Respuesta inesperada de la API o falta el texto generado. Respuesta: ' . $respuestaCurl;
        error_log($mensajeError);
        return null;
    }
}

function limpiarYParsearJsonInterno($texto)
{
    if (empty($texto) || !is_string($texto)) {
        return null;
    }
    
    // CAMBIO CLAVE: Usar una expresión regular para encontrar el bloque JSON.
    // Esta regex busca un texto que empiece con { y termine con } (o [ y ])
    // y captura todo lo que hay en medio, incluyendo anidaciones.
    // El modificador 's' (DOTALL) permite que '.' incluya saltos de línea.
    preg_match('/({.*}|\[.*\])/s', $texto, $matches);

    if (empty($matches[0])) {
        // No se encontró ninguna estructura que parezca JSON.
        return null;
    }

    $jsonString = $matches[0];
    $datos = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('limpiarYParsearJsonInterno: Fallo al decodificar el JSON extraído. Error: ' . json_last_error_msg() . '. String: ' . $jsonString);
        return null;
    }

    return $datos;
}
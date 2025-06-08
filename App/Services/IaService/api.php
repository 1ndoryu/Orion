<?php
// App/Services/IaService/api.php

define('DEFAULT_API_MODEL_ID', 'gemini-2.5-flash-preview-05-20');

function _obtenerClaveApiPredeterminada($idUsuario)
{
    $claveApi = get_user_meta($idUsuario, 'googleApiKey', true);

    if (empty($claveApi) && current_user_can('administrator')) {
        if (defined('GOOGLE_API_KEY') && !empty(GOOGLE_API_KEY)) {
            $claveApi = GOOGLE_API_KEY;
        } else {
            $envKey = getenv('GOOGLE_API_KEY');
            if ($envKey !== false && !empty($envKey)) {
                $claveApi = $envKey;
            } elseif (isset($_ENV['GOOGLE_API_KEY']) && !empty($_ENV['GOOGLE_API_KEY'])) {
                $claveApi = $_ENV['GOOGLE_API_KEY'];
            }
        }
    }
    return $claveApi;
}

function api($prompt, $rutaArchivo = null, $mimeTypeArchivo = null, $esperarJson = false, $configuracionGeneracion = [], $configuracionSeguridad = [], $modeloId = DEFAULT_API_MODEL_ID)
{
    $idUsuario = get_current_user_id();
    $claveApi = _obtenerClaveApiPredeterminada($idUsuario);

    if (empty($claveApi)) {
        error_log('api: No se encontró una clave API válida para Google API.');
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modeloId}:generateContent?key={$claveApi}";

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
    
    $cuerpoSolicitud['generation_config'] = _construirConfiguracionGeneracion($modeloId, $esperarJson, $configuracionGeneracion);
    $cuerpoSolicitud['safety_settings'] = _construirConfiguracionSeguridad($configuracionSeguridad);

    $curlResult = _ejecutarSolicitudCurl($url, json_encode($cuerpoSolicitud));

    $httpCode = $curlResult['httpCode'];
    $respuestaCurl = $curlResult['responseBody'];
    $curlError = $curlResult['curlError'];

    return _procesarRespuestaCurl($httpCode, $respuestaCurl, $curlError, $esperarJson);
}

function _procesarRespuestaCurl($httpCode, $responseBody, $curlError, $esperarJson)
{
    if ($curlError) {
        // El error ya fue logueado en _ejecutarSolicitudCurl
        return null;
    }

    $datosRespuesta = json_decode($responseBody, true);

    if ($httpCode >= 400) {
        $mensajeError = "_procesarRespuestaCurl: Error HTTP {$httpCode}. Respuesta: " . $responseBody;
        error_log($mensajeError);
        if (isset($datosRespuesta['error']['message'])) {
            error_log('_procesarRespuestaCurl: Mensaje de error de la API: ' . $datosRespuesta['error']['message']);
        }
        return null;
    }

    if (isset($datosRespuesta['candidates'][0]['content']['parts'][0]['text'])) {
        $textoGenerado = $datosRespuesta['candidates'][0]['content']['parts'][0]['text'];
        
        if ($esperarJson) {
            $jsonParseado = limpiarYParsearJsonInterno($textoGenerado); 
            if ($jsonParseado === null && !empty($textoGenerado)) { // Solo loguear error de parseo si habia texto
                error_log('_procesarRespuestaCurl: Se esperaba JSON pero no se pudo parsear la respuesta de la API: ' . substr($textoGenerado, 0, 500));
            }
            return $jsonParseado;
        }
        return $textoGenerado;
    } elseif (isset($datosRespuesta['promptFeedback']['blockReason'])) {
        $razonBloqueo = $datosRespuesta['promptFeedback']['blockReason'];
        $mensajeError = "_procesarRespuestaCurl: Prompt bloqueado por la API. Razón: {$razonBloqueo}.";
        if (isset($datosRespuesta['promptFeedback']['safetyRatings'])) {
            $mensajeError .= ' Ratings: ' . json_encode($datosRespuesta['promptFeedback']['safetyRatings']);
        }
        error_log($mensajeError);
        return null;
    } else {
        $mensajeError = '_procesarRespuestaCurl: Respuesta inesperada de la API o falta el texto generado. Respuesta: ' . $responseBody;
        error_log($mensajeError);
        return null;
    }
}

function _ejecutarSolicitudCurl($urlConClave, $cuerpoSolicitudJson)
{
    $ch = curl_init($urlConClave);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpoSolicitudJson);

    $respuestaCurl = curl_exec($ch);
    $curlError = null;

    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log('_ejecutarSolicitudCurl: Error en CURL: ' . $curlError);
        // No retornamos null aquí directamente, dejamos que el llamador decida basado en curlError
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['httpCode' => $httpCode, 'responseBody' => $respuestaCurl, 'curlError' => $curlError];
}

function _construirConfiguracionGeneracion($modeloId, $esperarJson, $configuracionUsuario)
{
    $configuracionGeneracionActual = [
        'temperature'       => 0.5,
        'max_output_tokens' => 60000,
    ];

    if (strpos($modeloId, 'flash') !== false) {
        $configuracionGeneracionActual['thinking_config'] = ['thinking_budget' => 0];
    }

    $configuracionGeneracionActual = array_merge($configuracionGeneracionActual, $configuracionUsuario);

    if (isset($configuracionGeneracionActual['thinking_config']) &&
        ($configuracionGeneracionActual['thinking_config'] === null || $configuracionGeneracionActual['thinking_config'] === false)) {
        unset($configuracionGeneracionActual['thinking_config']);
    }

    $responseMimeType = isset($configuracionGeneracionActual['response_mime_type']) ? $configuracionGeneracionActual['response_mime_type'] : null;

    if (strpos($modeloId, 'gemma') !== false) {
        $responseMimeType = 'text/plain';
        if ($esperarJson) {
            error_log("_construirConfiguracionGeneracion: Se solicitó JSON ($esperarJson=true) pero el modelo es Gemma ({$modeloId}), que solo admite 'text/plain'. La solicitud a la API se hará con 'text/plain'.");
        }
    } elseif ($esperarJson) {
        $responseMimeType = 'application/json';
    } elseif ($responseMimeType === null) {
        $responseMimeType = 'text/plain';
    }

    $configuracionGeneracionActual['response_mime_type'] = $responseMimeType;
    return $configuracionGeneracionActual;
}

function _construirConfiguracionSeguridad($configuracionUsuario)
{
    $configSeguridadPorDefecto = [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
    ];
    return array_merge($configSeguridadPorDefecto, $configuracionUsuario);
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
<?php 
// App/Services/webSocketService.php

function enviarMensajeSocket($idUsuario, $payload)
{
    $url = 'http://127.0.0.1:8081/send-message';

    $cuerpoSolicitud = [
        'idUsuario' => $idUsuario,
        'payload'   => $payload,
    ];
    
    // El flag JSON_UNESCAPED_UNICODE es útil, pero el problema principal suele ser la validez del UTF-8.
    // La conversión con mb_convert_encoding antes de llegar aquí es la solución principal.
    $cuerpoCodificado = json_encode($cuerpoSolicitud, JSON_UNESCAPED_UNICODE);

    // Verificación explícita de error en la codificación JSON
    if ($cuerpoCodificado === false) {
        $jsonErrorMsg = json_last_error_msg();
        // Log detallado que incluye el error específico de JSON y una parte del payload para diagnóstico
        error_log("enviarMensajeSocket: CRÍTICO - Fallo al codificar el payload a JSON. Error: {$jsonErrorMsg}. Payload (muestra): " . substr(print_r($payload, true), 0, 1000));
        return false;
    }

    $respuesta = wp_remote_post($url, [
        'method'  => 'POST',
        'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'    => $cuerpoCodificado,
        'timeout' => 5,
    ]);

    if (is_wp_error($respuesta)) {
        error_log('enviarMensajeSocket: Error en wp_remote_post al contactar el servidor de chat: ' . $respuesta->get_error_message());
        return false;
    }

    $codigoRespuesta = wp_remote_retrieve_response_code($respuesta);

    // Si el código de respuesta indica un error (>=300), se loguea el cuerpo de la respuesta.
    if ($codigoRespuesta >= 300) {
        $cuerpoRespuesta = wp_remote_retrieve_body($respuesta);
        error_log("enviarMensajeSocket: El servidor de chat devolvió un error HTTP {$codigoRespuesta}. Cuerpo de la respuesta: {$cuerpoRespuesta}");
        // Opcionalmente, loguear también el cuerpo enviado si el error es 400 (Bad Request)
        if ($codigoRespuesta === 400) {
            error_log("enviarMensajeSocket: Payload que causó el error 400 (muestra): " . substr($cuerpoCodificado, 0, 1000));
        }
        return false;
    }

    return true;
}
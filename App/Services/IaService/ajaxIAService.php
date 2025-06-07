<?php

function ajaxIA()
{
    if (!isset($_POST['instruccion']) || empty(trim($_POST['instruccion']))) {
        wp_send_json_error(['mensaje' => 'La instrucción para la IA no puede estar vacía.']);
    }

    $idUsuario = get_current_user_id();

    if (!$idUsuario) {
        wp_send_json_error(['mensaje' => 'Usuario no autenticado.'], 403);
    }

    $instruccion = sanitize_textarea_field($_POST['instruccion']);

    $comandoManejado = false;
    if (function_exists('gestionarComandosIA')) {
        $comandoManejado = gestionarComandosIA($instruccion, $idUsuario);
    }

    if ($comandoManejado) {
        return;
    }

    $respuestaIa = api($instruccion);
    $respuestasIaIntermedias['decisionComandoGeneral'] = $respuestaIa;

    if ($respuestaIa !== null) {
        wp_send_json_success([
            'datos' => $respuestaIa,
            'promptEnviado' => $instruccion,
            'respuestasIaIntermedias' => $respuestasIaIntermedias
        ]);
    } else {
        wp_send_json_error([
            'mensaje' => 'Error al comunicarse con la API generativa o al procesar la respuesta. Revise los logs del servidor para más detalles.',
            'promptEnviado' => $instruccion,
            'respuestasIaIntermedias' => $respuestasIaIntermedias
        ]);
    }
}

add_action('wp_ajax_ajaxIA', 'ajaxIA');
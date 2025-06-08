<?php
// App/Services/IaService/ajaxIAService.php

function ajaxIA()
{
    if (!isset($_POST['instruccion']) || empty(trim($_POST['instruccion']))) {
        wp_send_json_error(['mensaje' => 'La instrucción para la IA no puede estar vacía.']);
    }
    //por ejemplo puede recibir un parametro especifico despues de una pregunta, y pasarlo a gestionarComandosIA
    $idUsuario = get_current_user_id();

    if (!$idUsuario) {
        wp_send_json_error(['mensaje' => 'Usuario no autenticado.'], 403);
    }

    $instruccion = sanitize_textarea_field($_POST['instruccion']);
    $comandoManejado = gestionarComandosIA($instruccion, $idUsuario);

    if (!$comandoManejado) {
        $respuestaIa = api($instruccion);
        $respuestasIaIntermedias['decisionComandoGeneral'] = $respuestaIa;
        $payload = [];

        if ($respuestaIa !== null) {
            $payload = [
                'success' => true,
                'datos' => $respuestaIa,
                'promptEnviado' => $instruccion,
                'respuestasIaIntermedias' => $respuestasIaIntermedias
            ];
        } else {
            $payload = [
                'success' => false,
                'mensaje' => 'Error al comunicarse con la API generativa.',
                'promptEnviado' => $instruccion,
                'respuestasIaIntermedias' => $respuestasIaIntermedias
            ];
        }
        enviarMensajeSocket($idUsuario, $payload);
    }
    wp_send_json_success(['mensaje' => 'Comando recibido y en proceso.']);
}

add_action('wp_ajax_ajaxIA', 'ajaxIA');
<?php
// App/Services/IaService/Comandos/comandoCrud.php

define('MODELO_DECISION_COMANDO', 'gemma-3-27b-it');

function gestionarComandosIA($instruccion, $idUsuario)
{
    //luego aqui el parametro activara un funcion especifica sin consultar a respuestaDecisionIa
    $promptsEnviados = ['usuario' => $instruccion];
    $respuestasIaIntermedias = [];

    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'Analizando tu instrucción para determinar el comando...']);

    $promptComando = "Analiza la siguiente instrucción de usuario: \"{$instruccion}\".
 El usuario podría querer:
 1. Iniciar una nueva tarea, proyecto o 'misión' (relacionada con desarrollo de software, análisis de código, gestión de archivos).
 2. Borrar todas sus misiones existentes.
 3. Listar o mostrar todas sus misiones existentes.
 4. Ninguna de las anteriores.

 Responde únicamente con un objeto JSON con las siguientes claves:
 - 'crearMision': (boolean) true si el usuario quiere iniciar una nueva misión.
 - 'borrarTodasMisiones': (boolean) true si el usuario quiere borrar todas sus misiones.
 - 'listarMisiones': (boolean) true si el usuario quiere ver una lista de sus misiones.
 - 'motivoMision': (string) Si crearMision es true, una breve descripción de la misión. Si no, una explicación de por qué no se detecta una acción específica o qué otra acción se detectó.
 Asegúrate de que 'crearMision', 'borrarTodasMisiones' y 'listarMisiones' sean mutuamente excluyentes. Si se detecta la intención de borrar o listar, 'crearMision' debería ser false.";

    $promptsEnviados['decisionComandoGeneral'] = $promptComando;
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'prompt-sistema', 'mensaje' => $promptComando]);

    $respuestaDecisionIa = api($promptComando, null, null, true, [], [], MODELO_DECISION_COMANDO);

    $respuestasIaIntermedias['decisionComandoGeneral'] = $respuestaDecisionIa;
    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'respuesta-ia', 'mensaje' => $respuestaDecisionIa]);

    if ($respuestaDecisionIa === null) {
        error_log("gestionarComandosIA: Respuesta nula de la IA para decisión de comando. Instrucción: {$instruccion}");
        enviarMensajeSocket($idUsuario, [
            'success' => false,
            'mensaje' => 'Error interno al procesar la intención del comando con la IA (respuesta nula).',
            'comandoProcesado' => 'decisionComandoGeneral',
        ]);
        return true;
    }

    if (isset($respuestaDecisionIa['borrarTodasMisiones']) && $respuestaDecisionIa['borrarTodasMisiones'] === true) {
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'Intención reconocida: Borrar todas las misiones.']);
        return comandoBorrarTodasMisiones($idUsuario, $promptsEnviados, $respuestasIaIntermedias);
    }

    if (isset($respuestaDecisionIa['listarMisiones']) && $respuestaDecisionIa['listarMisiones'] === true) {
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'Intención reconocida: Listar misiones.']);
        return comandoListarMisiones($idUsuario, $promptsEnviados, $respuestasIaIntermedias);
    }

    if (isset($respuestaDecisionIa['crearMision']) && $respuestaDecisionIa['crearMision'] === true) {
        $motivoMisionIa = $respuestaDecisionIa['motivoMision'] ?? 'No especificado por la IA.';
        enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => "Intención reconocida: Crear misión.\nMotivo: \"{$motivoMisionIa}\""]);
        return comandoCrearMision($idUsuario, $instruccion, $motivoMisionIa);
    }

    enviarMensajeSocket($idUsuario, ['success' => true, 'tipo' => 'estado', 'mensaje' => 'No se detectó un comando específico, procesando como chat general...']);
    return false;
}
<?php


function gestionarComandosIA($instruccion, $idUsuario)
{
    $promptsEnviados = ['usuario' => $instruccion];
    $respuestasIaIntermedias = [];

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

    $respuestaDecisionIa = api($promptComando, null, null, true, [], [], 'gemma-3-27b-it');
    $respuestasIaIntermedias['decisionComandoGeneral'] = $respuestaDecisionIa;

    if ($respuestaDecisionIa === null) {
        error_log("gestionarComandosIA: Respuesta nula de la IA para decisión de comando. Instrucción: {$instruccion}");
        wp_send_json_error([
            'mensaje' => 'Error interno al procesar la intención del comando con la IA (respuesta nula).',
            'comandoProcesado' => 'decisionComandoGeneral',
            'prompts' => $promptsEnviados,
            'respuestasIaIntermedias' => $respuestasIaIntermedias
        ]);
        return true;
    }

    if (isset($respuestaDecisionIa['borrarTodasMisiones']) && $respuestaDecisionIa['borrarTodasMisiones'] === true) {
        return comandoBorrarTodasMisiones($idUsuario, $promptsEnviados, $respuestasIaIntermedias);
    }

    if (isset($respuestaDecisionIa['listarMisiones']) && $respuestaDecisionIa['listarMisiones'] === true) {
        return comandoListarMisiones($idUsuario, $promptsEnviados, $respuestasIaIntermedias);
    }

    if (isset($respuestaDecisionIa['crearMision']) && $respuestaDecisionIa['crearMision'] === true) {
        $motivoMisionIa = $respuestaDecisionIa['motivoMision'] ?? 'No especificado por la IA.';
        return comandoCrearMision($idUsuario, $instruccion, $motivoMisionIa, $promptsEnviados, $respuestasIaIntermedias);
    }

    return false;
}





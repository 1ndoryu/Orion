<?php
/**
 * Lista todas las misiones (CPT 'mision') de un usuario específico.
 */
function comandoListarMisiones($idUsuario)
{
    $args = [
        'post_type'      => 'mision',
        'author'         => $idUsuario,
        'posts_per_page' => -1, // Obtener todas las misiones
        'post_status'    => 'publish', // O los estados que consideres
    ];

    $misionesQuery = new WP_Query($args);
    $misionesUsuario = [];

    if ($misionesQuery->have_posts()) {
        while ($misionesQuery->have_posts()) {
            $misionesQuery->the_post();
            $idMision = get_the_ID();
            
            $misionesUsuario[] = [
                'idMision' => $idMision,
                'nombreMision' => get_the_title(),
                'objetivoMision' => get_the_content(),
                'estado' => get_post_meta($idMision, 'estado', true),
                'fechaCreacion' => get_the_date('Y-m-d H:i:s'),
                // Puedes agregar más metadatos si es necesario
            ];
        }
        wp_reset_postdata(); // Restaurar datos originales del post
    }

    if (empty($misionesUsuario)) {
        $mensaje = 'Actualmente no tienes ninguna misión definida.';
    } else {
        $mensaje = 'Aquí tienes una lista de tus misiones actuales:';
    }

    wp_send_json_success([
        'mensaje' => $mensaje,
        'misiones' => $misionesUsuario,
        'comandoReconocido' => 'listarMisiones'
    ]);

    return true; // Indica que el comando fue manejado
}

<?php
use Glory\Class\PostActionManager;

/**
 * Borra todas las misiones (CPT 'mision') de un usuario.
 */
function comandoBorrarTodasMisiones($idUsuario)
{
    $args = [
        'post_type'      => 'mision',
        'author'         => $idUsuario,
        'posts_per_page' => -1,
        'post_status'    => 'any', // Para borrar también las que estén en borrador, etc.
        'fields'         => 'ids', // Solo necesitamos los IDs para borrar
    ];

    $misionesQuery = new WP_Query($args);
    $misionesIds = $misionesQuery->posts;

    if (empty($misionesIds)) {
        wp_send_json_success([
            'mensaje' => 'No tenías ninguna misión para borrar.',
            'comandoReconocido' => 'borrarTodasMisiones'
        ]);
        return true;
    }

    $contadorBorradas = 0;
    foreach ($misionesIds as $idMision) {
        if (PostActionManager::deletePost($idMision, true)) { // true para forzar borrado permanente
            $contadorBorradas++;
        }
    }

    wp_send_json_success([
        'mensaje' => "¡Éxito! Se han borrado {$contadorBorradas} misión(es) de forma permanente.",
        'comandoReconocido' => 'borrarTodasMisiones'
    ]);

    return true;
}

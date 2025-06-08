<?php

function home()
{
    // Define default repo values if not already defined globally
    if (!defined('DEFAULT_REPO_URL')) {
        define('DEFAULT_REPO_URL', 'https://github.com/1ndoryu/Glory.git');
    }
    if (!defined('DEFAULT_REPO_BRANCH')) {
        define('DEFAULT_REPO_BRANCH', 'main');
    }

    ob_start();
    ?>
    
    <div class="flex center bloquesTests">
        <div class="bloque borde" id="bloqueTestClonar">
            <div class="variables">
                <?php
                $idUsuario         = get_current_user_id();
                $urlRepo           = get_user_meta($idUsuario, 'metaRepo', true);
                $ramaTrabajo       = get_user_meta($idUsuario, 'ramaTrabajo', true);
                $rutaLocalRepo     = get_user_meta($idUsuario, 'metaRutaLocalRepo', true);
                $ultimaClonacion   = get_user_meta($idUsuario, 'metaUltimaClonacionExitosa', true);
                $repoClonadoUrl    = get_user_meta($idUsuario, 'metaRepoClonadoUrl', true);
                $repoClonadoBranch = get_user_meta($idUsuario, 'metaRepoClonadoBranch', true);

                if (empty($urlRepo) || !filter_var($urlRepo, FILTER_VALIDATE_URL)) {
                    $urlRepo = DEFAULT_REPO_URL;
                }

                if (empty($ramaTrabajo)) {
                    $ramaTrabajo = DEFAULT_REPO_BRANCH;
                }

                ?>
                <p>Ruta Local: <?php echo esc_html($rutaLocalRepo ? wp_normalize_path($rutaLocalRepo) : 'No establecida') ?></p>
                <p>Última Clonación: <?php echo $ultimaClonacion ? date('Y-m-d H:i:s', $ultimaClonacion) : 'Nunca' ?></p>
                <p>Repo Clonado URL: <?php echo esc_html($repoClonadoUrl ?: 'No clonado') ?></p>
                <p>Repo Clonado Branch: <?php echo esc_html($repoClonadoBranch ?: 'No clonado') ?></p>
            </div>
            <button class="primario botonprincipal" id="clonarBoton">Clonar</button>
        </div>

        <div class="bloque borde" id="bloqueTestChat">

            <div class="variables">
            </div>
            <div class="Conversacion">

            </div>
            <div class="inputChat">
                <input type="text" id="mensajeUsuarioChat" placeholder="Escribe tu mensaje aquí...">
                <button id="enviarMensajeChat" class="primario">Enviar</button>
            </div>
        </div>

    </div>
    
    <?php
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}

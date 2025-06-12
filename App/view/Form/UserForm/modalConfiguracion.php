<?
use Glory\Components\FormBuilder as Form;

function modalConfiguracion()
{
    $usuarioId = get_current_user_id();
    if (!$usuarioId) {
        return;
    }

    $usuario = get_userdata($usuarioId);
    $userLogin = $usuario ? $usuario->user_login : '';
    $nombreUsuario = get_user_meta($usuarioId, 'nombreUsuario', true);
    $descripcion = get_user_meta($usuarioId, 'descripcion', true);
    $enlace = get_user_meta($usuarioId, 'enlace', true);

    $imagenPerfilId = get_user_meta($usuarioId, 'imagenPerfil', true);
    if (!empty($imagenPerfilId)) {
        $previewContent = wp_get_attachment_image($imagenPerfilId, 'thumbnail');
    } else {
        $previewContent = esc_html('Arrastra tu foto de perfil');
    }
?>
    <div class="bloque modal" id="modalConfiguracion">
        <div class="modalContenido flex gap columna">
            <p>Configuracion de perfil</p>

            <?
            // 3. Pasamos los atributos 'data-*' a través del array 'atributos'.
            echo Form::inicio([
                'atributos' => [
                    'data-meta-target' => 'user',
                    'data-object-id'   => $usuarioId
                ],
                'extraClass' => 'flex gap columna'
            ]);

            // 4. Pasamos el contenido de la vista previa pre-renderizado.
            echo Form::campoArchivo([
                'nombre' => 'imagenPerfil',
                'idPreview' => 'previewImagenPerfil',
                'previewContent' => $previewContent,
                'limite' => 2048576,
                'accept' => 'image/*',
                'classContainer' => 'preview imagenInput'
            ]);

            // 5. Pasamos el valor obtenido a cada campo correspondiente.
            echo Form::campoTexto([
                'nombre' => 'nombreUsuario',
                'label' => 'Nombre',
                'valor' => $nombreUsuario,
                'limite' => 20,
                'classContainer' => 'nombreInput'
            ]);

            echo Form::campoTexto([
                'nombre' => 'user_login',
                'label' => 'Username',
                'valor' => $userLogin,
                'limite' => 20,
                'classContainer' => 'usernameInput'
            ]);

            echo Form::campoTextarea([
                'nombre' => 'descripcion',
                'label' => 'Descripcion',
                'valor' => $descripcion,
                'rows' => 2,
                'limite' => 260,
                'classContainer' => 'descripcionInput'
            ]);

            echo Form::campoTexto([
                'nombre' => 'enlace',
                'label' => 'Enlace',
                'valor' => $enlace,
                'limite' => 100,
                'classContainer' => 'enlaceInput'
            ]);
            ?>

            <div class="flex botonesBloques">
            <?
            echo Form::botonEnviar([
                'accion' => 'guardarMeta',
                'texto' => 'Guardar',
                'extraClass' => 'borde'
            ]);
            ?>
            </div>

            <?
            echo Form::fin();
            ?>
        </div>
    </div>
<?
}

add_action('wp_footer', 'modalConfiguracion');
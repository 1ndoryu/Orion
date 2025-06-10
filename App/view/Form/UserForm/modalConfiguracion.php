<?
use Glory\Component\FormBuilder as Form;


function modalConfiguracion()
{
?>
    <div class="bloque modal" id="modalConfiguracion">
        <div class="modalContenido flex gap columna">
            <p>Configuracion de perfil</p>

            <?
            echo Form::inicio([
                'metaTarget' => 'user',
                'extraClass' => 'flex gap columna'
            ]);

            echo Form::campoArchivo([
                'nombre' => 'imagenPerfil',
                'idPreview' => 'previewImagenPerfil',
                'textoPreview' => 'Arrastra tu foto de perfil',
                'limite' => 2048576, 
                'accept' => 'image/*',
                'classContainer' => 'imagenInput'
            ]);

            echo Form::campoTexto([
                'nombre' => 'nombreUsuario',
                'label' => 'Nombre',
                'limite' => 20,
                'classContainer' => 'nombreInput'
            ]);

            // 3. CAMBIAMOS 'username' por 'user_login' para mayor claridad.
            echo Form::campoTexto([
                'nombre' => 'user_login',
                'label' => 'Username',
                'limite' => 20,
                'classContainer' => 'usernameInput'
            ]);

            echo Form::campoTextarea([
                'nombre' => 'descripcion',
                'label' => 'Descripcion',
                'rows' => 2,
                'limite' => 260,
                'classContainer' => 'descripcionInput'
            ]);

            echo Form::campoTexto([
                'nombre' => 'enlace',
                'label' => 'Enlace',
                'limite' => 100,
                'classContainer' => 'enlaceInput'
            ]);
            ?>

            <div class="flex botonesBloques">
            <?
            // 2. CORREGIMOS LA ACCIÓN para usar el nuevo handler genérico.
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
<?
use Glory\Component\FormBuilder as Form;

/**
 * Renderiza el modal para crear una nueva publicación.
 * Reconstruido con FormBuilder para ser genérico y mantenible.
 */
function modalCrearPublicacion()
{
    // Obtenemos la información del usuario actual para mostrarla en la cabecera del modal.
    $user = wp_get_current_user();
    $nombreUsuario = $user->display_name;

?>
    <div class="bloque modal" id="modalCrearPublicacion" style="display: flex;">
        <div class="modalContenido flex gap columna">

            <!-- Cabecera del modal con información del usuario (fuera del form) -->
            <?php echo imagenPerfil() ?>

            <?
            // Iniciamos el contenedor del formulario.
            // La lógica de creación de post se manejará en un handler específico.
            echo Form::inicio([
                'metaTarget' => 'post',
                'extraClass' => 'flex gap columna'
            ]);

            // Campo principal para el contenido de la publicación.
            echo Form::campoTextarea([
                'nombre' => 'postContent',
                'placeholder' => 'Escribe aquí tu publicación. Usa # para añadir tags...',
                'rows' => 1,
                'limite' => 2000,
                'classContainer' => 'postContent'
            ]);

            // Contenedor para las previsualizaciones de archivos.
            echo '<div class="previewsForms flex w100 gap oculto">';
            
            echo Form::campoArchivo([
                'nombre' => 'archivoImagen',
                'idPreview' => 'previewImagen',
                'textoPreview' => 'Arrastra o selecciona una imagen',
                'accept' => 'image/*',
                'classContainer' => 'archivoImagen w100 oculto'
            ]);

            echo Form::campoArchivo([
                'nombre' => 'archivoAudio',
                'idPreview' => 'previewAudio',
                'textoPreview' => 'Arrastra o selecciona audios',
                'accept' => 'audio/*',
                // 'multiple' => true, // Futura implementación para múltiples archivos
                'classContainer' => 'previewAudio w100 oculto'
            ]);

            echo '</div>'; // Cierre de .previewsForm
            
            // Opciones de visibilidad
            echo '<div class="flex gap w100">';
            echo Form::campoCheckbox(['nombre' => 'areaFans', 'label' => 'Área de fans', 'classContainer' => 'w100']);
            echo Form::campoCheckbox(['nombre' => 'areaArtistas', 'label' => 'Área de artistas', 'classContainer' => 'w100']);
            echo '</div>';


            // Opciones avanzadas de la publicación
            echo '<div id="opcionesPublicacion" class="bloque flexRow spaceBetween alignItemCenter">';
            echo '<p>Opciones de post</p>';
            echo '<div class="flex flexRow gap" >';

            //los iconos se definiran asi $GLOBALS['descargaicono'] no se como colocarlos aqui asi 
            echo Form::campoCheckbox(['nombre' => 'permitirDescargas', 'labelIcono' => '⬇️', 'tooltip' => 'Permitir descargas']);
            echo Form::campoCheckbox(['nombre' => 'esExclusivo', 'labelIcono' => '⭐', 'tooltip' => 'Contenido exclusivo para suscriptores']);
            echo Form::campoCheckbox(['nombre' => 'permitirColab', 'labelIcono' => '🤝', 'tooltip' => 'Permitir solicitudes de colaboración']);
            echo Form::campoCheckbox(['nombre' => 'lanzamientoMusical', 'labelIcono' => '🎵', 'tooltip' => 'Lanzar a tiendas musicales']);
            echo Form::campoCheckbox(['nombre' => 'enVenta', 'labelIcono' => '💰', 'tooltip' => 'Vender en la tienda']);
            echo Form::campoCheckbox(['nombre' => 'esEfimero', 'labelIcono' => '⏳', 'tooltip' => 'Publicación Efímera (24h)']);

            /*
            los mismos iconos deberian ser estos
                            <label class="custom-checkbox tooltip-element" data-tooltip="Permite las descargas en la publicación">
                    <input type="checkbox" id="descargacheck" name="descargacheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['descargaicono']; ?>
                </label>
                <label class="custom-checkbox tooltip-element" data-tooltip="Exclusividad: solo los usuarios suscritos verán el contenido de la publicación">
                    <input type="checkbox" id="exclusivocheck" name="exclusivocheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['estrella']; ?>
                </label>
                <label class="custom-checkbox tooltip-element" data-tooltip="Permite recibir solicitudes de colaboración">
                    <input type="checkbox" id="colabcheck" name="colabcheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['iconocolab']; ?>
                </label>
                <label class="custom-checkbox tooltip-element" data-tooltip="Publicar en formato stream y lanzar a tiendas musicales">
                    <input type="checkbox" id="musiccheck" name="musiccheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['iconomusic']; ?>
                </label>
                <label class="custom-checkbox tooltip-element" data-tooltip="Vender el contenido, beat o sample en la tienda de 2upra">
                    <input type="checkbox" id="tiendacheck" name="tiendacheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['dolar']; ?>
                </label>
                <label class="custom-checkbox tooltip-element" data-tooltip="Publicación Efimera">
                    <input type="checkbox" id="momentocheck" name="momentocheck" value="1">
                    <span class="checkmark"></span>
                    <? echo $GLOBALS['momentoIcon']; ?>
                </label>
            */
            
            echo '</div>';
            echo '</div>';

            // Botones de acción
            echo '<div class="botonesForm R0A915">';
            echo Form::botonEnviar([
                'accion' => 'crearPublicacion', // Acción para el nuevo handler
                'texto' => 'Publicar',
                'extraClass' => 'crearPublicacion borde'
            ]);
            echo '</div>';

            // Finalizamos el contenedor del formulario.
            echo Form::fin();
            ?>
        </div>
    </div>
<?
}

// Enganchamos la función al footer de WordPress.
add_action('wp_footer', 'modalCrearPublicacion');

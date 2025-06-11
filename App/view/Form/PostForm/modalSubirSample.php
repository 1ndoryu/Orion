<?

use Glory\Component\FormBuilder as Form;

/**
 * Renderiza el modal para crear una nueva publicación.
 * Reconstruido con FormBuilder y ob_start para mayor legibilidad.
 */
function modalSubirSample()
{
    $nombreUsuario = metaUsuario('user_login');

    ob_start();
?>
    <div class="bloque modal previewContenedor subirSample"
        id="modalSubirSample"
        style="display: flex;"
        data-extrapreview=".previewsForms">

        <div class="modalContenido flex gap columna">

            <div class="infoUser">
                <? echo imagenPerfil() ?>
                <p class="nombreUsuario">
                    <? echo esc_html($nombreUsuario); ?>
                </p>
            </div>

            <? echo Form::inicio([
                'extraClass' => 'flex gap columna',
                'atributos'  => [ 
                    'data-post-type' => 'sample', 
                    'data-post-status' => 'publish'
                ]
            ]) ?>

            <? echo Form::campoTextarea([
                'nombre' => 'postContent',
                'placeholder' => 'Publica tu sample, puedes agregar tags usando #',
                'rows' => 2,
                'limite' => 200,
                'minimo' => 10,
                'classContainer' => 'postContent',
                'obligatorio' => 'true',
                'alertaObligatorio' => 'Por favor, agrega una descripcion.'
            ]) ?>

            <div class="previewsForms flex w100 gap oculto">
                <? echo Form::campoArchivo([
                    'nombre' => 'archivoImagen',
                    'idPreview' => 'previewImagen',
                    'textoPreview' => 'Arrastra o selecciona una imagen',
                    'accept' => 'image/*',
                    'classContainer' => 'preview archivoImagen w100 oculto'
                ]) ?>
                <!-- 
                para despues
                'obligatorio' => 'true',
                'alertaObligatorio' => 'Por favor, sube al menos un audio'
                -->
                <? echo Form::campoArchivo([
                    'nombre' => 'archivoAudio',
                    'idPreview' => 'previewAudio',
                    'textoPreview' => 'Arrastra o selecciona audios',
                    'accept' => 'audio/*',
                    'classContainer' => 'previewAudio w100 oculto',

                ]) ?>
            </div>

            <div class="flex gap flexRow botonesSubirSample">

                <button class="botonPreviewAudio borde"><? echo $GLOBALS['subiraudio']; ?></button>

                <button class="botonPreviewImagen borde" data-extrapreview=".previewsForms"><? echo $GLOBALS['subirimagen']; ?></button>

                <? echo Form::botonEnviar([
                    'accion' => 'crearPublicacion',
                    'texto' => 'Publicar',
                    'extraClass' => 'crearPublicacion borde'
                ]) ?>

            </div>

            <? echo Form::fin() ?>

        </div>
    </div>
<?
    echo ob_get_clean();
}

add_action('wp_footer', 'modalSubirSample');

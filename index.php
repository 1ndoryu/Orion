<?php

/**
 * La plantilla principal para mostrar el contenido.
 *
 * Este es el archivo de plantilla más genérico en un tema de WordPress
 * y uno de los dos requeridos para un tema (el otro es style.css).
 * Se utiliza para mostrar una página cuando no hay disponible un archivo más específico.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package TuTema
 */

get_header();
?>

<main id="primary" class="site-main">

    <?php
    if (have_posts()) :

        /* Inicia el Bucle */
        while (have_posts()) :


            /*
				 * Incluye el contenido de la plantilla para una entrada.
				 * Utiliza get_template_part para poder reutilizar el formato del contenido
				 * en un archivo separado (ej. template-parts/content.php).
				 * WordPress buscará formatos específicos al tipo de entrada si existen
				 * (ej. template-parts/content-page.php).
				 */


        endwhile;

        // Muestra la navegación a las entradas siguientes/anteriores cuando sea necesario.


    else :

        /*
			 * Si no se encuentra contenido que coincida con la consulta,
			 * se incluye la plantilla para mostrar "Nada Encontrado".
			 * (ej. template-parts/content-none.php).
			 */

    endif;
    ?>

</main><!-- #primary -->



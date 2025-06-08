<?php


function home()
{
    ob_start();
?>

    <div class="pestanaContenido" data-pestana="uno">

    </div>

    <div class="pestanaContenido" data-pestana="dos">

    </div>

<?
    $content = ob_get_contents();
    ob_end_clean();
    return $content;
}

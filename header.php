<?php

?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<body>

    <header class="header">

        <div class="pestanas">

        </div>

        <div class="buscador">
            <input type="search"
                id="busquedaPrincipal"
                class="busqueda"
                placeholder="Ingresa tu busqueda"
                data-tipos="misiones,perfiles"
                data-cantidad="5"
                data-target="#resultado"
                data-renderer="renderizadorGeneral"
                data-callback-show="mostrarFondo"
                data-callback-hide="ocultarFondo"
                autocomplete="off">
        </div>

        <div class="end">

        </div>
    </header>

    <aside class="menu sidebar">
        <div class="icons">
            <a href="<? echo home_url('/'); ?>">
                <?php echo $GLOBALS['twobox'] ?>
            </a>

            <a href="<? echo home_url('/'); ?>" style="display: none;">
                <? echo $GLOBALS['iconoinicio'];
                ?>
            </a>

            <a href="<? echo home_url('/feed'); ?>">
                <? echo $GLOBALS['iconSocial']; ?>
            </a>

            <a href="<? echo home_url('/packs'); ?>">
                <? echo $GLOBALS['iconoColec']; ?>
            </a>

            <a href="<? echo home_url('/sello'); ?>">
                <? echo $GLOBALS['iconoNube']; ?>
            </a>

            <a href="<? echo home_url('/music'); ?>">
                <? echo $GLOBALS['iconoMusic']; ?>
            </a>


        </div>
        <div class="end">
            <div class="icons">

                <a class="openModal" data-modal="modalNotificaciones">
                    <? echo $GLOBALS['icononoti']; ?>
                </a>

                <a class="openModal" data-modal="modalConfiguracion">
                    <?php echo $GLOBALS['config'] ?>
                </a>


            </div>
        </div>
    </aside>


    <main id="contentAjax" class="main contentAjax">
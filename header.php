<?php

use Glory\Manager\CreditosManager;

$usuarioId = get_current_user_id();
$creditosUser = CreditosManager::getCreditos($usuarioId);

?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Source+Sans+3:wght@200..900&display=swap');
    </style>
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>

<?php if (logeado()) { ?>

    <body>
        <header class="header">

            <div class="pestanas">

            </div>

            <div class="buscador">
                <input type="search"
                    id="busquedaPrincipal"
                    class="busqueda"
                    placeholder="Ingresa tu busqueda"
                    data-tipos="mision"
                    data-cantidad="5"
                    data-target="#resultado"
                    data-renderer="renderizadorGeneral"
                    data-callback-show="mostrarFondo"
                    data-callback-hide="ocultarFondo"
                    autocomplete="off">
            </div>

            <div class="end">
                <div class="contadorCreditos">
                    <? echo $GLOBALS['pro']; ?>
                    <?php echo $creditosUser; ?>
                </div>
                <button data-submenu="menuPerfil" data-posicion="abajo">Test</button>
                <div id="menuPerfil" class="submenus">
                    <a class="openModal" data-modal="modalCrearPost">Opción 1</a>
                    <a>Opción 2</a>
                </div>
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
    <?php } ?>
    <?php if (!logeado()) { ?>
        <header class="headerNoLogin">
            <a href="<? echo home_url('/'); ?>">
                <?php echo $GLOBALS['twobox'] ?>
            </a>

            <nav class="flex gap20">
                <a href="<? echo home_url('/'); ?>">Test</a>
                <a href="<? echo home_url('/'); ?>">Test</a>
                <a href="<? echo home_url('/'); ?>">Test</a>
                <a href="<? echo home_url('/'); ?>">Test</a>
            </nav>

        </header>
    <?php  } ?>

    <main id="contentAjax" class="main contentAjax">
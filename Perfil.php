<?php
session_set_cookie_params(['path' => '/']);
session_start();
$haySesion = isset($_SESSION['estudiante_id']);

$reservasActivas = 0;
$enListaEspera = 0;
if ($haySesion) {
    require 'Conexion.php';

    $consulta = $conexion->prepare(
        'SELECT COUNT(*) AS total FROM prestamo WHERE Id_estudiante = ? AND estado_prestamo = 1'
    );
    $consulta->execute([$_SESSION['estudiante_id']]);
    $fila = $consulta->fetch(PDO::FETCH_ASSOC);
    $reservasActivas = $fila['total'];

    // Antes este número salía de localStorage; ahora sale de la tabla
    // real `lista_espera`.
    $consultaEspera = $conexion->prepare(
        'SELECT COUNT(*) AS total FROM lista_espera WHERE Id_estudiante = ?'
    );
    $consultaEspera->execute([$_SESSION['estudiante_id']]);
    $filaEspera = $consultaEspera->fetch(PDO::FETCH_ASSOC);
    $enListaEspera = $filaEspera['total'];
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$titulo = 'Mi perfil';
$cssExtra = ['Perfil.css'];
$sinAnimaciones = true; // pantalla de perfil: sin animaciones, a pedido
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion aplicacion--perfil" data-shell-fijo>

        <?php
        $tituloEncabezado = 'Mi perfil';
        $volverHref = 'index.php';
        require 'componentes/encabezado-volver.php';
        ?>

        <div class="perfil-scroll">

        <div class="perfil-col perfil-col--principal">

        <section class="perfil-tarjeta">
            <span class="perfil-avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <ellipse cx="12" cy="8" rx="4" ry="4" />
                    <polyline
                        points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                </svg>
            </span>
            <?php if ($haySesion): ?>
                <h2 class="perfil-tarjeta__nombre">
                    <?= htmlspecialchars($_SESSION['estudiante_nombre'] . ' ' . $_SESSION['estudiante_apellido']) ?>
                </h2>
                <p class="perfil-tarjeta__detalle">Panel de estudiante</p>
            <?php else: ?>
                <h2 class="perfil-tarjeta__nombre">Invitado</h2>
                <p class="perfil-tarjeta__detalle">Todavía no iniciaste sesión</p>
                <a href="Login.php" class="boton-reservar perfil-tarjeta__boton-login">Iniciar sesión</a>
            <?php endif; ?>
        </section>

        <div class="detalle-libro__ficha perfil-stats">
            <div class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                </svg>
                <span class="ficha-dato__valor"><?= (int) $reservasActivas ?></span>
                <span class="ficha-dato__etiqueta">Reservas activas</span>
            </div>
            <div class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <ellipse cx="12" cy="12" rx="8.5" ry="8.5" />
                    <polyline points="12,7.5 12,12 15.5,14" />
                </svg>
                <span class="ficha-dato__valor" id="perfil-contador-espera"><?= (int) $enListaEspera ?></span>
                <span class="ficha-dato__etiqueta">En lista de espera</span>
            </div>
        </div>

        </div>

        <div class="perfil-col perfil-col--secundaria">

        <h3 class="perfil-menu__titulo">Accesos rápidos</h3>
        <ul class="perfil-menu">
            <li>
                <a class="perfil-menu__item" href="Reservas.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                    </svg>
                    <span>Mis reservas</span>
                    <svg class="perfil-menu__flecha" viewBox="0 0 24 24" aria-hidden="true">
                        <polyline points="9,4 15,12 9,20" />
                    </svg>
                </a>
            </li>
            <li>
                <a class="perfil-menu__item" href="catalogo.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="3,3 10,3 10,10 3,10" />
                        <polygon points="14,3 21,3 21,10 14,10" />
                        <polygon points="3,14 10,14 10,21 3,21" />
                        <polygon points="14,14 21,14 21,21 14,21" />
                    </svg>
                    <span>Explorar el catálogo</span>
                    <svg class="perfil-menu__flecha" viewBox="0 0 24 24" aria-hidden="true">
                        <polyline points="9,4 15,12 9,20" />
                    </svg>
                </a>
            </li>
            <?php if ($haySesion): ?>
            <li>
                <a class="perfil-menu__item perfil-menu__item--peligro" href="Logout.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15,3 20,3 20,21 15,21" />
                        <polyline points="10,17 15,12 10,7" />
                        <polyline points="15,12 3,12" />
                    </svg>
                    <span>Cerrar sesión</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php require 'componentes/info-biblioteca.php'; ?>

        </div>

        </div>

    </main>

    <?php $navActivo = 'perfil'; require 'componentes/nav-inferior.php'; ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>
</body>

</html>

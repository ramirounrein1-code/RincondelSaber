<?php
session_set_cookie_params(['path' => '/']);
session_start();
$haySesion = isset($_SESSION['estudiante_id']);

$reservasActivas = 0;
$enListaEspera = 0;
if ($haySesion) {
    require 'conexion.php';

    $consulta = mysqli_prepare(
        $conexion,
        'SELECT COUNT(*) AS total FROM prestamo WHERE Id_estudiante = ? AND estado_prestamo = 1'
    );
    mysqli_stmt_bind_param($consulta, 'i', $_SESSION['estudiante_id']);
    mysqli_stmt_execute($consulta);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
    $reservasActivas = $fila['total'];

    // Antes este número salía de localStorage; ahora sale de la tabla
    // real `lista_espera`.
    $consultaEspera = mysqli_prepare(
        $conexion,
        'SELECT COUNT(*) AS total FROM lista_espera WHERE Id_estudiante = ?'
    );
    mysqli_stmt_bind_param($consultaEspera, 'i', $_SESSION['estudiante_id']);
    mysqli_stmt_execute($consultaEspera);
    $filaEspera = mysqli_fetch_assoc(mysqli_stmt_get_result($consultaEspera));
    $enListaEspera = $filaEspera['total'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil · Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
    <link rel="stylesheet" href="perfil.css">
</head>

<body>
    <main class="aplicacion aplicacion--perfil">

        <header class="encabezado-detalle">
            <button type="button" class="boton-volver" id="boton-volver" aria-label="Volver">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polyline points="15,4 9,12 15,20" />
                </svg>
            </button>
            <h1>Mi perfil</h1>
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
        </header>

        <div class="perfil-scroll">

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
                <a class="perfil-menu__item perfil-menu__item--peligro" href="logout.php">
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

        <section class="perfil-biblioteca">
            <h2 class="perfil-biblioteca__titulo">Información de la biblioteca</h2>
            <ul class="perfil-biblioteca__lista">
                <li class="perfil-biblioteca__item">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <ellipse cx="12" cy="12" rx="8.5" ry="8.5" />
                        <polyline points="12,7.5 12,12 15.5,14" />
                    </svg>
                    <div>
                        <p class="perfil-biblioteca__dato">Lunes a viernes, 8:00 a 18:00</p>
                        <p class="perfil-biblioteca__etiqueta">Horario de atención</p>
                    </div>
                </li>
                <li class="perfil-biblioteca__item">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="12,3 21,9 21,21 3,21 3,9" />
                        <polyline points="9,21 9,13 15,13 15,21" />
                    </svg>
                    <div>
                        <p class="perfil-biblioteca__dato">Edificio central, planta baja</p>
                        <p class="perfil-biblioteca__etiqueta">Ubicación</p>
                    </div>
                </li>
                <li class="perfil-biblioteca__item">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="3,5 21,5 21,19 3,19" />
                        <polyline points="3,6 12,13 21,6" />
                    </svg>
                    <div>
                        <p class="perfil-biblioteca__dato">biblioteca@rincondelsaber.edu.ar</p>
                        <p class="perfil-biblioteca__etiqueta">Contacto</p>
                    </div>
                </li>
            </ul>
        </section>

        </div>

    </main>

    <nav class="navegacion-inferior" aria-label="Navegación principal">
        <ul>
            <li><a href="index.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polyline points="3,11 7.5,7.5 12,4 16.5,7.5 21,11" />
                        <polyline points="5,10 5,15 5,20 12,20 19,20 19,15 19,10" />
                    </svg><span>Inicio</span></a></li>
            <li><a href="catalogo.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polygon points="3,3 10,3 10,10 3,10" />
                        <polygon points="14,3 21,3 21,10 14,10" />
                        <polygon points="3,14 10,14 10,21 3,21" />
                        <polygon points="14,14 21,14 21,21 14,21" />
                    </svg><span>Catálogo</span></a></li>
            <li><a href="Reservas.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                    </svg><span>Reservas</span></a></li>
            <li><a href="Perfil.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <ellipse cx="12" cy="8" rx="4" ry="4" />
                        <polyline
                            points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                    </svg><span>Perfil</span></a></li>
        </ul>
    </nav>

    <script src="imagenes-fallback.js"></script>
    <script>
        // overflow:hidden solo no alcanza en algunos navegadores de celular
        // (queda un "rebote" al arrastrar el dedo). Fijamos también la
        // posición para que la pantalla completa no se pueda mover, y solo
        // scrollee el bloque interno .perfil-scroll.
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
        document.documentElement.style.position = 'fixed';
        document.body.style.position = 'fixed';
        document.documentElement.style.height = '100%';
        document.body.style.height = '100%';
        document.documentElement.style.width = '100%';
        document.body.style.width = '100%';

        (function () {
            document.getElementById('boton-volver').addEventListener('click', function () {
                window.location.href = 'index.php';
            });

            // El contador "En lista de espera" ya viene renderizado por PHP
            // desde la base real. Si esta página vuelve del bfcache, la
            // recargamos para que no muestre un número viejo.
            window.addEventListener('pageshow', function (evento) {
                if (evento.persisted) {
                    window.location.reload();
                }
            });
        })();
    </script>
</body>

</html>
<?php
/**
 * PerfilProfesor.php
 * Perfil del profesor logueado. Mismo patrón de pantalla fija que
 * Perfil.php (el alumno): el <main> no se desliza, solo scrollea el
 * bloque .perfil-scroll de adentro.
 */

session_set_cookie_params(['path' => '/']);
session_start();

if (!isset($_SESSION['profesor_id'])) {
    header('Location: LoginProfesor.php');
    exit;
}

require 'Conexion.php';

$idProfesor = (int) $_SESSION['profesor_id'];

$consulta = mysqli_prepare(
    $conexion,
    'SELECT COUNT(*) AS total FROM recomendacion WHERE id_profesor = ?'
);
mysqli_stmt_bind_param($consulta, 'i', $idProfesor);
mysqli_stmt_execute($consulta);
$fila = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));
$totalRecomendaciones = (int) $fila['total'];

$consultaCursos = mysqli_prepare(
    $conexion,
    'SELECT COUNT(DISTINCT curso) AS total FROM recomendacion WHERE id_profesor = ?'
);
mysqli_stmt_bind_param($consultaCursos, 'i', $idProfesor);
mysqli_stmt_execute($consultaCursos);
$filaCursos = mysqli_fetch_assoc(mysqli_stmt_get_result($consultaCursos));
$totalCursos = (int) $filaCursos['total'];
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
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
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
                <h2 class="perfil-tarjeta__nombre">
                    <?= htmlspecialchars($_SESSION['profesor_nombre'] . ' ' . $_SESSION['profesor_apellido']) ?>
                </h2>
                <p class="perfil-tarjeta__detalle">Panel docente</p>
            </section>

            <div class="detalle-libro__ficha perfil-stats">
                <div class="ficha-dato">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                    </svg>
                    <span class="ficha-dato__valor"><?= $totalRecomendaciones ?></span>
                    <span class="ficha-dato__etiqueta">Recomendaciones</span>
                </div>
                <div class="ficha-dato">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                    </svg>
                    <span class="ficha-dato__valor"><?= $totalCursos ?></span>
                    <span class="ficha-dato__etiqueta">Cursos alcanzados</span>
                </div>
            </div>

            <ul class="perfil-menu">
                <li>
                    <a class="perfil-menu__item" href="Recomendar.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg>
                        <span>Mis recomendaciones</span>
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
                <li>
                    <a class="perfil-menu__item perfil-menu__item--peligro" href="LogoutProfesor.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15,3 20,3 20,21 15,21" />
                            <polyline points="10,17 15,12 10,7" />
                            <polyline points="15,12 3,12" />
                        </svg>
                        <span>Cerrar sesión</span>
                    </a>
                </li>
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
            <li><a href="Recomendar.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                    </svg><span>Recomendaciones</span></a></li>
            <li><a href="PerfilProfesor.php" class="navegacion-inferior__item navegacion-inferior__item--activo"><svg viewBox="0 0 24 24">
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

        window.addEventListener('pageshow', function (evento) {
            if (evento.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>

</html>
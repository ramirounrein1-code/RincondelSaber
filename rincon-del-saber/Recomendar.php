<?php
/**
 * Recomendar.php
 * Panel del profesor logueado: lista de recomendaciones ya hechas
 * ("Mis recomendaciones"). Recomendar un libro nuevo (o cancelar una
 * recomendación existente) se hace siempre desde el detalle del libro
 * (Detalle.php), así el profesor ve la ficha completa antes de
 * recomendar. Por eso esta pantalla ya no tiene el formulario
 * "Recomendar libro" con el desplegable de cualquier libro del
 * catálogo: solo muestra lo ya recomendado, con un botón "Detalles"
 * que lleva a Detalle.php (desde ahí también se cancela).
 */

session_set_cookie_params(['path' => '/']);
session_start();

if (!isset($_SESSION['profesor_id'])) {
    header('Location: LoginProfesor.php');
    exit;
}

require 'Conexion.php';

$idProfesor = (int) $_SESSION['profesor_id'];

require 'Cursos.php';
$cursoFiltro = $_GET['curso'] ?? '';
if (!in_array($cursoFiltro, $CURSOS, true)) {
    $cursoFiltro = ''; // valor inválido o vacío = sin filtro, se ven todas
}

// -----------------------------------------------------------------
// Listado de las recomendaciones del profesor logueado (solo las
// suyas). Por defecto se ven todas las propias; si elige un curso en
// el desplegable, se filtran además por ese curso.
// -----------------------------------------------------------------
if ($cursoFiltro !== '') {
    $consultaPropias = mysqli_prepare(
        $conexion,
        'SELECT r.id_recomendacion, r.id_libro, r.curso, r.fecha_recomendacion, r.id_profesor,
                l.título AS titulo, l.autor, l.portada
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         WHERE r.id_profesor = ? AND r.curso = ?
         ORDER BY r.fecha_recomendacion DESC'
    );
    mysqli_stmt_bind_param($consultaPropias, 'is', $idProfesor, $cursoFiltro);
} else {
    $consultaPropias = mysqli_prepare(
        $conexion,
        'SELECT r.id_recomendacion, r.id_libro, r.curso, r.fecha_recomendacion, r.id_profesor,
                l.título AS titulo, l.autor, l.portada
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         WHERE r.id_profesor = ?
         ORDER BY r.fecha_recomendacion DESC'
    );
    mysqli_stmt_bind_param($consultaPropias, 'i', $idProfesor);
}
mysqli_stmt_execute($consultaPropias);
$resultadoPropias = mysqli_stmt_get_result($consultaPropias);
$recomendaciones = [];
while ($fila = mysqli_fetch_assoc($resultadoPropias)) {
    $recomendaciones[] = $fila;
}

function formatearFechaRecomendacion($fechaISO)
{
    if (!$fechaISO) return '';
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $partes = explode('-', substr($fechaISO, 0, 10));
    return (int) $partes[2] . ' ' . $meses[(int) $partes[1] - 1] . ' ' . $partes[0];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis recomendaciones · Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
    <link rel="stylesheet" href="Reservas.css">
    <link rel="stylesheet" href="Recomendar.css">
</head>

<body>
    <main class="aplicacion">

        <header class="encabezado-detalle">
            <button type="button" class="boton-volver" id="boton-volver" aria-label="Volver">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polyline points="15,4 9,12 15,20" />
                </svg>
            </button>
            <h1>Mis recomendaciones</h1>
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
        </header>

        <!-- Filtro opcional por curso: por defecto se ven todas las
             recomendaciones propias; tocar un chip solo restringe la
             vista, no borra ni cambia nada. Chips en vez de <select>
             nativo porque en celular el desplegable del sistema tapa
             media pantalla y es incómodo de tocar. -->
        <div class="filtro-curso" role="tablist" aria-label="Filtrar por curso">
            <a href="Recomendar.php" class="filtro-curso__chip<?= $cursoFiltro === '' ? ' filtro-curso__chip--activo' : '' ?>">Todos</a>
            <?php foreach ($CURSOS as $curso): ?>
                <a href="Recomendar.php?curso=<?= urlencode($curso) ?>"
                    class="filtro-curso__chip<?= $cursoFiltro === $curso ? ' filtro-curso__chip--activo' : '' ?>"><?= htmlspecialchars($curso) ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Para recomendar un libro nuevo, andá al detalle del libro
             desde el catálogo y usá el botón "Recomendar libro" de ahí. -->
        <section class="reservas-lista">
            <?php if (empty($recomendaciones) && $cursoFiltro !== ''): ?>
                <p class="reservas-lista__vacio">
                    Todavía no recomendaste ningún libro para <?= htmlspecialchars($cursoFiltro) ?>.
                    <a href="Recomendar.php">Ver todas las tuyas</a>
                </p>
            <?php elseif (empty($recomendaciones)): ?>
                <p class="reservas-lista__vacio">
                    Todavía no recomendaste ningún libro. Para recomendar uno,
                    entrá al <a href="catalogo.php">catálogo</a> y abrí el
                    detalle del libro.
                </p>
            <?php else: ?>
                <?php foreach ($recomendaciones as $rec): ?>
                    <article class="reserva-tarjeta">
                        <figure class="reserva-tarjeta__portada">
                            <img src="<?= htmlspecialchars($rec['portada'] ?? '') ?>"
                                alt="Portada de <?= htmlspecialchars($rec['titulo']) ?>">
                        </figure>
                        <div class="reserva-tarjeta__cuerpo">
                            <div class="reserva-tarjeta__encabezado">
                                <div>
                                    <h3 class="reserva-tarjeta__titulo"><?= htmlspecialchars($rec['titulo']) ?></h3>
                                    <p class="reserva-tarjeta__autor"><?= htmlspecialchars($rec['autor']) ?></p>
                                </div>
                                <span class="reserva-tarjeta__badge reserva-tarjeta__badge--reservado"><?= htmlspecialchars($rec['curso']) ?></span>
                            </div>
                            <ul class="reserva-tarjeta__datos">
                                <li class="reserva-tarjeta__dato">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <polygon points="4,5 20,5 20,20 4,20" />
                                        <polyline points="4,10 20,10" />
                                        <polyline points="8,3 8,6" />
                                        <polyline points="16,3 16,6" />
                                    </svg>
                                    <span>Recomendado el <?= formatearFechaRecomendacion($rec['fecha_recomendacion']) ?></span>
                                </li>
                            </ul>
                            <a class="boton-detalle" href="Detalle.php?libro=<?= (int) $rec['id_libro'] ?>">Ver detalles</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

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
            <li><a href="Recomendar.php" class="navegacion-inferior__item navegacion-inferior__item--activo"><svg
                        viewBox="0 0 24 24">
                        <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                    </svg><span>Recomendaciones</span></a></li>
            <li><a href="PerfilProfesor.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <ellipse cx="12" cy="8" rx="4" ry="4" />
                        <polyline
                            points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                    </svg><span>Perfil</span></a></li>
        </ul>
    </nav>

    <script src="imagenes-fallback.js"></script>
    <script>
        (function () {
            document.getElementById('boton-volver').addEventListener('click', function () {
                window.location.href = 'index.php';
            });
        })();
    </script>

</body>

</html>
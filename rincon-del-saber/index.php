<?php
session_set_cookie_params(['path' => '/']);
session_start();
$haySesion = isset($_SESSION['estudiante_id']);
$haySesionProfesor = isset($_SESSION['profesor_id']);
$cursoEstudiante = $haySesion ? ($_SESSION['estudiante_curso'] ?? '') : '';

// A partir de cuántas recomendaciones la insignia pasa de verde a
// dorada (mismo criterio que en catalogo.php).
const UMBRAL_RECOMENDACION_DESTACADA = 5;

require 'Conexion.php';

// Contador real y global de libros prestados en este momento (antes era
// un número que dependía de localStorage de cada navegador).
$totalPrestados = 0;
$resultadoContador = mysqli_query($conexion, 'SELECT COUNT(*) AS total FROM prestamo WHERE estado_prestamo = 1');
if ($resultadoContador) {
    $filaContador = mysqli_fetch_assoc($resultadoContador);
    $totalPrestados = (int) $filaContador['total'];
}

// Estado real (disponible/prestado) e id de cada libro que se muestra en
// las secciones curadas de abajo. Antes esto salía de libros.js.
$consultaLibros = mysqli_query(
    $conexion,
    'SELECT l.id_libro, l.título AS titulo, l.autor, l.portada,
            SUM(CASE WHEN p.Id_ejemplar IS NULL THEN 1 ELSE 0 END) AS ejemplares_disponibles
     FROM libro l
     LEFT JOIN ejemplar e ON e.id_libro = l.id_libro
     LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
     GROUP BY l.id_libro'
);

$porTitulo = [];
while ($fila = mysqli_fetch_assoc($consultaLibros)) {
    $porTitulo[$fila['titulo']] = [
        'id' => (int) $fila['id_libro'],
        'disponible' => ((int) $fila['ejemplares_disponibles']) > 0,
    ];
}

/**
 * Devuelve id + disponibilidad real de un libro por su título, para no
 * repetir el mismo "if" en cada tarjeta curada de abajo.
 */
function estadoLibro($porTitulo, $titulo)
{
    if (isset($porTitulo[$titulo])) {
        return $porTitulo[$titulo];
    }
    // Si el libro todavía no existe en la base, no rompemos la página:
    // mostramos el link al catálogo general y estado "prestado" por defecto.
    return ['id' => 0, 'disponible' => false];
}

// Recomendaciones reales de profesores (tabla `recomendacion`), no un
// listado inventado: se muestran los últimos 4 libros recomendados,
// junto con el/los nombre(s) del/de los profesor(es) que lo
// recomendaron.
//
// - Alumno logueado: solo recomendaciones de SU curso.
// - Profesor logueado: todas, sin filtrar (así ve lo que recomendaron
//   sus colegas, sin importar el curso).
// - Invitado: no se consulta nada, se invita a iniciar sesión.
//
// Antes se agrupaba por libro + profesor: si dos profesores
// recomendaban el mismo libro, aparecía dos veces en el carrusel (una
// tarjeta por cada uno). Ahora se agrupa solo por libro y se juntan
// los nombres con GROUP_CONCAT, así cada libro sale una sola vez.
$recomendados = [];
if ($haySesion) {
    $consultaRecomendadosStmt = mysqli_prepare(
        $conexion,
        'SELECT l.id_libro, l.título AS titulo, l.autor, l.portada,
                GROUP_CONCAT(DISTINCT CONCAT(pr.Nombre, " ", pr.Apellido) ORDER BY pr.Nombre SEPARATOR ", ") AS profesores,
                COUNT(*) AS cantidad_recomendaciones,
                MAX(r.fecha_recomendacion) AS fecha_recomendacion
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         JOIN profesor pr ON pr.id_profesor = r.id_profesor
         WHERE r.curso = ?
         GROUP BY l.id_libro
         ORDER BY cantidad_recomendaciones DESC, fecha_recomendacion DESC
         LIMIT 4'
    );
    mysqli_stmt_bind_param($consultaRecomendadosStmt, 's', $cursoEstudiante);
    mysqli_stmt_execute($consultaRecomendadosStmt);
    $consultaRecomendados = mysqli_stmt_get_result($consultaRecomendadosStmt);
} elseif ($haySesionProfesor) {
    $consultaRecomendados = mysqli_query(
        $conexion,
        'SELECT l.id_libro, l.título AS titulo, l.autor, l.portada,
                GROUP_CONCAT(DISTINCT CONCAT(pr.Nombre, " ", pr.Apellido) ORDER BY pr.Nombre SEPARATOR ", ") AS profesores,
                COUNT(*) AS cantidad_recomendaciones,
                MAX(r.fecha_recomendacion) AS fecha_recomendacion
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         JOIN profesor pr ON pr.id_profesor = r.id_profesor
         GROUP BY l.id_libro
         ORDER BY cantidad_recomendaciones DESC, fecha_recomendacion DESC
         LIMIT 4'
    );
} else {
    $consultaRecomendados = null;
}
if ($consultaRecomendados) {
    while ($fila = mysqli_fetch_assoc($consultaRecomendados)) {
        $recomendados[] = $fila;
    }
}

// Convierte la lista de nombres separados por coma en un texto natural:
// "Ana Pérez" / "Ana Pérez y Juan Gómez" / "Ana Pérez y 2 más".
function formatearProfesores(string $listaCsv): string
{
    $nombres = array_filter(array_map('trim', explode(',', $listaCsv)));
    $nombres = array_values($nombres);
    $cantidad = count($nombres);

    if ($cantidad === 0) {
        return '';
    }
    if ($cantidad === 1) {
        return $nombres[0];
    }
    if ($cantidad === 2) {
        return $nombres[0] . ' y ' . $nombres[1];
    }
    return $nombres[0] . ' y ' . ($cantidad - 1) . ' más';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
</head>

<body>
    <main class="aplicacion">
        <header class="encabezado">
            <a href="index.php" class="logotipo">
                <svg class="logotipo__icono" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <polygon
                        points="24,12 23.33,11.49 22.6,11.05 21.83,10.67 21.01,10.35 20.16,10.09 19.28,9.89 18.38,9.75 17.46,9.67 16.53,9.66 15.6,9.7 14.68,9.81 13.77,9.98 12.87,10.21 12,10.5 12,21.5 12,32.5 12.87,32.21 13.77,31.98 14.68,31.81 15.6,31.7 16.53,31.66 17.46,31.67 18.38,31.75 19.28,31.89 20.16,32.09 21.01,32.35 21.83,32.67 22.6,33.05 23.33,33.49 24,34 24.67,33.49 25.4,33.05 26.17,32.67 26.99,32.35 27.84,32.09 28.72,31.89 29.62,31.75 30.54,31.67 31.47,31.66 32.4,31.7 33.32,31.81 34.23,31.98 35.13,32.21 36,32.5 36,21.5 36,10.5 35.06,10.19 34.09,9.95 33.11,9.78 32.11,9.68 31.11,9.66 30.12,9.7 29.14,9.82 28.18,10 27.25,10.26 26.36,10.59 25.52,10.99 24.73,11.46 24,12" />
                </svg>
                <span class="logotipo__texto">Rincón<br>del Saber</span>
            </a>
            <?php if ($haySesion): ?>
                <a href="Perfil.php" class="sesion-indicador">
                    <span class="sesion-indicador__nombre"><?= htmlspecialchars($_SESSION['estudiante_nombre']) ?></span>
                    <span class="sesion-indicador__etiqueta">Ver perfil</span>
                </a>
            <?php elseif ($haySesionProfesor): ?>
                <a href="Recomendar.php" class="sesion-indicador">
                    <span class="sesion-indicador__nombre"><?= htmlspecialchars($_SESSION['profesor_nombre']) ?></span>
                    <span class="sesion-indicador__etiqueta">Panel docente</span>
                </a>
            <?php else: ?>
                <a href="Login.php" class="sesion-indicador sesion-indicador--invitado">
                    <span class="sesion-indicador__nombre">Invitado</span>
                    <span class="sesion-indicador__etiqueta">Iniciar sesión</span>
                </a>
            <?php endif; ?>
        </header>

        <form class="buscador" role="search" action="catalogo.php" method="get">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <ellipse cx="11" cy="11" rx="7" ry="7" />
                <line x1="21" y1="21" x2="16.7" y2="16.7" />
            </svg>
            <input type="search" name="buscar" class="buscador__campo" placeholder="Buscar libros, autores, géneros…"
                aria-label="Buscar libros">
        </form>

        <section class="destacado" aria-label="Destacado">
            <article class="destacado__tarjeta destacado__tarjeta--carrusel">
                <!-- "Descubrí tu próxima lectura" queda oculto por ahora.
                     Para reactivarlo, sacá este comentario y el de más
                     abajo, y quitale la clase "destacado__tarjeta--carrusel"
                     al <article> de arriba. -->
                <!--
                <section class="destacado__texto">
                    <h1>Descubrí tu<br><em>próxima lectura</em></h1>
                    <p>Miles de libros al alcance de todos.</p>
                    <p class="destacado__stat">
                        <span class="destacado__stat-punto" aria-hidden="true"></span>
                        <strong id="contador-lectores"><?= $totalPrestados ?></strong> libros prestados ahora
                    </p>
                </section>
                -->
                <div class="carrusel-banner" id="carrusel-banner">
                    <div class="carrusel-banner__pista" id="carrusel-banner-pista">
                        <figure class="carrusel-banner__slide">
                            <img src="imagenes/banner-1.jpg" alt="">
                        </figure>
                        <figure class="carrusel-banner__slide">
                            <img src="imagenes/banner-2.jpg" alt="">
                        </figure>
                        <figure class="carrusel-banner__slide">
                            <img src="imagenes/banner-3.jpg" alt="">
                        </figure>
                        <!-- Copia de la primera imagen: permite que el carrusel
                             siga deslizando para la derecha al llegar al final,
                             en vez de "rebotar" hacia la izquierda para volver
                             a la primera. Ver el script más abajo. -->
                        <figure class="carrusel-banner__slide" aria-hidden="true">
                            <img src="imagenes/banner-1.jpg" alt="">
                        </figure>
                    </div>
                    <div class="carrusel-banner__puntos" id="carrusel-banner-puntos">
                        <span class="carrusel-banner__punto carrusel-banner__punto--activo"></span>
                        <span class="carrusel-banner__punto"></span>
                        <span class="carrusel-banner__punto"></span>
                    </div>
                </div>
            </article>
        </section>

        <section class="categorias">
            <header class="seccion__encabezado">
                <h2>Categorías</h2>
            </header>
            <ul class="categorias__lista">
                <li><a href="catalogo.php?categoria=todos" class="categoria"><svg class="categoria__icono"
                            viewBox="0 0 24 24">
                            <polygon
                                points="12,6 11.56,5.69 11.08,5.42 10.59,5.18 10.06,4.97 9.52,4.8 8.96,4.66 8.38,4.56 7.78,4.5 7.17,4.48 6.55,4.5 5.92,4.56 5.28,4.66 4.64,4.81 4,5 4,12 4,19 4.64,18.81 5.28,18.66 5.92,18.56 6.55,18.5 7.17,18.48 7.78,18.5 8.38,18.56 8.96,18.66 9.52,18.8 10.06,18.97 10.59,19.18 11.08,19.42 11.56,19.69 12,20 12.44,19.69 12.92,19.42 13.41,19.18 13.94,18.97 14.48,18.8 15.04,18.66 15.62,18.56 16.22,18.5 16.83,18.48 17.45,18.5 18.08,18.56 18.72,18.66 19.36,18.81 20,19 20,12 20,5 19.31,4.8 18.62,4.64 17.94,4.54 17.26,4.49 16.6,4.48 15.94,4.52 15.31,4.61 14.69,4.74 14.1,4.91 13.53,5.13 12.99,5.38 12.48,5.67 12,6" />
                            <polyline points="12,6 12,13 12,20" />
                        </svg><span>Todos</span></a></li>
                <li><a href="catalogo.php?categoria=literatura" class="categoria"><svg class="categoria__icono"
                            viewBox="0 0 24 24">
                            <polygon
                                points="4,20 5.68,19.91 7.29,19.64 8.83,19.19 10.27,18.58 11.62,17.8 12.87,16.85 14,15.75 15.01,14.5 15.89,13.09 16.62,11.55 17.21,9.86 17.64,8.03 17.91,6.08 18,4 16.19,4.11 14.46,4.42 12.83,4.93 11.3,5.65 9.88,6.55 8.59,7.64 7.44,8.91 6.44,10.35 5.59,11.96 4.91,13.74 4.41,15.68 4.1,17.76 4,20" />
                            <polyline
                                points="4,20 4.46,18.74 4.98,17.55 5.56,16.41 6.2,15.32 6.91,14.29 7.67,13.31 8.5,12.38 9.39,11.49 10.34,10.64 11.35,9.84 12.42,9.08 13.55,8.35 14.74,7.66 16,7" />
                        </svg><span>Literatura</span></a></li>
                <li><a href="catalogo.php?categoria=ciencia" class="categoria"><svg class="categoria__icono"
                            viewBox="0 0 24 24">
                            <polyline points="9,3 12,3 15,3" />
                            <polyline
                                points="10,3 10,5.75 10,8.5 7.75,12.75 5.5,17 5.37,17.27 5.28,17.56 5.24,17.87 5.24,18.17 5.29,18.47 5.38,18.76 5.52,19.03 5.69,19.28 5.9,19.5 6.15,19.68 6.41,19.83 6.7,19.93 7,19.99 7.3,20 12,20 16.7,20 17,19.99 17.3,19.93 17.59,19.83 17.85,19.68 18.1,19.5 18.31,19.28 18.48,19.03 18.62,18.76 18.71,18.47 18.76,18.17 18.76,17.87 18.72,17.56 18.63,17.27 18.5,17 16.25,12.75 14,8.5 14,5.75 14,3" />
                        </svg><span>Ciencia</span></a></li>
                <li><a href="catalogo.php?categoria=infantil" class="categoria"><svg class="categoria__icono"
                            viewBox="0 0 24 24">
                            <polygon
                                points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg><span>Infantil</span></a></li>
                <li><a href="catalogo.php?categoria=historia" class="categoria"><svg class="categoria__icono"
                            viewBox="0 0 24 24">
                            <polygon
                                points="12,4 14.47,4.39 16.7,5.53 18.47,7.3 19.61,9.53 20,12 19.61,14.47 18.47,16.7 16.7,18.47 14.47,19.61 12,20 9.53,19.61 7.3,18.47 5.53,16.7 4.39,14.47 4,12 4.39,9.53 5.53,7.3 7.3,5.53 9.53,4.39" />
                            <polyline points="12,7 12,12 15.5,14" />
                        </svg><span>Historia</span></a></li>
            </ul>
        </section>

        <section class="recomendados">
            <header class="seccion__encabezado">
                <h2>Recomendaciones de profesores</h2>
            </header>
            <?php if (empty($recomendados)): ?>
                <p class="buscador__sin-resultados">
                    <?php if ($haySesion): ?>
                        Todavía no hay recomendaciones de profesores para <?= htmlspecialchars($cursoEstudiante) ?>.
                    <?php elseif ($haySesionProfesor): ?>
                        Todavía no hay ninguna recomendación cargada.
                    <?php else: ?>
                        Iniciá sesión como alumno para ver las recomendaciones de tu curso.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <ul class="libros__lista">
                    <?php foreach ($recomendados as $item):
                        $estado = estadoLibro($porTitulo, $item['titulo']);
                        $cantidadProfesores = count(array_filter(array_map('trim', explode(',', $item['profesores']))));
                        $esDestacado = $cantidadProfesores >= UMBRAL_RECOMENDACION_DESTACADA;
                        ?>
                        <li>
                            <article class="libro">
                                <figure class="libro__portada">
                                    <img src="<?= htmlspecialchars($item['portada'] ?? '') ?>"
                                        alt="Portada de <?= htmlspecialchars($item['titulo']) ?>">
                                    <span class="insignia-recomendado<?= $esDestacado ? ' insignia-recomendado--destacado' : '' ?>"
                                        title="<?= $cantidadProfesores === 1 ? '1 profesor lo recomienda' : $cantidadProfesores . ' profesores lo recomiendan' ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                d="M1,21 5,21 5,9 1,9 M23,10 C23,8.9 22.1,8 21,8 L14.69,8 15.64,3.43 15.67,3.11 C15.67,2.7 15.5,2.32 15.23,2.05 L14.17,1 7.59,7.59 C7.22,7.95 7,8.45 7,9 L7,19 C7,20.1 7.9,21 9,21 L18,21 C18.83,21 19.54,20.5 19.84,19.78 L22.86,12.73 C22.95,12.5 23,12.26 23,12 L23,10 Z" />
                                        </svg>
                                    </span>
                                </figure>
                                <h3 class="libro__titulo"><?= htmlspecialchars($item['titulo']) ?></h3>
                                <p class="libro__autor"><?= htmlspecialchars($item['autor']) ?></p>
                                <p class="libro__recomienda">Recomendado por <strong><?= htmlspecialchars(formatearProfesores($item['profesores'])) ?></strong></p>
                                <p class="libro__estado <?= $estado['disponible'] ? 'libro__estado--disponible' : 'libro__estado--prestado' ?>">
                                    <span class="libro__punto"></span><?= $estado['disponible'] ? 'Disponible' : 'Prestado' ?>
                                </p>
                                <a href="Detalle.php?libro=<?= $estado['id'] ?>" class="boton-detalle">Ver detalle</a>
                            </article>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="nuevos-ingresos">
            <header class="seccion__encabezado">
                <h2>Nuevos ingresos</h2>
            </header>
            <ul class="libros__lista">
                <?php
                $nuevosIngresos = [
                    ['titulo' => 'Sapiens', 'autor' => 'Yuval Noah Harari', 'portada' => 'imagenes/sapiens.jpg'],
                    ['titulo' => 'Red Eyes', 'autor' => 'Mara Voss', 'portada' => 'imagenes/red-eyes.jpg'],
                    ['titulo' => 'What is AI', 'autor' => 'Renee Cole', 'portada' => 'imagenes/what-is-ai.jpg'],
                    ['titulo' => 'Walk into the Shadow', 'autor' => 'Theo Bramwell', 'portada' => 'imagenes/walk-into-the-shadow.jpg'],
                ];
                foreach ($nuevosIngresos as $item):
                    $estado = estadoLibro($porTitulo, $item['titulo']);
                    ?>
                    <li>
                        <article class="libro">
                            <figure class="libro__portada">
                                <img src="<?= htmlspecialchars($item['portada']) ?>"
                                    alt="Portada de <?= htmlspecialchars($item['titulo']) ?>">
                                <span class="insignia-nuevo" aria-label="Nuevo ingreso">
                                    <svg viewBox="0 0 24 24">
                                        <polygon
                                            points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                                    </svg>
                                </span>
                            </figure>
                            <h3 class="libro__titulo"><?= htmlspecialchars($item['titulo']) ?></h3>
                            <p class="libro__autor"><?= htmlspecialchars($item['autor']) ?></p>
                            <p class="libro__estado <?= $estado['disponible'] ? 'libro__estado--disponible' : 'libro__estado--prestado' ?>">
                                <span class="libro__punto"></span><?= $estado['disponible'] ? 'Disponible' : 'Prestado' ?>
                            </p>
                            <a href="Detalle.php?libro=<?= $estado['id'] ?>" class="boton-detalle">Ver detalle</a>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <nav class="navegacion-inferior" aria-label="Navegación principal">
        <ul>
            <li><a href="index.php" class="navegacion-inferior__item navegacion-inferior__item--activo"><svg
                        viewBox="0 0 24 24">
                        <polyline points="3,11 7.5,7.5 12,4 16.5,7.5 21,11" />
                        <polyline points="5,10 5,15 5,20 12,20 19,20 19,15 19,10" />
                    </svg><span>Inicio</span></a></li>
            <li><a href="catalogo.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polygon points="3,3 10,3 10,10 3,10" />
                        <polygon points="14,3 21,3 21,10 14,10" />
                        <polygon points="3,14 10,14 10,21 3,21" />
                        <polygon points="14,14 21,14 21,21 14,21" />
                    </svg><span>Catálogo</span></a></li>
            <?php if ($haySesionProfesor): ?>
                <li><a href="Recomendar.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg><span>Recomendaciones</span></a></li>
            <?php else: ?>
                <li><a href="Reservas.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                        </svg><span>Reservas</span></a></li>
            <?php endif; ?>
            <?php if ($haySesionProfesor): ?>
                <li><a href="PerfilProfesor.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <ellipse cx="12" cy="8" rx="4" ry="4" />
                            <polyline
                                points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                        </svg><span>Perfil</span></a></li>
            <?php else: ?>
                <li><a href="Perfil.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <ellipse cx="12" cy="8" rx="4" ry="4" />
                            <polyline
                                points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                        </svg><span>Perfil</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <script src="imagenes-fallback.js"></script>
    <script>
        (function () {
            // El número ya es real (viene de PHP); solo lo animamos de 0
            // hasta el valor final para que se vea bien.
            var contador = document.getElementById('contador-lectores');
            if (!contador) return;

            var meta = parseInt(contador.textContent, 10) || 0;
            var duracion = 900;
            var inicio = null;

            function animar(marca) {
                if (inicio === null) inicio = marca;
                var progreso = Math.min((marca - inicio) / duracion, 1);
                var facilitado = 1 - Math.pow(1 - progreso, 3);
                contador.textContent = Math.floor(facilitado * meta);
                if (progreso < 1) {
                    requestAnimationFrame(animar);
                } else {
                    contador.textContent = meta;
                }
            }

            requestAnimationFrame(animar);
        })();
    </script>
    <script>
        (function () {
            var pista = document.getElementById('carrusel-banner-pista');
            var puntos = document.querySelectorAll('#carrusel-banner-puntos .carrusel-banner__punto');
            if (!pista || !puntos.length) return;

            var totalReal = puntos.length; // 3 imágenes reales
            var totalSlides = totalReal + 1; // + la copia de la primera al final
            var actual = 0;

            function marcarPunto(indiceReal) {
                puntos.forEach(function (punto, i) {
                    punto.classList.toggle('carrusel-banner__punto--activo', i === indiceReal);
                });
            }

            function avanzar() {
                actual++;
                pista.style.transition = 'transform 0.6s ease';
                pista.style.transform = 'translateX(-' + (actual * (100 / totalSlides)) + '%)';
                marcarPunto(actual % totalReal);

                // Llegamos a la copia (visualmente igual a la primera real):
                // cuando termine de deslizarse, saltamos sin animación de
                // vuelta al slide real 0, así el próximo avance sigue
                // deslizando para la derecha sin "rebotar" hacia atrás.
                if (actual === totalSlides - 1) {
                    pista.addEventListener('transitionend', function reiniciar() {
                        pista.removeEventListener('transitionend', reiniciar);
                        pista.style.transition = 'none';
                        actual = 0;
                        pista.style.transform = 'translateX(0%)';
                        pista.offsetHeight; // fuerza a aplicar el salto ya, sin animarlo
                        pista.style.transition = '';
                    });
                }
            }

            setInterval(avanzar, 4000);
        })();
    </script>
</body>

</html>
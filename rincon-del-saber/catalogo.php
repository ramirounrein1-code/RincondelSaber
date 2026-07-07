<?php
/**
 * catalogo.php
 * Reemplaza a catalogo.html. La grilla se arma ENTERA en el servidor con
 * los datos reales de la base (libro + ejemplar + prestamo), así el
 * navegador ya recibe el HTML final con el estado correcto de cada libro
 * y no hay que "corregir" nada con JS después: cero parpadeo.
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

$haySesion = isset($_SESSION['estudiante_id']);
$haySesionProfesor = isset($_SESSION['profesor_id']);
// Curso del alumno logueado (o cadena vacía si es invitado): se usa
// para marcar en la grilla los libros que un profesor recomendó para
// ESE curso puntual, no recomendaciones de otros cursos.
$cursoEstudiante = $haySesion ? ($_SESSION['estudiante_curso'] ?? '') : '';

$consulta = mysqli_prepare(
    $conexion,
    'SELECT l.id_libro, l.título AS titulo, l.autor, l.categoria, l.portada,
            COUNT(DISTINCT e.id_ejemplar) AS total_ejemplares,
            SUM(CASE WHEN p.Id_ejemplar IS NULL THEN 1 ELSE 0 END) AS ejemplares_disponibles,
            (SELECT CONCAT(pr.Nombre, " ", pr.Apellido)
             FROM recomendacion r
             JOIN profesor pr ON pr.id_profesor = r.id_profesor
             WHERE r.id_libro = l.id_libro AND r.curso = ?
             LIMIT 1) AS recomendado_por,
            (SELECT COUNT(*) FROM recomendacion r2
             WHERE r2.id_libro = l.id_libro AND r2.curso = ?) AS total_recomendaciones
     FROM libro l
     LEFT JOIN ejemplar e ON e.id_libro = l.id_libro
     LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
     GROUP BY l.id_libro
     ORDER BY l.título'
);
mysqli_stmt_bind_param($consulta, 'ss', $cursoEstudiante, $cursoEstudiante);
mysqli_stmt_execute($consulta);
$consulta = mysqli_stmt_get_result($consulta);

$libros = [];
while ($fila = mysqli_fetch_assoc($consulta)) {
    $libros[] = $fila;
}

// Categoría pedida por la URL (?categoria=...). Se resuelve acá, en el
// servidor, para que el HTML ya llegue filtrado y no haya que ocultar
// nada con JS después de pintar todo (eso era lo que causaba el
// parpadeo: por un instante se veían todos los libros).
$categoriaActual = $_GET['categoria'] ?? 'todos';

// A partir de cuántas recomendaciones la insignia pasa de verde a
// dorada (libro "muy recomendado").
const UMBRAL_RECOMENDACION_DESTACADA = 5;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo · Rincón del Saber</title>
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
        </header>

        <form class="buscador" role="search" id="formulario-busqueda">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <ellipse cx="11" cy="11" rx="7" ry="7" />
                <line x1="21" y1="21" x2="16.7" y2="16.7" />
            </svg>
            <input type="search" name="buscar" id="campo-busqueda" class="buscador__campo"
                placeholder="Buscar libros, autores, géneros…" aria-label="Buscar libros">
        </form>
        <p class="buscador__sin-resultados" id="mensaje-sin-resultados" hidden>No encontramos libros que coincidan con
            tu búsqueda.</p>

        <section class="categorias">
            <header class="seccion__encabezado">
                <h2>Categorías</h2>
            </header>
            <ul class="categorias__lista">
                <li><a href="catalogo.php?categoria=todos" class="categoria<?= $categoriaActual === 'todos' ? ' categoria--activa' : '' ?>" data-categoria-link="todos"><svg
                            class="categoria__icono" viewBox="0 0 24 24">
                            <polygon
                                points="12,6 11.56,5.69 11.08,5.42 10.59,5.18 10.06,4.97 9.52,4.8 8.96,4.66 8.38,4.56 7.78,4.5 7.17,4.48 6.55,4.5 5.92,4.56 5.28,4.66 4.64,4.81 4,5 4,12 4,19 4.64,18.81 5.28,18.66 5.92,18.56 6.55,18.5 7.17,18.48 7.78,18.5 8.38,18.56 8.96,18.66 9.52,18.8 10.06,18.97 10.59,19.18 11.08,19.42 11.56,19.69 12,20 12.44,19.69 12.92,19.42 13.41,19.18 13.94,18.97 14.48,18.8 15.04,18.66 15.62,18.56 16.22,18.5 16.83,18.48 17.45,18.5 18.08,18.56 18.72,18.66 19.36,18.81 20,19 20,12 20,5 19.31,4.8 18.62,4.64 17.94,4.54 17.26,4.49 16.6,4.48 15.94,4.52 15.31,4.61 14.69,4.74 14.1,4.91 13.53,5.13 12.99,5.38 12.48,5.67 12,6" />
                            <polyline points="12,6 12,13 12,20" />
                        </svg><span>Todos</span></a></li>
                <li><a href="catalogo.php?categoria=literatura" class="categoria<?= $categoriaActual === 'literatura' ? ' categoria--activa' : '' ?>" data-categoria-link="literatura"><svg
                            class="categoria__icono" viewBox="0 0 24 24">
                            <polygon
                                points="4,20 5.68,19.91 7.29,19.64 8.83,19.19 10.27,18.58 11.62,17.8 12.87,16.85 14,15.75 15.01,14.5 15.89,13.09 16.62,11.55 17.21,9.86 17.64,8.03 17.91,6.08 18,4 16.19,4.11 14.46,4.42 12.83,4.93 11.3,5.65 9.88,6.55 8.59,7.64 7.44,8.91 6.44,10.35 5.59,11.96 4.91,13.74 4.41,15.68 4.1,17.76 4,20" />
                            <polyline
                                points="4,20 4.46,18.74 4.98,17.55 5.56,16.41 6.2,15.32 6.91,14.29 7.67,13.31 8.5,12.38 9.39,11.49 10.34,10.64 11.35,9.84 12.42,9.08 13.55,8.35 14.74,7.66 16,7" />
                        </svg><span>Literatura</span></a></li>
                <li><a href="catalogo.php?categoria=ciencia" class="categoria<?= $categoriaActual === 'ciencia' ? ' categoria--activa' : '' ?>" data-categoria-link="ciencia"><svg
                            class="categoria__icono" viewBox="0 0 24 24">
                            <polyline points="9,3 12,3 15,3" />
                            <polyline
                                points="10,3 10,5.75 10,8.5 7.75,12.75 5.5,17 5.37,17.27 5.28,17.56 5.24,17.87 5.24,18.17 5.29,18.47 5.38,18.76 5.52,19.03 5.69,19.28 5.9,19.5 6.15,19.68 6.41,19.83 6.7,19.93 7,19.99 7.3,20 12,20 16.7,20 17,19.99 17.3,19.93 17.59,19.83 17.85,19.68 18.1,19.5 18.31,19.28 18.48,19.03 18.62,18.76 18.71,18.47 18.76,18.17 18.76,17.87 18.72,17.56 18.63,17.27 18.5,17 16.25,12.75 14,8.5 14,5.75 14,3" />
                        </svg><span>Ciencia</span></a></li>
                <li><a href="catalogo.php?categoria=infantil" class="categoria<?= $categoriaActual === 'infantil' ? ' categoria--activa' : '' ?>" data-categoria-link="infantil"><svg
                            class="categoria__icono" viewBox="0 0 24 24">
                            <polygon
                                points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg><span>Infantil</span></a></li>
                <li><a href="catalogo.php?categoria=historia" class="categoria<?= $categoriaActual === 'historia' ? ' categoria--activa' : '' ?>" data-categoria-link="historia"><svg
                            class="categoria__icono" viewBox="0 0 24 24">
                            <polygon
                                points="12,4 14.47,4.39 16.7,5.53 18.47,7.3 19.61,9.53 20,12 19.61,14.47 18.47,16.7 16.7,18.47 14.47,19.61 12,20 9.53,19.61 7.3,18.47 5.53,16.7 4.39,14.47 4,12 4.39,9.53 5.53,7.3 7.3,5.53 9.53,4.39" />
                            <polyline points="12,7 12,12 15.5,14" />
                        </svg><span>Historia</span></a></li>
            </ul>
        </section>

        <section class="catalogo">
            <header class="seccion__encabezado">
                <h2 id="catalogo-titulo"><?php
                    $nombresCategoria = [
                        'todos' => 'Catálogo',
                        'literatura' => 'Catálogo · Literatura',
                        'ciencia' => 'Catálogo · Ciencia',
                        'infantil' => 'Catálogo · Infantil',
                        'historia' => 'Catálogo · Historia',
                    ];
                    echo htmlspecialchars($nombresCategoria[$categoriaActual] ?? 'Catálogo');
                    ?></h2>
            </header>
            <ul class="catalogo__grid" id="lista-catalogo">
                <?php foreach ($libros as $libro): ?>
                    <?php
                    $disponible = ((int) $libro['ejemplares_disponibles']) > 0;
                    $coincideCategoria = $categoriaActual === 'todos' || $libro['categoria'] === $categoriaActual;
                    $totalRecomendaciones = (int) $libro['total_recomendaciones'];
                    $esDestacado = $totalRecomendaciones >= UMBRAL_RECOMENDACION_DESTACADA;
                    ?>
                    <li<?= $coincideCategoria ? '' : ' hidden' ?>>
                        <article class="libro libro--catalogo" data-categoria="<?= htmlspecialchars($libro['categoria']) ?>">
                            <figure class="libro__portada">
                                <img src="<?= htmlspecialchars($libro['portada'] ?? '') ?>"
                                    alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>">
                                <?php if ($totalRecomendaciones > 0): ?>
                                    <span class="insignia-recomendado<?= $esDestacado ? ' insignia-recomendado--destacado' : '' ?>"
                                        title="<?= $esDestacado
                                            ? $totalRecomendaciones . ' profesores lo recomiendan'
                                            : ($totalRecomendaciones === 1 ? '1 profesor lo recomienda' : $totalRecomendaciones . ' profesores lo recomiendan') ?>">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path
                                                d="M1,21 5,21 5,9 1,9 M23,10 C23,8.9 22.1,8 21,8 L14.69,8 15.64,3.43 15.67,3.11 C15.67,2.7 15.5,2.32 15.23,2.05 L14.17,1 7.59,7.59 C7.22,7.95 7,8.45 7,9 L7,19 C7,20.1 7.9,21 9,21 L18,21 C18.83,21 19.54,20.5 19.84,19.78 L22.86,12.73 C22.95,12.5 23,12.26 23,12 L23,10 Z" />
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </figure>
                            <h3 class="libro__titulo"><?= htmlspecialchars($libro['titulo']) ?></h3>
                            <p class="libro__autor"><?= htmlspecialchars($libro['autor']) ?></p>
                            <?php if (!empty($libro['recomendado_por'])): ?>
                                <p class="libro__recomienda">
                                    <strong><?= htmlspecialchars($libro['recomendado_por']) ?></strong> lo recomienda para tu curso
                                </p>
                            <?php endif; ?>
                            <p class="libro__estado <?= $disponible ? 'libro__estado--disponible' : 'libro__estado--prestado' ?>">
                                <span class="libro__punto"></span><?= $disponible ? 'Disponible' : 'Prestado' ?>
                            </p>
                            <a href="Detalle.php?libro=<?= (int) $libro['id_libro'] ?>" class="boton-detalle">Ver
                                detalle</a>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <nav class="navegacion-inferior" aria-label="Navegación principal">
        <ul>
            <li><a href="index.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                        <polyline points="3,11 7.5,7.5 12,4 16.5,7.5 21,11" />
                        <polyline points="5,10 5,15 5,20 12,20 19,20 19,15 19,10" />
                    </svg><span>Inicio</span></a></li>
            <li><a href="catalogo.php" class="navegacion-inferior__item navegacion-inferior__item--activo"><svg
                        viewBox="0 0 24 24">
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
            // Filtro de categoría y búsqueda: puramente visual sobre el
            // HTML que ya vino armado y completo del servidor. No decide
            // nada sobre disponibilidad ni reservas, así que no hay
            // parpadeo posible: el estado de cada libro ya es el real
            // desde el primer render.
            var NOMBRES_CATEGORIA = {
                todos: 'Catálogo',
                literatura: 'Catálogo · Literatura',
                ciencia: 'Catálogo · Ciencia',
                infantil: 'Catálogo · Infantil',
                historia: 'Catálogo · Historia'
            };

            var parametros = new URLSearchParams(window.location.search);
            var categoriaActual = parametros.get('categoria') || 'todos';
            var busquedaInicial = parametros.get('buscar') || '';

            var campo = document.getElementById('campo-busqueda');
            var formulario = document.getElementById('formulario-busqueda');
            var mensajeSinResultados = document.getElementById('mensaje-sin-resultados');
            var titulo = document.getElementById('catalogo-titulo');
            var libros = document.querySelectorAll('.libro--catalogo');
            var chips = document.querySelectorAll('[data-categoria-link]');

            function normalizar(texto) {
                return texto
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            }

            function marcarChipActivo() {
                chips.forEach(function (chip) {
                    var esActivo = chip.getAttribute('data-categoria-link') === categoriaActual;
                    chip.classList.toggle('categoria--activa', esActivo);
                });
                titulo.textContent = NOMBRES_CATEGORIA[categoriaActual] || 'Catálogo';
            }

            function filtrarLibros() {
                var consulta = normalizar(campo.value.trim());
                var algunoVisible = false;

                libros.forEach(function (libro) {
                    var categoriaLibro = libro.getAttribute('data-categoria');
                    var coincideCategoria = categoriaActual === 'todos' || categoriaLibro === categoriaActual;

                    var tituloLibro = libro.querySelector('.libro__titulo');
                    var autorLibro = libro.querySelector('.libro__autor');
                    var texto = normalizar((tituloLibro ? tituloLibro.textContent : '') + ' ' + (autorLibro ? autorLibro.textContent : ''));
                    var coincideBusqueda = consulta === '' || texto.indexOf(consulta) !== -1;

                    var coincide = coincideCategoria && coincideBusqueda;
                    var contenedor = libro.closest('li') || libro;

                    contenedor.hidden = !coincide;
                    if (coincide) algunoVisible = true;
                });

                mensajeSinResultados.hidden = algunoVisible;
            }

            campo.value = busquedaInicial;
            marcarChipActivo();
            filtrarLibros();

            campo.addEventListener('input', filtrarLibros);
            formulario.addEventListener('submit', function (evento) {
                evento.preventDefault();
                filtrarLibros();
            });

            chips.forEach(function (chip) {
                chip.addEventListener('click', function (evento) {
                    evento.preventDefault();
                    categoriaActual = chip.getAttribute('data-categoria-link');
                    var url = new URL(window.location.href);
                    url.searchParams.set('categoria', categoriaActual);
                    history.pushState({}, '', url);
                    marcarChipActivo();
                    filtrarLibros();
                });
            });
        })();
    </script>
</body>

</html>
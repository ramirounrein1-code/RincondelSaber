<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    session_set_cookie_params(['path' => '/']);
    session_start();
    $haySesion = isset($_SESSION['estudiante_id']);
    $haySesionProfesor = isset($_SESSION['profesor_id']);
    $cursoEstudiante = $haySesion ? ($_SESSION['estudiante_curso'] ?? '') : '';

    // A partir de cuántas recomendaciones la insignia pasa de verde a dorada.
    const UMBRAL_RECOMENDACION_DESTACADA = 5;

    require 'Conexion.php';

    // Contador real y global de libros prestados en este momento
    $totalPrestados = 0;
    $resultadoContador = $conexion->query("SELECT COUNT(*) as total FROM prestamo WHERE estado_prestamo = 1");
    if ($resultadoContador) {
        $filaContador = $resultadoContador->fetch(PDO::FETCH_ASSOC);
        $totalPrestados = (int) $filaContador['total'];
    }

    // Id del alumno logueado (0 si es invitado o profesor)
    $idEstudianteActual = $haySesion ? (int) $_SESSION['estudiante_id'] : 0;

    // Estado real de cada libro en las secciones curadas
    $consultaLibros = $conexion->prepare(
        'SELECT l.id_libro, l.título AS titulo, l.autor, l.portada,
                SUM(CASE WHEN p.Id_ejemplar IS NULL THEN 1 ELSE 0 END) AS ejemplares_disponibles,
                (SELECT COUNT(*) FROM prestamo p2
                 JOIN ejemplar e2 ON e2.id_ejemplar = p2.Id_ejemplar
                 WHERE e2.id_libro = l.id_libro AND p2.Id_estudiante = ?
                   AND p2.estado_prestamo = 1) AS reservado_por_mi
         FROM libro l
         LEFT JOIN ejemplar e ON e.id_libro = l.id_libro
         LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
         GROUP BY l.id_libro'
    );
    $consultaLibros->execute([$idEstudianteActual]);

    $porTitulo = [];
    while ($fila = $consultaLibros->fetch(PDO::FETCH_ASSOC)) {
        $porTitulo[$fila['titulo']] = [
            'id' => (int) $fila['id_libro'],
            'disponible' => ((int) $fila['ejemplares_disponibles']) > 0,
            'reservado' => ((int) $fila['reservado_por_mi']) > 0,
        ];
    }

    /**
     * Devuelve id + disponibilidad real de un libro por su título
     */
    function estadoLibro($porTitulo, $titulo)
    {
        if (isset($porTitulo[$titulo])) {
            return $porTitulo[$titulo];
        }
        return ['id' => 0, 'disponible' => false, 'reservado' => false];
    }

    $recomendados = [];
    if ($haySesion) {
        $consultaRecomendadosStmt = $conexion->prepare(
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
        $consultaRecomendadosStmt->execute([$cursoEstudiante]);
        $recomendados = $consultaRecomendadosStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($haySesionProfesor) {
        $consultaRecomendadosStmt = $conexion->query(
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
        if ($consultaRecomendadosStmt) {
            $recomendados = $consultaRecomendadosStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

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

<?php require 'componentes/head.php'; ?>

<body>
    <main class="aplicacion">
        <?php require 'componentes/encabezado-logo.php'; ?>

        <form class="buscador" role="search" action="catalogo.php" method="get">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <ellipse cx="11" cy="11" rx="7" ry="7" />
                <line x1="21" y1="21" x2="16.7" y2="16.7" />
            </svg>
            <input type="search" name="buscar" class="buscador__campo" placeholder="Buscar libros, autores, géneros…"
                aria-label="Buscar libros">
        </form>

        <div class="home-franja-superior">
            <section class="destacado" aria-label="Destacado">
                <article class="destacado__tarjeta destacado__tarjeta--carrusel">
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
        </div>

        <div class="home-franja-libros">
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
                                <p class="libro__estado <?= $estado['reservado'] ? 'libro__estado--prestado' : ($estado['disponible'] ? 'libro__estado--disponible' : 'libro__estado--prestado') ?>">
                                    <span class="libro__punto"></span><?= $estado['reservado'] ? 'Reservado' : ($estado['disponible'] ? 'Disponible' : 'Prestado') ?>
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
                            <p class="libro__estado <?= $estado['reservado'] ? 'libro__estado--prestado' : ($estado['disponible'] ? 'libro__estado--disponible' : 'libro__estado--prestado') ?>">
                                <span class="libro__punto"></span><?= $estado['reservado'] ? 'Reservado' : ($estado['disponible'] ? 'Disponible' : 'Prestado') ?>
                            </p>
                            <a href="Detalle.php?libro=<?= $estado['id'] ?>" class="boton-detalle">Ver detalle</a>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        </div>
    </main>

    <?php $navActivo = 'inicio'; require 'componentes/nav-inferior.php'; ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>
    <script>
        (function () {
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

            var totalReal = puntos.length;
            var totalSlides = totalReal + 1;
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

                if (actual === totalSlides - 1) {
                    pista.addEventListener('transitionend', function reiniciar() {
                        pista.removeEventListener('transitionend', reiniciar);
                        pista.style.transition = 'none';
                        actual = 0;
                        pista.style.transform = 'translateX(0%)';
                        pista.offsetHeight; 
                        pista.style.transition = '';
                    });
                }
            }

            setInterval(avanzar, 4000);
        })();
    </script>
</body>

</html>
<?php
session_set_cookie_params(['path' => '/']);
session_start();
$haySesion = isset($_SESSION['estudiante_id']);
$prestamos = [];
$listaEspera = [];

if ($haySesion) {
    require 'conexion.php';

    // Pestaña "Activas": préstamos reales de la tabla `prestamo`.
    $consulta = mysqli_prepare(
        $conexion,
        'SELECT l.id_libro, l.título AS titulo, l.autor AS autor, l.portada, p.fecha_prestamo, p.fecha_estimada_de_devolución AS fecha_limite
         FROM prestamo p
         JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
         JOIN libro l ON l.id_libro = e.id_libro
         WHERE p.Id_estudiante = ? AND p.estado_prestamo = 1
         ORDER BY p.fecha_prestamo DESC'
    );
    mysqli_stmt_bind_param($consulta, 'i', $_SESSION['estudiante_id']);
    mysqli_stmt_execute($consulta);
    $resultado = mysqli_stmt_get_result($consulta);
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $prestamos[] = $fila;
    }

    // Pestaña "Lista de espera": ahora sale de la tabla real `lista_espera`
    // (antes se leía de localStorage y se perdía al cambiar de navegador).
    // El puesto se calcula al vuelo contando, para el mismo libro, cuántas
    // anotaciones son iguales o más antiguas que la nuestra (por id_lista,
    // que respeta el orden de inscripción). Así, si alguien más adelante
    // cancela, todos los que quedan atrás "suben" un puesto automáticamente
    // sin tener que recalcular ni guardar nada en la tabla.
    $consultaEspera = mysqli_prepare(
        $conexion,
        'SELECT l.id_libro, l.título AS titulo, l.autor, l.portada, le.fecha_solicitud AS fecha_anotado,
                (SELECT COUNT(*) FROM lista_espera le2
                 WHERE le2.id_libro = le.id_libro AND le2.id_lista <= le.id_lista) AS puesto,
                (SELECT COUNT(*) FROM lista_espera le2
                 WHERE le2.id_libro = le.id_libro) AS total_en_espera
         FROM lista_espera le
         JOIN libro l ON l.id_libro = le.id_libro
         WHERE le.id_estudiante = ?
         ORDER BY le.fecha_solicitud DESC'
    );
    mysqli_stmt_bind_param($consultaEspera, 'i', $_SESSION['estudiante_id']);
    mysqli_stmt_execute($consultaEspera);
    $resultadoEspera = mysqli_stmt_get_result($consultaEspera);
    while ($fila = mysqli_fetch_assoc($resultadoEspera)) {
        $listaEspera[] = $fila;
    }
}

function formatearFechaPhp($fechaISO)
{
    if (!$fechaISO) return '';
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $partes = explode('-', $fechaISO);
    return (int) $partes[2] . ' ' . $meses[(int) $partes[1] - 1];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis reservas · Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
    <link rel="stylesheet" href="reservas.css">
</head>

<body>
    <main class="aplicacion">

        <header class="encabezado-detalle">
            <button type="button" class="boton-volver" id="boton-volver" aria-label="Volver">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polyline points="15,4 9,12 15,20" />
                </svg>
            </button>
            <h1>Mis reservas</h1>
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
        </header>

        <div class="reservas-tabs" role="tablist">
            <button type="button" class="reservas-tabs__boton reservas-tabs__boton--activo" id="tab-activas"
                role="tab" aria-selected="true">Activas</button>
            <button type="button" class="reservas-tabs__boton" id="tab-espera" role="tab"
                aria-selected="false">Lista de espera</button>
        </div>

        <!-- Pestaña "Activas": esta parte ya viene armada desde PHP con
             los préstamos reales de la tabla `prestamo`. -->
        <section id="panel-activas" class="reservas-lista">
            <?php if (!$haySesion): ?>
                <p class="reservas-lista__vacio">
                    <a href="Login.php">Iniciá sesión</a> para ver tus préstamos.
                </p>
            <?php elseif (empty($prestamos)): ?>
                <p class="reservas-lista__vacio">Todavía no tenés préstamos activos.</p>
            <?php else: ?>
                <?php foreach ($prestamos as $prestamo): ?>
                    <article class="reserva-tarjeta">
                        <figure class="reserva-tarjeta__portada">
                            <img src="<?= htmlspecialchars($prestamo['portada'] ?? '') ?>"
                                alt="Portada de <?= htmlspecialchars($prestamo['titulo']) ?>">
                        </figure>
                        <div class="reserva-tarjeta__cuerpo">
                            <div class="reserva-tarjeta__encabezado">
                                <div>
                                    <h3 class="reserva-tarjeta__titulo"><?= htmlspecialchars($prestamo['titulo']) ?></h3>
                                    <p class="reserva-tarjeta__autor"><?= htmlspecialchars($prestamo['autor']) ?></p>
                                </div>
                                <span class="reserva-tarjeta__badge reserva-tarjeta__badge--reservado">Reservado</span>
                            </div>
                            <ul class="reserva-tarjeta__datos">
                                <li class="reserva-tarjeta__dato">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <polygon points="4,5 20,5 20,20 4,20" />
                                        <polyline points="4,10 20,10" />
                                        <polyline points="8,3 8,6" />
                                        <polyline points="16,3 16,6" />
                                    </svg>
                                    <span>Retirado el <?= formatearFechaPhp($prestamo['fecha_prestamo']) ?></span>
                                </li>
                                <li class="reserva-tarjeta__dato reserva-tarjeta__dato--limite">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <ellipse cx="12" cy="12" rx="8.5" ry="8.5" />
                                        <polyline points="12,7.5 12,12 15.5,14" />
                                    </svg>
                                    <span>Devolvé antes del <?= formatearFechaPhp($prestamo['fecha_limite']) ?></span>
                                </li>
                            </ul>
                            <a class="boton-detalle" href="Detalle.php?libro=<?= (int) $prestamo['id_libro'] ?>">Ver detalles</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <!-- Pestaña "Lista de espera": ahora sale de la tabla real
             `lista_espera`, igual que "Activas" sale de `prestamo`. -->
        <section id="panel-espera" class="reservas-lista" hidden>
            <?php if (!$haySesion): ?>
                <p class="reservas-lista__vacio">
                    <a href="Login.php">Iniciá sesión</a> para ver tu lista de espera.
                </p>
            <?php elseif (empty($listaEspera)): ?>
                <p class="reservas-lista__vacio">Todavía no te anotaste en ninguna lista de espera.</p>
            <?php else: ?>
                <?php foreach ($listaEspera as $item): ?>
                    <article class="reserva-tarjeta">
                        <figure class="reserva-tarjeta__portada">
                            <img src="<?= htmlspecialchars($item['portada'] ?? '') ?>"
                                alt="Portada de <?= htmlspecialchars($item['titulo']) ?>">
                        </figure>
                        <div class="reserva-tarjeta__cuerpo">
                            <div class="reserva-tarjeta__encabezado">
                                <div>
                                    <h3 class="reserva-tarjeta__titulo"><?= htmlspecialchars($item['titulo']) ?></h3>
                                    <p class="reserva-tarjeta__autor"><?= htmlspecialchars($item['autor']) ?></p>
                                </div>
                                <span class="reserva-tarjeta__badge reserva-tarjeta__badge--espera">En lista de espera</span>
                            </div>
                            <ul class="reserva-tarjeta__datos">
                                <li class="reserva-tarjeta__dato">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <polygon points="4,5 20,5 20,20 4,20" />
                                        <polyline points="4,10 20,10" />
                                        <polyline points="8,3 8,6" />
                                        <polyline points="16,3 16,6" />
                                    </svg>
                                    <span>Anotado el <?= formatearFechaPhp(substr($item['fecha_anotado'], 0, 10)) ?></span>
                                </li>
                                <li class="reserva-tarjeta__dato reserva-tarjeta__dato--limite">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <polyline points="17,1 21,5 17,9" />
                                        <path d="M3,11 3,9 21,9 21,5" />
                                        <polyline points="7,23 3,19 7,15" />
                                        <path d="M21,13 21,15 3,15 3,19" />
                                    </svg>
                                    <span>
                                        <?php if ((int) $item['puesto'] === 1): ?>
                                            Sos el/la siguiente en la lista (puesto 1 de <?= (int) $item['total_en_espera'] ?>)
                                        <?php else: ?>
                                            Puesto <?= (int) $item['puesto'] ?> de <?= (int) $item['total_en_espera'] ?> en la lista de espera
                                        <?php endif; ?>
                                    </span>
                                </li>
                            </ul>
                            <a class="boton-detalle" href="Detalle.php?libro=<?= (int) $item['id_libro'] ?>">Ver detalles</a>
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
            <li><a href="Reservas.php" class="navegacion-inferior__item navegacion-inferior__item--activo"><svg viewBox="0 0 24 24">
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
        (function () {
            // -----------------------------------------------------------------
            // Tabs: "Activas" y "Lista de espera" ya vienen armadas desde PHP
            // con datos reales de la base (prestamo y lista_espera). El JS
            // acá solo alterna cuál de las dos se ve.
            // -----------------------------------------------------------------
            var tabActivas = document.getElementById('tab-activas');
            var tabEspera = document.getElementById('tab-espera');
            var panelActivas = document.getElementById('panel-activas');
            var panelEspera = document.getElementById('panel-espera');

            function mostrar(tab) {
                var esActivas = tab === 'activas';
                tabActivas.classList.toggle('reservas-tabs__boton--activo', esActivas);
                tabEspera.classList.toggle('reservas-tabs__boton--activo', !esActivas);
                tabActivas.setAttribute('aria-selected', esActivas);
                tabEspera.setAttribute('aria-selected', !esActivas);
                panelActivas.hidden = !esActivas;
                panelEspera.hidden = esActivas;
            }

            tabActivas.addEventListener('click', function () { mostrar('activas'); });
            tabEspera.addEventListener('click', function () { mostrar('espera'); });

            document.getElementById('boton-volver').addEventListener('click', function () {
                window.location.href = 'index.php';
            });

            // Si esta página vuelve del bfcache del navegador, la recargamos
            // para traer los datos al día (por si reservaste algo mientras
            // tanto desde otra pestaña).
            window.addEventListener('pageshow', function (evento) {
                if (evento.persisted) {
                    window.location.reload();
                }
            });
        })();
    </script>
</body>

</html>
<?php
session_set_cookie_params(['path' => '/']);
session_start();
$haySesion = isset($_SESSION['estudiante_id']);
$prestamos = [];
$listaEspera = [];

if ($haySesion) {
    require 'Conexion.php';

    // Pestaña "Activas": préstamos reales de la tabla `prestamo`.
    $consulta = $conexion->prepare(
        'SELECT l.id_libro, l.título AS titulo, l.autor AS autor, l.portada, p.fecha_prestamo, p.fecha_estimada_de_devolución AS fecha_limite
         FROM prestamo p
         JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
         JOIN libro l ON l.id_libro = e.id_libro
         WHERE p.Id_estudiante = ? AND p.estado_prestamo = 1
         ORDER BY p.fecha_prestamo DESC'
    );
    $consulta->execute([$_SESSION['estudiante_id']]);
    $prestamos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // Pestaña "Lista de espera": sale de la tabla real `lista_espera`
    // (antes se leía de localStorage y se perdía al cambiar de navegador).
    // El puesto se calcula al vuelo contando, para el mismo libro, cuántas
    // anotaciones son iguales o más antiguas que la nuestra (por id_lista,
    // que respeta el orden de inscripción). Así, si alguien más adelante
    // cancela, todos los que quedan atrás "suben" un puesto automáticamente
    // sin tener que recalcular ni guardar nada en la tabla.
    $consultaEspera = $conexion->prepare(
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
    $consultaEspera->execute([$_SESSION['estudiante_id']]);
    $listaEspera = $consultaEspera->fetchAll(PDO::FETCH_ASSOC);
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

<?php
$titulo = 'Mis reservas';
$cssExtra = ['Reservas.css'];
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion">

        <?php
        $tituloEncabezado = 'Mis reservas';
        $volverHref = 'index.php';
        require 'componentes/encabezado-volver.php';
        ?>

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

        <!-- Pestaña "Lista de espera": sale de la tabla real
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

    <?php $navActivo = 'reservas'; require 'componentes/nav-inferior.php'; ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>
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
        })();
    </script>
</body>

</html>

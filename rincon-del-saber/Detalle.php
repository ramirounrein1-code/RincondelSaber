<?php
/**
 * Detalle.php
 * Reemplaza a Detalle.html. Ya no lee libros.js ni localStorage: todo sale
 * de la base (libro, ejemplar, prestamo, lista_espera) y todo lo que hace
 * el botón "Reservar" / "Unirme a lista de espera" se procesa en
 * Reservar.php y queda guardado de verdad.
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

$haySesion = isset($_SESSION['estudiante_id']);
$haySesionProfesor = isset($_SESSION['profesor_id']);
$idLibro = isset($_GET['libro']) ? (int) $_GET['libro'] : 0;

$consulta = mysqli_prepare(
    $conexion,
    'SELECT l.id_libro, l.título AS titulo, l.autor, l.categoria, l.editorial,
            l.fecha_publicación AS publicado, l.portada, l.descripcion,
            l.valoracion, l.paginas,
            COUNT(e.id_ejemplar) AS total_ejemplares,
            SUM(CASE WHEN p.Id_ejemplar IS NULL THEN 1 ELSE 0 END) AS ejemplares_disponibles
     FROM libro l
     LEFT JOIN ejemplar e ON e.id_libro = l.id_libro
     LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
     WHERE l.id_libro = ?
     GROUP BY l.id_libro'
);
mysqli_stmt_bind_param($consulta, 'i', $idLibro);
mysqli_stmt_execute($consulta);
$libro = mysqli_fetch_assoc(mysqli_stmt_get_result($consulta));

// Si el libro no existe, no hay nada que mostrar.
if (!$libro) {
    header('Location: catalogo.php');
    exit;
}

$disponible = ((int) $libro['ejemplares_disponibles']) > 0;

// Recomendaciones de profesores para este libro (para cualquier curso,
// no solo el del alumno que está mirando la página).
$cursoEstudiante = $haySesion ? ($_SESSION['estudiante_curso'] ?? '') : '';

$consultaRecomendaciones = mysqli_prepare(
    $conexion,
    'SELECT r.curso, pr.Apellido AS apellido, CONCAT(pr.Nombre, " ", pr.Apellido) AS profesor
     FROM recomendacion r
     JOIN profesor pr ON pr.id_profesor = r.id_profesor
     WHERE r.id_libro = ? AND r.curso = ?
     ORDER BY r.curso'
);
mysqli_stmt_bind_param($consultaRecomendaciones, 'is', $idLibro, $cursoEstudiante);
mysqli_stmt_execute($consultaRecomendaciones);
$resultadoRecomendaciones = mysqli_stmt_get_result($consultaRecomendaciones);
$recomendaciones = [];
while ($fila = mysqli_fetch_assoc($resultadoRecomendaciones)) {
    $recomendaciones[] = $fila;
}

// Estado del ALUMNO REAL logueado con respecto a ESTE libro (nada de
// localStorage: se consulta la base cada vez).
$yaReservado = false;
$yaEnEspera = false;

if ($haySesion) {
    $idEstudiante = (int) $_SESSION['estudiante_id'];

    $consultaPropio = mysqli_prepare(
        $conexion,
        'SELECT 1 FROM prestamo p
         JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
         WHERE e.id_libro = ? AND p.Id_estudiante = ? AND p.estado_prestamo = 1
         LIMIT 1'
    );
    mysqli_stmt_bind_param($consultaPropio, 'ii', $idLibro, $idEstudiante);
    mysqli_stmt_execute($consultaPropio);
    $yaReservado = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($consultaPropio));

    $consultaEspera = mysqli_prepare(
        $conexion,
        'SELECT 1 FROM lista_espera WHERE id_estudiante = ? AND id_libro = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($consultaEspera, 'ii', $idEstudiante, $idLibro);
    mysqli_stmt_execute($consultaEspera);
    $yaEnEspera = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($consultaEspera));
}

// Estado del PROFESOR REAL logueado con respecto a ESTE libro: ¿ya lo
// recomendó? (para cualquier curso). Si ya lo recomendó, guardamos el
// curso con el que lo hizo, para mostrarlo en el botón de cancelar.
$yaRecomendado = false;
$miRecomendacion = null;
$cursosProfesor = [];

if ($haySesionProfesor) {
    $idProfesor = (int) $_SESSION['profesor_id'];

    $consultaMiRecomendacion = mysqli_prepare(
        $conexion,
        'SELECT id_recomendacion, curso, motivo FROM recomendacion WHERE id_libro = ? AND id_profesor = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($consultaMiRecomendacion, 'ii', $idLibro, $idProfesor);
    mysqli_stmt_execute($consultaMiRecomendacion);
    $miRecomendacion = mysqli_fetch_assoc(mysqli_stmt_get_result($consultaMiRecomendacion));
    $yaRecomendado = (bool) $miRecomendacion;

    if (!$yaRecomendado) {
        // Lista fija de cursos para el desplegable del modal de
        // recomendar (ver Cursos.php).
        require 'Cursos.php';
        $cursosProfesor = $CURSOS;
    }
}

// Mensaje que llega desde Reservar.php / CancelarReserva.php / o de
// RecomendarLibro.php / CancelarRecomendacion.php después de procesar
// la solicitud.
$mensaje = '';
$mensajeEsError = false;
if (isset($_GET['ok'])) {
    $textosOk = [
        'reservado' => '¡Reserva confirmada! Pasá por la biblioteca a retirar tu ejemplar. Devolución antes de 7 días.',
        'espera' => 'Te anotamos en la lista de espera. Te avisaremos apenas haya un ejemplar libre.',
        'cancelado' => 'Cancelamos tu reserva de este libro.',
        'espera_cancelada' => 'Saliste de la lista de espera de este libro.',
        'recomendado' => '¡Recomendación guardada! Ya la van a ver los alumnos del curso que elegiste.',
        'recomendacion_cancelada' => 'Cancelamos tu recomendación de este libro.',
    ];
    $mensaje = $textosOk[$_GET['ok']] ?? '¡Listo!';
} elseif (isset($_GET['error'])) {
    $mensajeEsError = true;
    $textos = [
        'ya_reservado' => 'Ya tenés un préstamo activo de este libro.',
        'ya_en_espera' => 'Ya estás en la lista de espera de este libro.',
        'libro_inexistente' => 'Ese libro no existe.',
        'servidor' => 'Iniciá sesión para poder reservar.',
        'nada_que_cancelar' => 'No encontramos ninguna reserva tuya para cancelar.',
        'ya_recomendado' => 'Ya recomendaste este libro.',
        'curso_invalido' => 'Elegí un curso válido.',
    ];
    $mensaje = $textos[$_GET['error']] ?? 'No pudimos procesar tu solicitud. Probá de nuevo.';
}

$anioPublicado = $libro['publicado'] ? date('Y', strtotime($libro['publicado'])) : '—';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($libro['titulo']) ?> · Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
</head>

<body>
    <main class="aplicacion aplicacion--detalle">

        <header class="encabezado-detalle">
            <button type="button" class="boton-volver" id="boton-volver" aria-label="Volver">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polyline points="15,4 9,12 15,20" />
                </svg>
            </button>
            <h1>Detalle del libro</h1>
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
        </header>

        <article class="detalle-libro">
            <figure class="detalle-libro__portada">
                <img src="<?= htmlspecialchars($libro['portada'] ?? '') ?>"
                    alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>">
            </figure>

            <section class="detalle-libro__info">
                <h2 class="detalle-libro__titulo"><?= htmlspecialchars($libro['titulo']) ?></h2>
                <p class="detalle-libro__autor"><?= htmlspecialchars($libro['autor']) ?></p>

                <ul class="detalle-libro__meta">
                    <li class="meta-chip meta-chip--valoracion">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon
                                points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg>
                        <span><?= $libro['valoracion'] !== null ? number_format((float) $libro['valoracion'], 1) : '—' ?></span>
                    </li>
                    <li class="meta-chip meta-chip--estado <?= $disponible ? 'libro__estado--disponible' : 'libro__estado--prestado' ?>">
                        <span class="libro__punto"></span>
                        <span><?= $disponible ? 'Disponible' : 'Prestado' ?></span>
                    </li>
                </ul>
            </section>
        </article>

        <ul class="detalle-libro__ficha">
            <li class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                </svg>
                <span class="ficha-dato__valor"><?= htmlspecialchars($libro['editorial'] ?? '—') ?></span>
                <span class="ficha-dato__etiqueta">Editorial</span>
            </li>
            <li class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polygon points="4,5 20,5 20,20 4,20" />
                    <polyline points="4,10 20,10" />
                    <polyline points="8,3 8,6" />
                    <polyline points="16,3 16,6" />
                </svg>
                <span class="ficha-dato__valor"><?= htmlspecialchars((string) $anioPublicado) ?></span>
                <span class="ficha-dato__etiqueta">Publicado</span>
            </li>
            <li class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polygon points="6,3 15,3 18,6 18,21 6,21" />
                    <polyline points="9,10 15,10" />
                    <polyline points="9,14 15,14" />
                </svg>
                <span class="ficha-dato__valor"><?= (int) $libro['paginas'] ?></span>
                <span class="ficha-dato__etiqueta">Páginas</span>
            </li>
            <li class="ficha-dato">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polygon points="4,7 4,20 20,20 20,7" />
                    <polygon points="2,4 22,4 20,7 4,7" />
                    <polyline points="10,11 14,11" />
                </svg>
                <span class="ficha-dato__valor"><?= (int) $libro['ejemplares_disponibles'] ?>/<?= (int) $libro['total_ejemplares'] ?></span>
                <span class="ficha-dato__etiqueta">Disponibles</span>
            </li>
        </ul>

        <?php if (!empty($recomendaciones)): ?>
            <?php
                $totalRecomendaciones = count($recomendaciones);
                $primeraRecomendacion = $recomendaciones[0];
                $extra = $totalRecomendaciones - 1;
                $quienRecomienda = 'Prof. ' . htmlspecialchars($primeraRecomendacion['apellido']);
                if ($extra > 0) {
                    $quienRecomienda .= ' y ' . $extra . ' más';
                }
                $verboRecomienda = $extra > 0 ? 'lo recomiendan' : 'lo recomienda';
            ?>
            <section class="detalle-libro__ficha" style="flex-direction: column; gap: 8px; padding: 16px 20px 0;">
                <p class="libro__recomienda" style="margin: 0;">
                    <strong><?= $quienRecomienda ?></strong> <?= $verboRecomienda ?> para tu curso
                </p>
            </section>
        <?php endif; ?>

        <section class="detalle-libro__descripcion">
            <h3>Descripción</h3>
            <p class="descripcion__texto"><?= htmlspecialchars($libro['descripcion'] ?? '') ?></p>
        </section>

        <div class="detalle-libro__accion">
            <?php if ($haySesionProfesor && $yaRecomendado): ?>
                <form method="post" action="CancelarRecomendacion.php" id="formulario-cancelar-recomendacion">
                    <input type="hidden" name="id_recomendacion" value="<?= (int) $miRecomendacion['id_recomendacion'] ?>">
                    <input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>">
                    <input type="hidden" name="origen" value="detalle">
                    <button type="submit" class="boton-reservar boton-reservar--cancelar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="5" y1="5" x2="19" y2="19" />
                            <line x1="19" y1="5" x2="5" y2="19" />
                        </svg>
                        Cancelar recomendación
                    </button>
                </form>
            <?php elseif ($haySesionProfesor): ?>
                <form method="post" action="RecomendarLibro.php" id="formulario-recomendar" novalidate>
                    <input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>">
                    <button type="submit" class="boton-reservar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg>
                        Recomendar libro
                    </button>
                </form>
            <?php elseif (!$haySesion): ?>
                <a href="Login.php" class="boton-reservar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15,3 20,3 20,21 15,21" />
                        <polyline points="10,7 15,12 10,17" />
                        <polyline points="15,12 3,12" />
                    </svg>
                    Iniciá sesión para reservar
                </a>
            <?php elseif ($yaReservado): ?>
                <form method="post" action="CancelarReserva.php" id="formulario-cancelar">
                    <input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>">
                    <input type="hidden" name="tipo" value="reserva">
                    <button type="submit" class="boton-reservar boton-reservar--cancelar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="5" y1="5" x2="19" y2="19" />
                            <line x1="19" y1="5" x2="5" y2="19" />
                        </svg>
                        Cancelar reserva
                    </button>
                </form>
            <?php elseif ($yaEnEspera): ?>
                <form method="post" action="CancelarReserva.php" id="formulario-cancelar">
                    <input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>">
                    <input type="hidden" name="tipo" value="espera">
                    <button type="submit" class="boton-reservar boton-reservar--cancelar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="5" y1="5" x2="19" y2="19" />
                            <line x1="19" y1="5" x2="5" y2="19" />
                        </svg>
                        Salir de la lista de espera
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="Reservar.php" id="formulario-reserva" novalidate>
                    <input type="hidden" name="id_libro" value="<?= (int) $libro['id_libro'] ?>">
                    <button type="submit" class="boton-reservar">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="6,3 18,3 18,21 12,17 6,21" />
                        </svg>
                        <?= $disponible ? 'Reservar libro' : 'Unirme a lista de espera' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </main>

    <?php if ($haySesion && !$yaReservado && !$yaEnEspera): ?>
        <!-- Modal de confirmación: reemplaza al confirm() nativo del navegador -->
        <div class="modal-overlay" id="modal-confirmar-overlay" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-confirmar-titulo">
                <button type="button" class="modal__cerrar" id="modal-confirmar-cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="5" y1="5" x2="19" y2="19" />
                        <line x1="19" y1="5" x2="5" y2="19" />
                    </svg>
                </button>
                <h2 class="modal__titulo" id="modal-confirmar-titulo">
                    <?= $disponible ? 'Reservar libro' : 'Unirme a lista de espera' ?>
                </h2>
                <p class="modal__descripcion">
                    <?= $disponible
                        ? '¿Confirmás la reserva de «' . htmlspecialchars($libro['titulo']) . '»?'
                        : '¿Querés anotarte en la lista de espera de «' . htmlspecialchars($libro['titulo']) . '»?' ?>
                </p>
                <?php if ($disponible): ?>
                    <!-- Solo tiene sentido pedir la fecha de devolución cuando se
                         entrega un ejemplar de verdad; en lista de espera todavía
                         no hay libro físico que devolver. El "form" asocia este
                         campo al formulario de afuera aunque esté en otro lugar
                         del HTML, así viaja junto con el resto del POST. -->
                    <label class="modal__campo">
                        Fecha de devolución estimada
                        <input type="date" name="fecha_devolucion" form="formulario-reserva" required
                            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                            max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                            value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                    </label>
                <?php endif; ?>
                <div class="modal__acciones">
                    <button type="button" class="boton-reservar" id="modal-confirmar-aceptar">
                        <?= $disponible ? 'Confirmar reserva' : 'Unirme a la lista' ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($haySesion && ($yaReservado || $yaEnEspera)): ?>
        <!-- Modal de confirmación para cancelar reserva / salir de la lista de espera -->
        <div class="modal-overlay" id="modal-cancelar-overlay" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-cancelar-titulo">
                <button type="button" class="modal__cerrar" id="modal-cancelar-cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="5" y1="5" x2="19" y2="19" />
                        <line x1="19" y1="5" x2="5" y2="19" />
                    </svg>
                </button>
                <h2 class="modal__titulo" id="modal-cancelar-titulo">
                    <?= $yaReservado ? 'Cancelar reserva' : 'Salir de la lista de espera' ?>
                </h2>
                <p class="modal__descripcion">
                    <?= $yaReservado
                        ? '¿Confirmás que querés cancelar la reserva de «' . htmlspecialchars($libro['titulo']) . '»?'
                        : '¿Confirmás que querés salir de la lista de espera de «' . htmlspecialchars($libro['titulo']) . '»?' ?>
                </p>
                <div class="modal__acciones">
                    <button type="button" class="boton-reservar boton-reservar--cancelar" id="modal-cancelar-aceptar">
                        <?= $yaReservado ? 'Sí, cancelar reserva' : 'Sí, salir de la lista' ?>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($haySesionProfesor && !$yaRecomendado): ?>
        <!-- Modal de confirmación para recomendar el libro -->
        <div class="modal-overlay" id="modal-recomendar-overlay" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-recomendar-titulo">
                <button type="button" class="modal__cerrar" id="modal-recomendar-cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="5" y1="5" x2="19" y2="19" />
                        <line x1="19" y1="5" x2="5" y2="19" />
                    </svg>
                </button>
                <h2 class="modal__titulo" id="modal-recomendar-titulo">Recomendar libro</h2>
                <p class="modal__descripcion">
                    ¿Para qué curso recomendás «<?= htmlspecialchars($libro['titulo']) ?>»?
                </p>
                <label class="modal__campo">
                    Curso
                    <select name="curso" form="formulario-recomendar" required>
                        <option value="">Elegí un curso…</option>
                        <?php foreach ($cursosProfesor as $curso): ?>
                            <option value="<?= htmlspecialchars($curso) ?>"><?= htmlspecialchars($curso) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="modal__acciones">
                    <button type="button" class="boton-reservar" id="modal-recomendar-aceptar">
                        Confirmar recomendación
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($haySesionProfesor && $yaRecomendado): ?>
        <!-- Modal de confirmación para cancelar la recomendación -->
        <div class="modal-overlay" id="modal-cancelar-recomendacion-overlay" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-cancelar-recomendacion-titulo">
                <button type="button" class="modal__cerrar" id="modal-cancelar-recomendacion-cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="5" y1="5" x2="19" y2="19" />
                        <line x1="19" y1="5" x2="5" y2="19" />
                    </svg>
                </button>
                <h2 class="modal__titulo" id="modal-cancelar-recomendacion-titulo">Cancelar recomendación</h2>
                <p class="modal__descripcion">
                    ¿Confirmás que querés cancelar la recomendación de «<?= htmlspecialchars($libro['titulo']) ?>»
                    para <?= htmlspecialchars($miRecomendacion['curso'] ?? '') ?>?
                </p>
                <div class="modal__acciones">
                    <button type="button" class="boton-reservar boton-reservar--cancelar" id="modal-cancelar-recomendacion-aceptar">
                        Sí, cancelar recomendación
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <!-- Modal de resultado: reemplaza al cartel de texto de arriba -->
        <div class="modal-overlay" id="modal-mensaje-overlay">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-mensaje-titulo">
                <button type="button" class="modal__cerrar" id="modal-mensaje-cerrar" aria-label="Cerrar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <line x1="5" y1="5" x2="19" y2="19" />
                        <line x1="19" y1="5" x2="5" y2="19" />
                    </svg>
                </button>
                <div class="modal__exito <?= $mensajeEsError ? 'modal__exito--error' : '' ?>">
                    <?php if ($mensajeEsError): ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <ellipse cx="12" cy="12" rx="9" ry="9" />
                            <line x1="12" y1="8" x2="12" y2="13" />
                            <line x1="12" y1="16.3" x2="12" y2="16.31" />
                        </svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <ellipse cx="12" cy="12" rx="9" ry="9" />
                            <polyline points="8,12.5 11,15.5 16,9" />
                        </svg>
                    <?php endif; ?>
                    <h3 id="modal-mensaje-titulo"><?= $mensajeEsError ? 'No pudimos completar la solicitud' : '¡Listo!' ?></h3>
                    <p><?= htmlspecialchars($mensaje) ?></p>
                    <button type="button" class="boton-reservar" id="modal-mensaje-aceptar">Listo</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
            <?php if ($haySesionProfesor): ?>
                <li><a href="Recomendar.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg><span>Recomendaciones</span></a></li>
            <?php else: ?>
                <li><a href="Reservas.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                        </svg><span>Reservas</span></a></li>
            <?php endif; ?>
            <?php if (!$haySesionProfesor): ?>
                <li><a href="Perfil.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <ellipse cx="12" cy="8" rx="4" ry="4" />
                            <polyline
                                points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                        </svg><span>Perfil</span></a></li>
            <?php else: ?>
                <li><a href="PerfilProfesor.php" class="navegacion-inferior__item"><svg viewBox="0 0 24 24">
                            <ellipse cx="12" cy="8" rx="4" ry="4" />
                            <polyline
                                points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                        </svg><span>Perfil</span></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <script src="imagenes-fallback.js"></script>
    <script>
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        document.getElementById('boton-volver').addEventListener('click', function () {
            window.location.href = 'index.php';
        });

        (function () {
            // Un mismo patrón sirve tanto para "Reservar libro" como para
            // "Cancelar reserva" / "Salir de la lista de espera": intercepta
            // el submit del formulario y solo lo manda de verdad si el
            // usuario confirma en el modal.
            function conectarConfirmacion(idFormulario, idOverlay, idCerrar, idAceptar) {
                var formulario = document.getElementById(idFormulario);
                var overlay = document.getElementById(idOverlay);
                if (!formulario || !overlay) return;

                var botonCerrar = document.getElementById(idCerrar);
                var botonAceptar = document.getElementById(idAceptar);

                function abrir() {
                    overlay.hidden = false;
                }
                function cerrar() {
                    overlay.hidden = true;
                }

                formulario.addEventListener('submit', function (evento) {
                    evento.preventDefault();
                    abrir();
                });

                botonCerrar.addEventListener('click', cerrar);
                overlay.addEventListener('click', function (evento) {
                    if (evento.target === overlay) cerrar();
                });
                document.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Escape' && !overlay.hidden) cerrar();
                });

                botonAceptar.addEventListener('click', function () {
                    // Si el modal tiene algún campo obligatorio (como la fecha
                    // de devolución o el curso a recomendar), no dejamos pasar
                    // el envío sin completarlo.
                    var camposRequeridos = overlay.querySelectorAll('[required]');
                    for (var i = 0; i < camposRequeridos.length; i++) {
                        if (!camposRequeridos[i].value) {
                            camposRequeridos[i].focus();
                            return;
                        }
                    }

                    // Los campos del modal (curso, motivo, fecha de
                    // devolución) viven fuera del <form> real y se asocian
                    // con el atributo form="...". Por las dudas de que el
                    // navegador no los tome al mandar el formulario por JS,
                    // los copiamos también como inputs ocultos DENTRO del
                    // form antes de enviarlo.
                    var camposDelModal = overlay.querySelectorAll('[name]');
                    for (var j = 0; j < camposDelModal.length; j++) {
                        var campo = camposDelModal[j];
                        if (campo.form !== formulario) continue;
                        var oculto = formulario.querySelector('input[type="hidden"][name="' + campo.name + '"]');
                        if (!oculto) {
                            oculto = document.createElement('input');
                            oculto.type = 'hidden';
                            oculto.name = campo.name;
                            formulario.appendChild(oculto);
                        }
                        oculto.value = campo.value;
                    }

                    formulario.submit();
                });
            }

            conectarConfirmacion('formulario-reserva', 'modal-confirmar-overlay', 'modal-confirmar-cerrar', 'modal-confirmar-aceptar');
            conectarConfirmacion('formulario-cancelar', 'modal-cancelar-overlay', 'modal-cancelar-cerrar', 'modal-cancelar-aceptar');
            conectarConfirmacion('formulario-recomendar', 'modal-recomendar-overlay', 'modal-recomendar-cerrar', 'modal-recomendar-aceptar');
            conectarConfirmacion('formulario-cancelar-recomendacion', 'modal-cancelar-recomendacion-overlay', 'modal-cancelar-recomendacion-cerrar', 'modal-cancelar-recomendacion-aceptar');

            // Modal de resultado (éxito o error de Reservar.php / CancelarReserva.php):
            // al cerrarlo, limpiamos el ?ok=/?error= de la URL para que un
            // refresh de la página no lo vuelva a mostrar.
            var overlayMensaje = document.getElementById('modal-mensaje-overlay');
            if (overlayMensaje) {
                function cerrarMensaje() {
                    overlayMensaje.hidden = true;
                    var url = new URL(window.location.href);
                    url.searchParams.delete('ok');
                    url.searchParams.delete('error');
                    history.replaceState({}, '', url);
                }

                document.getElementById('modal-mensaje-cerrar').addEventListener('click', cerrarMensaje);
                document.getElementById('modal-mensaje-aceptar').addEventListener('click', cerrarMensaje);
                overlayMensaje.addEventListener('click', function (evento) {
                    if (evento.target === overlayMensaje) cerrarMensaje();
                });
                document.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Escape' && !overlayMensaje.hidden) cerrarMensaje();
                });
            }
        })();
    </script>
</body>

</html>
<?php
/**
 * Admin.php
 * Panel del administrador logueado. Muestra y permite gestionar los
 * tres procesos de guardado del sistema:
 *   - Préstamos/reservas (tabla `prestamo`): marcar como devuelto.
 *   - Recomendaciones de profesores (tabla `recomendacion`): eliminar.
 *   - Mensajes de contacto (tabla `contacto`): marcar leído/no leído
 *     y eliminar (la columna `leido` ya existía en la tabla pero
 *     hasta ahora nada la usaba).
 *
 * Todas las acciones llegan por POST y redirigen de vuelta acá con
 * GET (patrón POST/Redirect/GET), igual que Reservar.php.
 */

session_set_cookie_params(['path' => '/']);
session_start();

if (!isset($_SESSION['administrador_id'])) {
    header('Location: Loginadmin.php');
    exit;
}

require 'Conexion.php';
require 'Cursos.php'; // $CURSOS, para el desplegable del filtro

// -----------------------------------------------------------------
// Filtros de "Recomendaciones" (profesor, curso, fecha desde/hasta).
// Vienen por GET para que se puedan compartir/recargar la página con
// el filtro puesto. Cuando se borra una recomendación desde acá, el
// formulario manda los mismos valores por POST (campos ocultos) para
// que el redirect de vuelta los conserve.
// -----------------------------------------------------------------
function filtrosRecomendaciones(array $origen): array
{
    return [
        'profesor' => (int) ($origen['filtro_profesor'] ?? 0),
        'curso' => trim($origen['filtro_curso'] ?? ''),
        'desde' => trim($origen['filtro_desde'] ?? ''),
        'hasta' => trim($origen['filtro_hasta'] ?? ''),
    ];
}

function queryStringFiltros(array $filtros): string
{
    $parametros = array_filter([
        'profesor' => $filtros['profesor'] > 0 ? $filtros['profesor'] : '',
        'curso' => $filtros['curso'],
        'desde' => $filtros['desde'],
        'hasta' => $filtros['hasta'],
    ], fn($v) => $v !== '');
    return http_build_query($parametros);
}

// -----------------------------------------------------------------
// Acciones
// -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'marcar_devuelto') {
        $idPrestamo = (int) ($_POST['id_prestamo'] ?? 0);
        $actualizar = $conexion->prepare(
            'UPDATE prestamo SET estado_prestamo = 0, fecha_devolucion = CURDATE() WHERE id_prestamo = ?'
        );
        $actualizar->execute([$idPrestamo]);
        header('Location: Admin.php?ok=devuelto#prestamos');
        exit;
    }

    if ($accion === 'eliminar_recomendacion') {
        $idRecomendacion = (int) ($_POST['id_recomendacion'] ?? 0);
        $eliminar = $conexion->prepare('DELETE FROM recomendacion WHERE id_recomendacion = ?');
        $eliminar->execute([$idRecomendacion]);
        $qs = queryStringFiltros(filtrosRecomendaciones($_POST));
        header('Location: Admin.php?ok=recomendacion' . ($qs !== '' ? '&' . $qs : '') . '#recomendaciones');
        exit;
    }

    if ($accion === 'marcar_leido') {
        $idContacto = (int) ($_POST['id_contacto'] ?? 0);
        $nuevoEstado = (int) ($_POST['nuevo_estado'] ?? 1);
        $actualizar = $conexion->prepare('UPDATE contacto SET leido = ? WHERE id = ?');
        $actualizar->execute([$nuevoEstado, $idContacto]);
        header('Location: Admin.php?ok=contacto#contacto');
        exit;
    }

    if ($accion === 'eliminar_contacto') {
        $idContacto = (int) ($_POST['id_contacto'] ?? 0);
        $eliminar = $conexion->prepare('DELETE FROM contacto WHERE id = ?');
        $eliminar->execute([$idContacto]);
        header('Location: Admin.php?ok=contacto#contacto');
        exit;
    }
}

// -----------------------------------------------------------------
// Datos de las 3 secciones
// -----------------------------------------------------------------
$prestamos = $conexion->query(
    'SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_estimada_de_devolución AS fecha_limite,
            p.estado_prestamo, l.título AS titulo, l.autor,
            CONCAT(e.Nombre, " ", e.Apellido) AS estudiante, e.curso
     FROM prestamo p
     JOIN ejemplar ej ON ej.id_ejemplar = p.Id_ejemplar
     JOIN libro l ON l.id_libro = ej.id_libro
     JOIN estudiante e ON e.id_estudiante = p.Id_estudiante
     ORDER BY p.estado_prestamo DESC, p.fecha_prestamo DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$filtros = filtrosRecomendaciones($_GET);

$condicionesRecomendaciones = [];
$parametrosRecomendaciones = [];

if ($filtros['profesor'] > 0) {
    $condicionesRecomendaciones[] = 'r.id_profesor = ?';
    $parametrosRecomendaciones[] = $filtros['profesor'];
}
if ($filtros['curso'] !== '') {
    $condicionesRecomendaciones[] = 'r.curso = ?';
    $parametrosRecomendaciones[] = $filtros['curso'];
}
if ($filtros['desde'] !== '') {
    $condicionesRecomendaciones[] = 'r.fecha_recomendacion >= ?';
    $parametrosRecomendaciones[] = $filtros['desde'] . ' 00:00:00';
}
if ($filtros['hasta'] !== '') {
    $condicionesRecomendaciones[] = 'r.fecha_recomendacion <= ?';
    $parametrosRecomendaciones[] = $filtros['hasta'] . ' 23:59:59';
}

$sqlRecomendaciones = 'SELECT r.id_recomendacion, r.curso, r.fecha_recomendacion,
            l.título AS titulo, l.autor,
            CONCAT(pr.Nombre, " ", pr.Apellido) AS profesor
     FROM recomendacion r
     JOIN libro l ON l.id_libro = r.id_libro
     JOIN profesor pr ON pr.id_profesor = r.id_profesor';

if ($condicionesRecomendaciones) {
    $sqlRecomendaciones .= ' WHERE ' . implode(' AND ', $condicionesRecomendaciones);
}
$sqlRecomendaciones .= ' ORDER BY r.fecha_recomendacion DESC';

$consultaRecomendaciones = $conexion->prepare($sqlRecomendaciones);
$consultaRecomendaciones->execute($parametrosRecomendaciones);
$recomendaciones = $consultaRecomendaciones->fetchAll(PDO::FETCH_ASSOC);

// Para llenar el desplegable "Profesor" del filtro (todos los
// profesores que alguna vez recomendaron algo, independientemente del
// filtro actual).
$profesoresParaFiltro = $conexion->query(
    'SELECT DISTINCT pr.id_profesor, CONCAT(pr.Nombre, " ", pr.Apellido) AS nombre
     FROM profesor pr
     JOIN recomendacion r ON r.id_profesor = pr.id_profesor
     ORDER BY nombre'
)->fetchAll(PDO::FETCH_ASSOC);

$hayFiltrosActivos = $filtros['profesor'] > 0 || $filtros['curso'] !== '' || $filtros['desde'] !== '' || $filtros['hasta'] !== '';

$mensajesContacto = $conexion->query(
    'SELECT id, nombre, email, telefono, tipo_consulta, mensaje, fecha, leido
     FROM contacto
     ORDER BY leido ASC, fecha DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$totalPrestamosActivos = count(array_filter($prestamos, fn($p) => (int) $p['estado_prestamo'] === 1));
$totalMensajesSinLeer = count(array_filter($mensajesContacto, fn($m) => (int) $m['leido'] === 0));

function formatearFechaAdmin($fechaISO)
{
    if (!$fechaISO) return '—';
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $partes = explode('-', substr($fechaISO, 0, 10));
    return (int) $partes[2] . ' ' . $meses[(int) $partes[1] - 1] . ' ' . $partes[0];
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$titulo = 'Panel de administración';
$cssExtra = ['Admin.css'];
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion aplicacion--admin">

        <header class="admin-encabezado">
            <div>
                <h1>Panel de administración</h1>
                <p>Hola, <?= htmlspecialchars($_SESSION['administrador_nombre']) ?></p>
            </div>
            <a href="Logoutadmin.php" class="admin-boton admin-boton--secundario">Cerrar sesión</a>
        </header>

        <?php if (isset($_GET['ok'])): ?>
            <p class="admin-aviso">Listo, se actualizó correctamente.</p>
        <?php endif; ?>

        <nav class="admin-resumen">
            <a href="#prestamos" class="admin-resumen__tarjeta">
                <span class="admin-resumen__valor"><?= $totalPrestamosActivos ?></span>
                <span class="admin-resumen__etiqueta">Préstamos activos</span>
            </a>
            <a href="#recomendaciones" class="admin-resumen__tarjeta">
                <span class="admin-resumen__valor"><?= count($recomendaciones) ?></span>
                <span class="admin-resumen__etiqueta">Recomendaciones</span>
            </a>
            <a href="#contacto" class="admin-resumen__tarjeta">
                <span class="admin-resumen__valor"><?= $totalMensajesSinLeer ?></span>
                <span class="admin-resumen__etiqueta">Mensajes sin leer</span>
            </a>
        </nav>

        <section id="prestamos" class="admin-seccion">
            <h2>Préstamos y reservas</h2>
            <?php if (empty($prestamos)): ?>
                <p class="admin-vacio">Todavía no hay préstamos registrados.</p>
            <?php else: ?>
                <div class="admin-tabla-wrap">
                    <table class="admin-tabla">
                        <thead>
                            <tr>
                                <th>Libro</th>
                                <th>Alumno</th>
                                <th>Curso</th>
                                <th>Prestado</th>
                                <th>Límite</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $p): ?>
                                <tr>
                                    <td data-label="Libro">
                                        <?= htmlspecialchars($p['titulo']) ?>
                                        <span class="admin-tabla__sub"><?= htmlspecialchars($p['autor']) ?></span>
                                    </td>
                                    <td data-label="Alumno"><?= htmlspecialchars($p['estudiante']) ?></td>
                                    <td data-label="Curso"><?= htmlspecialchars($p['curso']) ?></td>
                                    <td data-label="Prestado"><?= formatearFechaAdmin($p['fecha_prestamo']) ?></td>
                                    <td data-label="Límite"><?= formatearFechaAdmin($p['fecha_limite']) ?></td>
                                    <td data-label="Estado">
                                        <?php if ((int) $p['estado_prestamo'] === 1): ?>
                                            <span class="admin-badge admin-badge--activo">Activo</span>
                                        <?php else: ?>
                                            <span class="admin-badge admin-badge--listo">Devuelto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="">
                                        <?php if ((int) $p['estado_prestamo'] === 1): ?>
                                            <form method="post" onsubmit="return confirm('¿Marcar este préstamo como devuelto?');">
                                                <input type="hidden" name="accion" value="marcar_devuelto">
                                                <input type="hidden" name="id_prestamo" value="<?= (int) $p['id_prestamo'] ?>">
                                                <button type="submit" class="admin-boton">Marcar devuelto</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="recomendaciones" class="admin-seccion">
            <h2>Recomendaciones de profesores</h2>

            <form method="get" class="admin-filtros" action="Admin.php#recomendaciones">
                <div class="admin-filtros__campo">
                    <label for="filtro-profesor">Profesor</label>
                    <select name="filtro_profesor" id="filtro-profesor">
                        <option value="">Todos</option>
                        <?php foreach ($profesoresParaFiltro as $prof): ?>
                            <option value="<?= (int) $prof['id_profesor'] ?>" <?= $filtros['profesor'] === (int) $prof['id_profesor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prof['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filtros__campo">
                    <label for="filtro-curso">Curso</label>
                    <select name="filtro_curso" id="filtro-curso">
                        <option value="">Todos</option>
                        <?php foreach ($CURSOS as $curso): ?>
                            <option value="<?= htmlspecialchars($curso) ?>" <?= $filtros['curso'] === $curso ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-filtros__campo">
                    <label for="filtro-desde">Desde</label>
                    <input type="date" name="filtro_desde" id="filtro-desde" value="<?= htmlspecialchars($filtros['desde']) ?>">
                </div>
                <div class="admin-filtros__campo">
                    <label for="filtro-hasta">Hasta</label>
                    <input type="date" name="filtro_hasta" id="filtro-hasta" value="<?= htmlspecialchars($filtros['hasta']) ?>">
                </div>
                <div class="admin-filtros__acciones">
                    <button type="submit" class="admin-boton">Filtrar</button>
                    <?php if ($hayFiltrosActivos): ?>
                        <a href="Admin.php#recomendaciones" class="admin-boton admin-boton--secundario">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($hayFiltrosActivos): ?>
                <p class="admin-filtros__resultado">
                    <?= count($recomendaciones) ?> recomendación<?= count($recomendaciones) === 1 ? '' : 'es' ?> encontrada<?= count($recomendaciones) === 1 ? '' : 's' ?> con este filtro.
                </p>
            <?php endif; ?>

            <?php if (empty($recomendaciones)): ?>
                <p class="admin-vacio">
                    <?= $hayFiltrosActivos ? 'No hay recomendaciones que coincidan con el filtro.' : 'Todavía no hay recomendaciones cargadas.' ?>
                </p>
            <?php else: ?>
                <div class="admin-tabla-wrap">
                    <table class="admin-tabla">
                        <thead>
                            <tr>
                                <th>Libro</th>
                                <th>Profesor</th>
                                <th>Curso</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recomendaciones as $r): ?>
                                <tr>
                                    <td data-label="Libro">
                                        <?= htmlspecialchars($r['titulo']) ?>
                                        <span class="admin-tabla__sub"><?= htmlspecialchars($r['autor']) ?></span>
                                    </td>
                                    <td data-label="Profesor"><?= htmlspecialchars($r['profesor']) ?></td>
                                    <td data-label="Curso"><?= htmlspecialchars($r['curso'] ?? '—') ?></td>
                                    <td data-label="Fecha"><?= formatearFechaAdmin($r['fecha_recomendacion']) ?></td>
                                    <td data-label="">
                                        <form method="post" onsubmit="return confirm('¿Eliminar esta recomendación?');">
                                            <input type="hidden" name="accion" value="eliminar_recomendacion">
                                            <input type="hidden" name="id_recomendacion" value="<?= (int) $r['id_recomendacion'] ?>">
                                            <input type="hidden" name="filtro_profesor" value="<?= $filtros['profesor'] > 0 ? (int) $filtros['profesor'] : '' ?>">
                                            <input type="hidden" name="filtro_curso" value="<?= htmlspecialchars($filtros['curso']) ?>">
                                            <input type="hidden" name="filtro_desde" value="<?= htmlspecialchars($filtros['desde']) ?>">
                                            <input type="hidden" name="filtro_hasta" value="<?= htmlspecialchars($filtros['hasta']) ?>">
                                            <button type="submit" class="admin-boton admin-boton--peligro">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="contacto" class="admin-seccion">
            <h2>Mensajes de contacto</h2>
            <?php if (empty($mensajesContacto)): ?>
                <p class="admin-vacio">Todavía no llegó ningún mensaje.</p>
            <?php else: ?>
                <div class="admin-mensajes">
                    <?php foreach ($mensajesContacto as $m): ?>
                        <article class="admin-mensaje<?= (int) $m['leido'] === 0 ? ' admin-mensaje--nuevo' : '' ?>">
                            <div class="admin-mensaje__encabezado">
                                <div>
                                    <strong><?= htmlspecialchars($m['nombre']) ?></strong>
                                    <span class="admin-tabla__sub">
                                        <?= htmlspecialchars($m['email']) ?> · <?= htmlspecialchars($m['telefono']) ?>
                                    </span>
                                </div>
                                <span class="admin-tabla__sub"><?= formatearFechaAdmin($m['fecha']) ?></span>
                            </div>
                            <p class="admin-mensaje__tipo"><?= htmlspecialchars($m['tipo_consulta']) ?></p>
                            <p><?= nl2br(htmlspecialchars($m['mensaje'])) ?></p>
                            <div class="admin-mensaje__acciones">
                                <form method="post">
                                    <input type="hidden" name="accion" value="marcar_leido">
                                    <input type="hidden" name="id_contacto" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="nuevo_estado" value="<?= (int) $m['leido'] === 0 ? 1 : 0 ?>">
                                    <button type="submit" class="admin-boton">
                                        <?= (int) $m['leido'] === 0 ? 'Marcar leído' : 'Marcar no leído' ?>
                                    </button>
                                </form>
                                <form method="post" onsubmit="return confirm('¿Eliminar este mensaje?');">
                                    <input type="hidden" name="accion" value="eliminar_contacto">
                                    <input type="hidden" name="id_contacto" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="admin-boton admin-boton--peligro">Eliminar</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>
</body>

</html>

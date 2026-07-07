<?php
/**
 * CancelarReserva.php
 * Procesa el formulario "Cancelar reserva" / "Salir de la lista de espera"
 * de Detalle.php. No devuelve HTML propio: siempre redirige de vuelta a
 * Detalle.php?libro=... con ?ok=... o ?error=... según el caso.
 *
 * tipo=reserva  -> borra directamente la fila del préstamo activo en la
 *                  tabla `prestamo` (deja el ejemplar libre de nuevo).
 * tipo=espera   -> borra al alumno de `lista_espera` para ese libro.
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

$idLibro = isset($_POST['id_libro']) ? (int) $_POST['id_libro'] : 0;
$tipo = $_POST['tipo'] ?? '';

function volverADetalle(int $idLibro, array $parametros): void
{
    header('Location: Detalle.php?libro=' . $idLibro . '&' . http_build_query($parametros));
    exit;
}

if (!isset($_SESSION['estudiante_id'])) {
    volverADetalle($idLibro, ['error' => 'servidor']);
}

if ($idLibro <= 0 || !in_array($tipo, ['reserva', 'espera'], true)) {
    volverADetalle($idLibro, ['error' => 'libro_inexistente']);
}

$idEstudiante = (int) $_SESSION['estudiante_id'];

if ($tipo === 'reserva') {
    // Borramos de verdad el préstamo activo de este alumno para este libro
    // (antes solo se marcaba estado_prestamo = 0, pero quedaba la fila).
    $borrarPrestamo = mysqli_prepare(
        $conexion,
        'DELETE p FROM prestamo p
         JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
         WHERE e.id_libro = ? AND p.Id_estudiante = ? AND p.estado_prestamo = 1'
    );
    mysqli_stmt_bind_param($borrarPrestamo, 'ii', $idLibro, $idEstudiante);
    mysqli_stmt_execute($borrarPrestamo);

    if (mysqli_stmt_affected_rows($borrarPrestamo) === 0) {
        volverADetalle($idLibro, ['error' => 'nada_que_cancelar']);
    }

    volverADetalle($idLibro, ['ok' => 'cancelado']);
}

// tipo === 'espera': lo sacamos de la lista de espera.
$borrar = mysqli_prepare(
    $conexion,
    'DELETE FROM lista_espera WHERE id_estudiante = ? AND id_libro = ?'
);
mysqli_stmt_bind_param($borrar, 'ii', $idEstudiante, $idLibro);
mysqli_stmt_execute($borrar);

if (mysqli_stmt_affected_rows($borrar) === 0) {
    volverADetalle($idLibro, ['error' => 'nada_que_cancelar']);
}

volverADetalle($idLibro, ['ok' => 'espera_cancelada']);
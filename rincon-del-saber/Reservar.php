<?php
/**
 * Reservar.php
 * Procesa el formulario "Reservar libro" / "Unirme a lista de espera"
 * de Detalle.php. No devuelve HTML propio: siempre redirige de vuelta a
 * Detalle.php?libro=... con ?ok=... o ?error=... según el caso.
 *
 * Antes este archivo era una copia de Reservas.php (la pantalla "Mis
 * reservas") y nunca procesaba el POST: por eso el botón "Confirmar
 * reserva" no hacía nada. Ahora sigue el mismo patrón que
 * CancelarReserva.php.
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

$idLibro = isset($_POST['id_libro']) ? (int) $_POST['id_libro'] : 0;
$fechaDevolucion = trim($_POST['fecha_devolucion'] ?? '');

function volverADetalle(int $idLibro, array $parametros): void
{
    header('Location: Detalle.php?libro=' . $idLibro . '&' . http_build_query($parametros));
    exit;
}

if (!isset($_SESSION['estudiante_id'])) {
    volverADetalle($idLibro, ['error' => 'servidor']);
}

if ($idLibro <= 0) {
    volverADetalle($idLibro, ['error' => 'libro_inexistente']);
}

$idEstudiante = (int) $_SESSION['estudiante_id'];

// El libro tiene que existir de verdad.
$consultaLibro = mysqli_prepare($conexion, 'SELECT id_libro FROM libro WHERE id_libro = ?');
mysqli_stmt_bind_param($consultaLibro, 'i', $idLibro);
mysqli_stmt_execute($consultaLibro);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($consultaLibro))) {
    volverADetalle($idLibro, ['error' => 'libro_inexistente']);
}

// ¿Este alumno ya tiene un préstamo activo de este libro?
$consultaPropio = mysqli_prepare(
    $conexion,
    'SELECT 1 FROM prestamo p
     JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
     WHERE e.id_libro = ? AND p.Id_estudiante = ? AND p.estado_prestamo = 1
     LIMIT 1'
);
mysqli_stmt_bind_param($consultaPropio, 'ii', $idLibro, $idEstudiante);
mysqli_stmt_execute($consultaPropio);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($consultaPropio))) {
    volverADetalle($idLibro, ['error' => 'ya_reservado']);
}

// ¿Ya está anotado en la lista de espera de este libro?
$consultaEspera = mysqli_prepare(
    $conexion,
    'SELECT 1 FROM lista_espera WHERE id_estudiante = ? AND id_libro = ? LIMIT 1'
);
mysqli_stmt_bind_param($consultaEspera, 'ii', $idEstudiante, $idLibro);
mysqli_stmt_execute($consultaEspera);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($consultaEspera))) {
    volverADetalle($idLibro, ['error' => 'ya_en_espera']);
}

// Buscamos un ejemplar libre (que no tenga un préstamo activo en este momento).
$consultaEjemplar = mysqli_prepare(
    $conexion,
    'SELECT e.id_ejemplar FROM ejemplar e
     LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
     WHERE e.id_libro = ? AND p.Id_ejemplar IS NULL
     LIMIT 1'
);
mysqli_stmt_bind_param($consultaEjemplar, 'i', $idLibro);
mysqli_stmt_execute($consultaEjemplar);
$ejemplar = mysqli_fetch_assoc(mysqli_stmt_get_result($consultaEjemplar));

if ($ejemplar) {
    // Hay un ejemplar libre: se presta directo con la fecha de
    // devolución que eligió el alumno en el modal (o +7 días si por
    // algún motivo no llegó).
    if ($fechaDevolucion === '') {
        $fechaDevolucion = date('Y-m-d', strtotime('+7 days'));
    }

    $idEjemplar = (int) $ejemplar['id_ejemplar'];
    $insertar = mysqli_prepare(
        $conexion,
        'INSERT INTO prestamo (Id_ejemplar, Id_estudiante, fecha_prestamo, fecha_estimada_de_devolución, estado_prestamo)
         VALUES (?, ?, CURDATE(), ?, 1)'
    );
    mysqli_stmt_bind_param($insertar, 'iis', $idEjemplar, $idEstudiante, $fechaDevolucion);
    mysqli_stmt_execute($insertar);

    volverADetalle($idLibro, ['ok' => 'reservado']);
}

// No hay ejemplares libres: lo anotamos en la lista de espera.
$insertarEspera = mysqli_prepare(
    $conexion,
    'INSERT INTO lista_espera (id_estudiante, id_libro, fecha_solicitud) VALUES (?, ?, NOW())'
);
mysqli_stmt_bind_param($insertarEspera, 'ii', $idEstudiante, $idLibro);
mysqli_stmt_execute($insertarEspera);

volverADetalle($idLibro, ['ok' => 'espera']);
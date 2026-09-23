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
 * Cancelarreserva.php.
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
$consultaLibro = $conexion->prepare('SELECT id_libro FROM libro WHERE id_libro = ?');
$consultaLibro->execute([$idLibro]);
if (!$consultaLibro->fetch(PDO::FETCH_ASSOC)) {
    volverADetalle($idLibro, ['error' => 'libro_inexistente']);
}

// ¿Este alumno ya tiene un préstamo activo de este libro?
$consultaPropio = $conexion->prepare(
    'SELECT 1 FROM prestamo p
     JOIN ejemplar e ON e.id_ejemplar = p.Id_ejemplar
     WHERE e.id_libro = ? AND p.Id_estudiante = ? AND p.estado_prestamo = 1
     LIMIT 1'
);
$consultaPropio->execute([$idLibro, $idEstudiante]);
if ($consultaPropio->fetch(PDO::FETCH_ASSOC)) {
    volverADetalle($idLibro, ['error' => 'ya_reservado']);
}

// ¿Ya está anotado en la lista de espera de este libro?
$consultaEspera = $conexion->prepare(
    'SELECT 1 FROM lista_espera WHERE id_estudiante = ? AND id_libro = ? LIMIT 1'
);
$consultaEspera->execute([$idEstudiante, $idLibro]);
if ($consultaEspera->fetch(PDO::FETCH_ASSOC)) {
    volverADetalle($idLibro, ['error' => 'ya_en_espera']);
}

// Buscamos un ejemplar libre (que no tenga un préstamo activo en este momento).
$consultaEjemplar = $conexion->prepare(
    'SELECT e.id_ejemplar FROM ejemplar e
     LEFT JOIN prestamo p ON p.Id_ejemplar = e.id_ejemplar AND p.estado_prestamo = 1
     WHERE e.id_libro = ? AND p.Id_ejemplar IS NULL
     LIMIT 1'
);
$consultaEjemplar->execute([$idLibro]);
$ejemplar = $consultaEjemplar->fetch(PDO::FETCH_ASSOC);

if ($ejemplar) {
    // Hay un ejemplar libre: se presta directo con la fecha de
    // devolución que eligió el alumno en el modal (o +7 días si por
    // algún motivo no llegó).
    if ($fechaDevolucion === '') {
        $fechaDevolucion = date('Y-m-d', strtotime('+7 days'));
    }

    $idEjemplar = (int) $ejemplar['id_ejemplar'];
    $insertar = $conexion->prepare(
        'INSERT INTO prestamo (Id_ejemplar, Id_estudiante, fecha_prestamo, fecha_estimada_de_devolución, estado_prestamo)
         VALUES (?, ?, CURDATE(), ?, 1)'
    );
    $insertar->execute([$idEjemplar, $idEstudiante, $fechaDevolucion]);

    volverADetalle($idLibro, ['ok' => 'reservado']);
}

// No hay ejemplares libres: lo anotamos en la lista de espera.
$insertarEspera = $conexion->prepare(
    'INSERT INTO lista_espera (id_estudiante, id_libro, fecha_solicitud) VALUES (?, ?, NOW())'
);
$insertarEspera->execute([$idEstudiante, $idLibro]);

volverADetalle($idLibro, ['ok' => 'espera']);

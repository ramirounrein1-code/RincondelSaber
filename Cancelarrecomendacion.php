<?php
/**
 * Cancelarrecomendacion.php
 * Borra una recomendación del profesor logueado. La puede pedir tanto
 * Detalle.php (manda id_libro, y volvemos ahí) como Recomendar.php
 * (manda id_recomendacion, y volvemos a la lista).
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

if (!isset($_SESSION['profesor_id'])) {
    header('Location: Loginprofesor.php');
    exit;
}

$idProfesor = (int) $_SESSION['profesor_id'];
$origen = ($_POST['origen'] ?? '') === 'detalle' ? 'detalle' : 'lista';
$idLibro = isset($_POST['id_libro']) ? (int) $_POST['id_libro'] : 0;
$idRecomendacion = isset($_POST['id_recomendacion']) ? (int) $_POST['id_recomendacion'] : 0;

function volver(string $origen, int $idLibro): void
{
    if ($origen === 'detalle' && $idLibro > 0) {
        header('Location: Detalle.php?libro=' . $idLibro . '&ok=recomendacion_cancelada');
    } else {
        header('Location: Recomendar.php');
    }
    exit;
}

// Siempre filtramos por Id_profesor de la sesión: un profesor no puede
// borrar la recomendación de otro aunque le mande el id a mano.
if ($idRecomendacion > 0) {
    $borrar = $conexion->prepare(
        'DELETE FROM recomendacion WHERE id_recomendacion = ? AND id_profesor = ?'
    );
    $borrar->execute([$idRecomendacion, $idProfesor]);
} elseif ($idLibro > 0) {
    $borrar = $conexion->prepare(
        'DELETE FROM recomendacion WHERE id_libro = ? AND id_profesor = ?'
    );
    $borrar->execute([$idLibro, $idProfesor]);
}

volver($origen, $idLibro);

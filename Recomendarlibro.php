<?php
/**
 * Recomendarlibro.php
 * Procesa el formulario "Recomendar libro". Lo puede mandar tanto
 * Detalle.php (recomendar el libro que se está viendo) como
 * Recomendar.php (elegir cualquier libro desde la pestaña "Recomendar
 * libro"). El campo oculto "origen" indica a cuál de las dos hay que
 * volver: no comparten página, cada una vuelve a la suya.
 */

session_set_cookie_params(['path' => '/']);
session_start();

require 'Conexion.php';

$idLibro = isset($_POST['id_libro']) ? (int) $_POST['id_libro'] : 0;
$origen = ($_POST['origen'] ?? '') === 'recomendar' ? 'recomendar' : 'detalle';

function volverProfesor(string $origen, int $idLibro, array $parametros): void
{
    if ($origen === 'recomendar') {
        header('Location: Recomendar.php?' . http_build_query($parametros));
    } else {
        header('Location: Detalle.php?libro=' . $idLibro . '&' . http_build_query($parametros));
    }
    exit;
}

// Hay que estar logueado como profesor para recomendar.
if (!isset($_SESSION['profesor_id'])) {
    volverProfesor($origen, $idLibro, ['error' => 'servidor']);
}

if ($idLibro <= 0) {
    volverProfesor($origen, $idLibro, ['error' => 'libro_inexistente']);
}

$idProfesor = (int) $_SESSION['profesor_id'];
$curso = trim($_POST['curso'] ?? '');

// El libro tiene que existir de verdad.
$consultaLibro = $conexion->prepare('SELECT id_libro FROM libro WHERE id_libro = ?');
$consultaLibro->execute([$idLibro]);
if (!$consultaLibro->fetch(PDO::FETCH_ASSOC)) {
    volverProfesor($origen, $idLibro, ['error' => 'libro_inexistente']);
}

// El curso tiene que ser uno de la lista fija (ver Cursos.php), así el
// profesor no puede escribir cualquier cosa a mano.
require 'Cursos.php';
$cursosValidos = $CURSOS;
if ($curso === '' || !in_array($curso, $cursosValidos, true)) {
    volverProfesor($origen, $idLibro, ['error' => 'curso_invalido']);
}

// ¿Este profesor ya recomendó este libro? Si es así no se puede
// recomendar de nuevo: primero hay que cancelar la recomendación
// existente.
$consultaExistente = $conexion->prepare(
    'SELECT 1 FROM recomendacion WHERE id_libro = ? AND id_profesor = ? LIMIT 1'
);
$consultaExistente->execute([$idLibro, $idProfesor]);
if ($consultaExistente->fetch(PDO::FETCH_ASSOC)) {
    volverProfesor($origen, $idLibro, ['error' => 'ya_recomendado']);
}

$insertar = $conexion->prepare(
    'INSERT INTO recomendacion (id_libro, id_profesor, curso, fecha_recomendacion)
     VALUES (?, ?, ?, CURDATE())'
);
$insertar->execute([$idLibro, $idProfesor, $curso]);

volverProfesor($origen, $idLibro, ['ok' => 'recomendado']);
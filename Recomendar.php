<?php
/**
 * Recomendar.php
 * Panel del profesor logueado: lista de recomendaciones ya hechas
 * ("Mis recomendaciones"). Recomendar un libro nuevo (o cancelar una
 * recomendación existente) se hace siempre desde el detalle del libro
 * (Detalle.php), así el profesor ve la ficha completa antes de
 * recomendar. Por eso esta pantalla ya no tiene el formulario
 * "Recomendar libro" con el desplegable de cualquier libro del
 * catálogo: solo muestra lo ya recomendado, con un botón "Detalles"
 * que lleva a Detalle.php (desde ahí también se cancela).
 */

session_set_cookie_params(['path' => '/']);
session_start();

if (!isset($_SESSION['profesor_id'])) {
    header('Location: Loginprofesor.php');
    exit;
}

require 'Conexion.php';

$idProfesor = (int) $_SESSION['profesor_id'];

require 'Cursos.php';
$cursoFiltro = $_GET['curso'] ?? '';
if (!in_array($cursoFiltro, $CURSOS, true)) {
    $cursoFiltro = ''; // valor inválido o vacío = sin filtro, se ven todas
}

// -----------------------------------------------------------------
// Listado de las recomendaciones del profesor logueado (solo las
// suyas). Por defecto se ven todas las propias; si elige un curso en
// el desplegable, se filtran además por ese curso.
// -----------------------------------------------------------------
if ($cursoFiltro !== '') {
    $consultaPropias = $conexion->prepare(
        'SELECT r.id_recomendacion, r.id_libro, r.curso, r.fecha_recomendacion, r.id_profesor,
                l.título AS titulo, l.autor, l.portada
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         WHERE r.id_profesor = ? AND r.curso = ?
         ORDER BY r.fecha_recomendacion DESC'
    );
    $consultaPropias->execute([$idProfesor, $cursoFiltro]);
} else {
    $consultaPropias = $conexion->prepare(
        'SELECT r.id_recomendacion, r.id_libro, r.curso, r.fecha_recomendacion, r.id_profesor,
                l.título AS titulo, l.autor, l.portada
         FROM recomendacion r
         JOIN libro l ON l.id_libro = r.id_libro
         WHERE r.id_profesor = ?
         ORDER BY r.fecha_recomendacion DESC'
    );
    $consultaPropias->execute([$idProfesor]);
}
$recomendaciones = $consultaPropias->fetchAll(PDO::FETCH_ASSOC);

function formatearFechaRecomendacion($fechaISO)
{
    if (!$fechaISO) return '';
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $partes = explode('-', substr($fechaISO, 0, 10));
    return (int) $partes[2] . ' ' . $meses[(int) $partes[1] - 1] . ' ' . $partes[0];
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$titulo = 'Mis recomendaciones';
$cssExtra = ['Reservas.css', 'Recomendar.css'];
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion">

        <?php
        $tituloEncabezado = 'Mis recomendaciones';
        $volverHref = 'index.php';
        require 'componentes/encabezado-volver.php';
        ?>

        <!-- Filtro opcional por curso: por defecto se ven todas las
             recomendaciones propias; tocar un chip solo restringe la
             vista, no borra ni cambia nada. Chips en vez de <select>
             nativo porque en celular el desplegable del sistema tapa
             media pantalla y es incómodo de tocar. -->
        <div class="filtro-curso" role="tablist" aria-label="Filtrar por curso">
            <a href="Recomendar.php" class="filtro-curso__chip<?= $cursoFiltro === '' ? ' filtro-curso__chip--activo' : '' ?>">Todos</a>
            <?php foreach ($CURSOS as $curso): ?>
                <a href="Recomendar.php?curso=<?= urlencode($curso) ?>"
                    class="filtro-curso__chip<?= $cursoFiltro === $curso ? ' filtro-curso__chip--activo' : '' ?>"><?= htmlspecialchars($curso) ?></a>
            <?php endforeach; ?>
        </div>

        <!-- Para recomendar un libro nuevo, andá al detalle del libro
             desde el catálogo y usá el botón "Recomendar libro" de ahí. -->
        <section class="reservas-lista">
            <?php if (empty($recomendaciones) && $cursoFiltro !== ''): ?>
                <p class="reservas-lista__vacio">
                    Todavía no recomendaste ningún libro para <?= htmlspecialchars($cursoFiltro) ?>.
                    <a href="Recomendar.php">Ver todas las tuyas</a>
                </p>
            <?php elseif (empty($recomendaciones)): ?>
                <p class="reservas-lista__vacio">
                    Todavía no recomendaste ningún libro. Para recomendar uno,
                    entrá al <a href="catalogo.php">catálogo</a> y abrí el
                    detalle del libro.
                </p>
            <?php else: ?>
                <?php foreach ($recomendaciones as $rec): ?>
                    <article class="reserva-tarjeta">
                        <figure class="reserva-tarjeta__portada">
                            <img src="<?= htmlspecialchars($rec['portada'] ?? '') ?>"
                                alt="Portada de <?= htmlspecialchars($rec['titulo']) ?>">
                        </figure>
                        <div class="reserva-tarjeta__cuerpo">
                            <div class="reserva-tarjeta__encabezado">
                                <div>
                                    <h3 class="reserva-tarjeta__titulo"><?= htmlspecialchars($rec['titulo']) ?></h3>
                                    <p class="reserva-tarjeta__autor"><?= htmlspecialchars($rec['autor']) ?></p>
                                </div>
                                <span class="reserva-tarjeta__badge reserva-tarjeta__badge--reservado"><?= htmlspecialchars($rec['curso']) ?></span>
                            </div>
                            <ul class="reserva-tarjeta__datos">
                                <li class="reserva-tarjeta__dato">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <polygon points="4,5 20,5 20,20 4,20" />
                                        <polyline points="4,10 20,10" />
                                        <polyline points="8,3 8,6" />
                                        <polyline points="16,3 16,6" />
                                    </svg>
                                    <span>Recomendado el <?= formatearFechaRecomendacion($rec['fecha_recomendacion']) ?></span>
                                </li>
                            </ul>
                            <a class="boton-detalle" href="Detalle.php?libro=<?= (int) $rec['id_libro'] ?>">Ver detalles</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </main>

    <?php
    $navActivo = 'reservas';
    $haySesionProfesor = true;
    require 'componentes/nav-inferior.php';
    ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>

</body>

</html>
<?php
/**
 * Perfilprofesor.php
 * Perfil del profesor logueado. Mismo patrón de pantalla fija que
 * Perfil.php (el alumno): el <main> no se desliza, solo scrollea el
 * bloque .perfil-scroll de adentro.
 */

session_set_cookie_params(['path' => '/']);
session_start();

if (!isset($_SESSION['profesor_id'])) {
    header('Location: Loginprofesor.php');
    exit;
}

require 'Conexion.php';

$idProfesor = (int) $_SESSION['profesor_id'];

$consulta = $conexion->prepare(
    'SELECT COUNT(*) AS total FROM recomendacion WHERE id_profesor = ?'
);
$consulta->execute([$idProfesor]);
$fila = $consulta->fetch(PDO::FETCH_ASSOC);
$totalRecomendaciones = (int) $fila['total'];

$consultaCursos = $conexion->prepare(
    'SELECT COUNT(DISTINCT curso) AS total FROM recomendacion WHERE id_profesor = ?'
);
$consultaCursos->execute([$idProfesor]);
$filaCursos = $consultaCursos->fetch(PDO::FETCH_ASSOC);
$totalCursos = (int) $filaCursos['total'];
?>
<!DOCTYPE html>
<html lang="es">

<?php
$titulo = 'Mi perfil';
$cssExtra = ['Perfil.css'];
$sinAnimaciones = true; // pantalla de perfil: sin animaciones, a pedido
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion aplicacion--perfil" data-shell-fijo>

        <?php
        $tituloEncabezado = 'Mi perfil';
        $mostrarBotonVolver = false;
        require 'componentes/encabezado-volver.php';
        ?>

        <div class="perfil-scroll">

            <section class="perfil-tarjeta">
                <span class="perfil-avatar" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <ellipse cx="12" cy="8" rx="4" ry="4" />
                        <polyline
                            points="4,21 4.35,20.08 4.76,19.25 5.22,18.51 5.72,17.84 6.26,17.26 6.83,16.75 7.44,16.31 8.06,15.94 8.71,15.64 9.36,15.4 10.03,15.22 10.69,15.1 11.35,15.02 12,15 12.65,15.02 13.31,15.1 13.97,15.22 14.64,15.4 15.29,15.64 15.94,15.94 16.56,16.31 17.17,16.75 17.74,17.26 18.28,17.84 18.78,18.51 19.24,19.25 19.65,20.08 20,21" />
                    </svg>
                </span>
                <h2 class="perfil-tarjeta__nombre">
                    <?= htmlspecialchars($_SESSION['profesor_nombre'] . ' ' . $_SESSION['profesor_apellido']) ?>
                </h2>
                <p class="perfil-tarjeta__detalle">Panel docente</p>
            </section>

            <div class="detalle-libro__ficha perfil-stats">
                <div class="ficha-dato">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                    </svg>
                    <span class="ficha-dato__valor"><?= $totalRecomendaciones ?></span>
                    <span class="ficha-dato__etiqueta">Recomendaciones</span>
                </div>
                <div class="ficha-dato">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="6,3 12,3 18,3 18,12 18,21 15,19 12,17 9,19 6,21" />
                    </svg>
                    <span class="ficha-dato__valor"><?= $totalCursos ?></span>
                    <span class="ficha-dato__etiqueta">Cursos alcanzados</span>
                </div>
            </div>

            <ul class="perfil-menu">
                <li>
                    <a class="perfil-menu__item" href="Recomendar.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="12,3 14.12,9.09 20.56,9.22 15.42,13.11 17.29,19.28 12,15.6 6.71,19.28 8.58,13.11 3.44,9.22 9.88,9.09" />
                        </svg>
                        <span>Mis recomendaciones</span>
                        <svg class="perfil-menu__flecha" viewBox="0 0 24 24" aria-hidden="true">
                            <polyline points="9,4 15,12 9,20" />
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="perfil-menu__item" href="catalogo.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polygon points="3,3 10,3 10,10 3,10" />
                            <polygon points="14,3 21,3 21,10 14,10" />
                            <polygon points="3,14 10,14 10,21 3,21" />
                            <polygon points="14,14 21,14 21,21 14,21" />
                        </svg>
                        <span>Explorar el catálogo</span>
                        <svg class="perfil-menu__flecha" viewBox="0 0 24 24" aria-hidden="true">
                            <polyline points="9,4 15,12 9,20" />
                        </svg>
                    </a>
                </li>
                <li>
                    <a class="perfil-menu__item perfil-menu__item--peligro" href="Logoutprofesor.php">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15,3 20,3 20,21 15,21" />
                            <polyline points="10,17 15,12 10,7" />
                            <polyline points="15,12 3,12" />
                        </svg>
                        <span>Cerrar sesión</span>
                    </a>
                </li>
            </ul>

            <?php require 'componentes/info-biblioteca.php'; ?>

        </div>

    </main>

    <?php
    $navActivo = 'perfil';
    $haySesionProfesor = true;
    require 'componentes/nav-inferior.php';
    ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>
</body>

</html>
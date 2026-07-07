<?php
/**
 * conexion.php
 * Conexión mysqli reutilizable. La incluyen login.php y logout.php.
 *
 * Datos por defecto de XAMPP/WAMP: usuario "root", sin contraseña.
 * Si le pusiste contraseña a tu MySQL, cambiala acá abajo.
 */

// -----------------------------------------------------------------------
// Si algo explota (consulta mal escrita, columna que no existe, se cae
// el servidor de MySQL, etc.) esto lo atajamos ACÁ, en un solo lugar,
// en vez de que cada página termine mostrando el "Fatal error" en blanco.
// Como Conexion.php se incluye al principio de cada página (antes de
// imprimir HTML), podemos armar una pantalla de error completa y prolija.
// -----------------------------------------------------------------------
set_exception_handler(function ($excepcion) {
    error_log((string) $excepcion);
    if (!headers_sent()) {
        http_response_code(500);
    }
    ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ocurrió un error · Rincón del Saber</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
</head>

<body>
    <main class="aplicacion"
        style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:32px 24px;">
        <svg viewBox="0 0 24 24" aria-hidden="true"
            style="width:48px;height:48px;stroke:var(--naranja);stroke-width:1.8;margin-bottom:16px;">
            <ellipse cx="12" cy="12" rx="9" ry="9" />
            <line x1="12" y1="8" x2="12" y2="13" />
            <line x1="12" y1="16.3" x2="12" y2="16.31" />
        </svg>
        <h1 style="font-family:var(--tipografia-titulo);font-size:18px;margin:0 0 8px;">Algo salió mal</h1>
        <p style="font-size:13px;color:var(--texto-muted);margin:0 0 24px;max-width:280px;">
            Tuvimos un problema al cargar esta página. Volvé atrás e intentá de nuevo.
        </p>
        <button type="button" class="boton-reservar" style="width:auto;padding:12px 28px;"
            onclick="if (document.referrer) { history.back(); } else { window.location.href = 'catalogo.php'; }">
            Volver
        </button>
    </main>
</body>

</html>
<?php
    exit;
});

$DB_HOST = 'localhost';
$DB_USUARIO = 'root';
$DB_CONTRASENA = '';
$DB_NOMBRE = 'rincon_del_saber';

$conexion = mysqli_connect($DB_HOST, $DB_USUARIO, $DB_CONTRASENA, $DB_NOMBRE);

if (!$conexion) {
    error_log('Error de conexión a MySQL: ' . mysqli_connect_error());
    header('Location: Login.php?error=servidor');
    exit;
}

mysqli_set_charset($conexion, 'utf8mb4');
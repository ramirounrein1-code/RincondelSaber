<?php
/**
 * LoginProfesor.php
 * Login exclusivo para profesores. Usa una clave de sesión distinta
 * a la del alumno (profesor_id en vez de estudiante_id) para que un
 * mismo navegador no mezcle las dos sesiones.
 *
 * La tabla `profesor` guarda la contraseña tal cual (sin hash), igual
 * que `estudiante` guarda la matrícula, así que se compara directo.
 */

session_set_cookie_params(['path' => '/']);
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'Conexion.php';

    $email = trim($_POST['email'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if ($email === '' || $contrasena === '') {
        $error = 'Completá todos los campos.';
    } else {
        $consulta = mysqli_prepare(
            $conexion,
            'SELECT id_profesor, Nombre, Apellido, email FROM profesor WHERE email = ? AND contraseña = ?'
        );
        mysqli_stmt_bind_param($consulta, 'ss', $email, $contrasena);
        mysqli_stmt_execute($consulta);
        $resultado = mysqli_stmt_get_result($consulta);
        $profesor = mysqli_fetch_assoc($resultado);

        if (!$profesor) {
            $error = 'Email o contraseña incorrectos.';
        } else {
            $_SESSION['profesor_id'] = $profesor['id_profesor'];
            $_SESSION['profesor_nombre'] = $profesor['Nombre'];
            $_SESSION['profesor_apellido'] = $profesor['Apellido'];
            $_SESSION['profesor_email'] = $profesor['email'];
            header('Location: Recomendar.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso de profesores · Rincón del Saber</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Zalando+Sans:wght@400;500;600;700&family=Arimo:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="detalle.css">
    <link rel="stylesheet" href="Auth.css">
</head>

<body>
    <main class="aplicacion aplicacion--auth">

        <header class="encabezado-detalle">
            <button type="button" class="boton-volver" id="boton-volver" aria-label="Volver">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <polyline points="15,4 9,12 15,20" />
                </svg>
            </button>
            <h1>Ingreso de profesores</h1>
            <span class="encabezado-detalle__relleno" aria-hidden="true"></span>
        </header>

        <div class="auth-logo">
            <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <polygon
                    points="24,12 23.33,11.49 22.6,11.05 21.83,10.67 21.01,10.35 20.16,10.09 19.28,9.89 18.38,9.75 17.46,9.67 16.53,9.66 15.6,9.7 14.68,9.81 13.77,9.98 12.87,10.21 12,10.5 12,21.5 12,32.5 12.87,32.21 13.77,31.98 14.68,31.81 15.6,31.7 16.53,31.66 17.46,31.67 18.38,31.75 19.28,31.89 20.16,32.09 21.01,32.35 21.83,32.67 22.6,33.05 23.33,33.49 24,34 24.67,33.49 25.4,33.05 26.17,32.67 26.99,32.35 27.84,32.09 28.72,31.89 29.62,31.75 30.54,31.67 31.47,31.66 32.4,31.7 33.32,31.81 34.23,31.98 35.13,32.21 36,32.5 36,21.5 36,10.5 35.06,10.19 34.09,9.95 33.11,9.78 32.11,9.68 31.11,9.66 30.12,9.7 29.14,9.82 28.18,10 27.25,10.26 26.36,10.59 25.52,10.99 24.73,11.46 24,12" />
            </svg>
            <h1>Panel docente</h1>
            <p>Ingresá para recomendar libros a tus cursos</p>
        </div>

        <?php if ($error): ?>
            <p class="auth-mensaje auth-mensaje--error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form class="auth-formulario" action="LoginProfesor.php" method="post" novalidate>
            <label class="modal__campo">
                Email
                <input type="email" name="email" placeholder="tu.nombre@rincondelsaber.edu.ar" required autofocus
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </label>
            <label class="modal__campo">
                Contraseña
                <input type="password" name="contrasena" placeholder="••••••" required>
            </label>
            <button type="submit" class="boton-reservar">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15,3 20,3 20,21 15,21" />
                    <polyline points="10,7 15,12 10,17" />
                    <polyline points="15,12 3,12" />
                </svg>
                Iniciar sesión
            </button>
        </form>

        <p class="auth-alterno">
            ¿Sos alumno/a? <a href="Login.php">Iniciá sesión como estudiante</a>
        </p>

    </main>

    <script>
        document.getElementById('boton-volver').addEventListener('click', function () {
            window.location.href = 'index.php';
        });
    </script>
</body>

</html>
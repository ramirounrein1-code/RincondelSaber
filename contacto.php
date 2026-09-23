<?php
/**
 * contacto.php
 * Formulario de contacto de la biblioteca. No requiere sesión
 * iniciada (lo puede usar cualquier visitante), pero si hay un alumno
 * logueado le precompletamos nombre y email para que no los tenga que
 * volver a escribir.
 *
 * Flujo:
 *   1) GET  -> se muestra el formulario vacío (o precompletado si hay
 *              sesión de alumno).
 *   2) POST -> se capturan los 5 campos, se sanitizan, se validan y:
 *        - si hay errores, se vuelve a mostrar el formulario con los
 *          valores que el visitante ya había escrito (no se pierde
 *          nada) más la lista roja de errores arriba.
 *        - si no hay errores, se muestra la tarjeta verde de éxito.
 *
 * Nota: esta entrega cubre captura + sanitización + validación +
 * feedback, tal como pide la consigna. No envía el mensaje por mail()
 * ni lo guarda en la base todavía; ese sería el siguiente paso natural
 * (agregar una tabla `contacto` y un INSERT acá mismo, con Conexion.php,
 * o mail() con las credenciales SMTP correspondientes).
 */

session_set_cookie_params(['path' => '/']);
session_start();

// A dónde vuelve el botón "<" del encabezado, según quién esté mirando
// la pantalla.
if (isset($_SESSION['estudiante_id'])) {
    $volverHref = 'Perfil.php';
} elseif (isset($_SESSION['profesor_id'])) {
    $volverHref = 'Perfilprofesor.php';
} else {
    $volverHref = 'index.php';
}

// Tipos de consulta disponibles en el desplegable. Es un array
// asociativo (valor guardado => texto mostrado) para que el value del
// <option> no dependa de tildes/mayúsculas.
$TIPOS_CONSULTA = [
    'general' => 'Consulta general',
    'prestamos' => 'Préstamos y reservas',
    'recomendacion' => 'Recomendación de libros',
    'sugerencia' => 'Sugerencia',
    'reclamo' => 'Reclamo',
    'otro' => 'Otro',
];

$errores = [];
$enviado = false;

$valores = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'tipo_consulta' => '',
    'mensaje' => '',
];

// Si hay un alumno logueado y todavía no mandó el formulario,
// precompletamos con sus datos reales.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['estudiante_id'])) {
    $valores['nombre'] = trim(($_SESSION['estudiante_nombre'] ?? '') . ' ' . ($_SESSION['estudiante_apellido'] ?? ''));
    $valores['email'] = $_SESSION['estudiante_email'] ?? '';
}

/**
 * Sanitiza un campo de texto libre: saca espacios de los extremos y
 * cualquier etiqueta HTML/JS que hayan intentado meter (<script>,
 * <img onerror=...>, etc.). Después, al mostrarlo en el HTML, todavía
 * pasa por htmlspecialchars() como segunda capa de protección.
 */
function sanitizarTexto(string $valor): string
{
    return trim(strip_tags($valor));
}

/**
 * Cuenta caracteres de forma segura para texto con tildes/ñ (mb_strlen
 * cuenta caracteres UTF-8 reales, no bytes). Si el servidor no tiene
 * la extensión mbstring habilitada, cae en strlen(): no se rompe,
 * aunque una tilde de más podría contar como 2. En XAMPP mbstring
 * viene activada por defecto.
 */
function contarCaracteres(string $valor): int
{
    return function_exists('mb_strlen') ? mb_strlen($valor) : strlen($valor);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -----------------------------------------------------------------
    // 1) Captura por POST + sanitización de los 5 campos.
    // -----------------------------------------------------------------
    $nombre = sanitizarTexto($_POST['nombre'] ?? '');
    $telefono = sanitizarTexto($_POST['telefono'] ?? '');
    $tipoConsulta = sanitizarTexto($_POST['tipo_consulta'] ?? '');
    $mensaje = sanitizarTexto($_POST['mensaje'] ?? '');

    // El email tiene su propio sanitizador: filter_var(FILTER_SANITIZE_EMAIL)
    // saca cualquier carácter que no pueda aparecer en una dirección válida.
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

    // El teléfono, además de sanitizarlo como texto, nos quedamos solo
    // con los caracteres que tienen sentido en un número (dígitos,
    // espacios y + - ( )); así no llega nada raro aunque el campo no
    // sea obligatoriamente numérico puro (hay gente que escribe
    // "011 4567-8900" o "+54 9 11 1234-5678").
    $telefono = preg_replace('/[^0-9 +\-()]/', '', $telefono);

    // Guardamos ya los valores sanitizados: si el formulario vuelve a
    // mostrarse por algún error, el visitante encuentra todo tal como
    // lo había escrito (menos las etiquetas HTML que se sacaron).
    $valores = [
        'nombre' => $nombre,
        'email' => $email,
        'telefono' => $telefono,
        'tipo_consulta' => $tipoConsulta,
        'mensaje' => $mensaje,
    ];

    // -----------------------------------------------------------------
    // 2) Validación
    // -----------------------------------------------------------------
    if ($nombre === '') {
        $errores[] = 'Ingresá tu nombre y apellido.';
    } elseif (contarCaracteres($nombre) < 3) {
        $errores[] = 'El nombre es demasiado corto.';
    }

    if ($email === '') {
        $errores[] = 'Ingresá tu email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // FILTER_VALIDATE_EMAIL valida el formato general de un email
        // (algo@dominio.algo) siguiendo la sintaxis de la RFC 822/5322.
        $errores[] = 'El email no tiene un formato válido.';
    }

    if ($telefono === '') {
        $errores[] = 'Ingresá tu teléfono.';
    } elseif (!preg_match('/^[0-9 +\-()]{6,20}$/', $telefono)) {
        $errores[] = 'El teléfono tiene que tener entre 6 y 20 caracteres, solo números, espacios y los símbolos + - ( ).';
    }

    if ($tipoConsulta === '' || !array_key_exists($tipoConsulta, $TIPOS_CONSULTA)) {
        $errores[] = 'Elegí un tipo de consulta de la lista.';
    }

    if ($mensaje === '') {
        $errores[] = 'Escribí tu mensaje.';
    } elseif (contarCaracteres($mensaje) < 15) {
        $errores[] = 'El mensaje tiene que tener al menos 15 caracteres (por ahora tiene ' . contarCaracteres($mensaje) . ').';
    }

    if (empty($errores)) {
        // -------------------------------------------------------------
        // 3) Guardado en la base de datos.
        //
        // Conexion.php se conecta con PDO (no mysqli), así que acá
        // usamos prepare()/execute() de PDO. Además, PDO::ERRMODE_EXCEPTION
        // (configurado en Conexion.php) hace que cualquier error de SQL
        // tire una excepción en vez de devolver false, por eso todo va
        // dentro de un try/catch: si no lo atrapáramos, un error de base
        // de datos cortaría la página entera con un 500 en blanco.
        // -------------------------------------------------------------
        try {
            require 'Conexion.php';

            $insertar = $conexion->prepare(
                'INSERT INTO contacto (nombre, email, telefono, tipo_consulta, mensaje, fecha)
                 VALUES (:nombre, :email, :telefono, :tipo_consulta, :mensaje, NOW())'
            );

            $insertar->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':telefono' => $telefono,
                ':tipo_consulta' => $tipoConsulta,
                ':mensaje' => $mensaje,
            ]);

            $enviado = true;
        } catch (Throwable $excepcion) {
            // Log para vos (se guarda en el error_log del hosting, no
            // se lo mostramos al visitante). Mientras depurás, podés
            // descomentar la línea de abajo para verlo directo en pantalla.
            error_log('contacto.php - error al guardar: ' . $excepcion->getMessage());
            // $errores[] = $excepcion->getMessage(); // <-- descomentar solo para depurar

            $errores[] = 'No pudimos guardar tu mensaje. Probá de nuevo en unos minutos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<?php
$titulo = 'Contacto';
$cssExtra = ['Contacto.css'];
require 'componentes/head.php';
?>

<body>
    <main class="aplicacion">

        <?php
        $tituloEncabezado = 'Contacto';
        require 'componentes/encabezado-volver.php';
        ?>

        <?php if ($enviado): ?>

            <div class="contacto-exito">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <ellipse cx="12" cy="12" rx="9" ry="9" />
                    <polyline points="8,12.5 11,15.5 16,9" />
                </svg>
                <h2>¡Mensaje enviado!</h2>
                <p>
                    Gracias, <?= htmlspecialchars($valores['nombre']) ?>. Te vamos a responder a
                    <strong><?= htmlspecialchars($valores['email']) ?></strong> a la brevedad.
                </p>
                <a href="contacto.php" class="boton-secundario">Enviar otro mensaje</a>
            </div>

        <?php else: ?>

            <div class="contacto-intro">
                <h1 class="contacto-intro__titulo">Escribinos</h1>
                <p class="contacto-intro__texto">
                    Completá el formulario y te respondemos por email. También podés
                    escribirnos directamente a biblioteca@rincondelsaber.edu.ar.
                </p>
            </div>

            <?php if (!empty($errores)): ?>
                <div class="contacto-errores">
                    <p class="contacto-errores__titulo">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <ellipse cx="12" cy="12" rx="9" ry="9" />
                            <line x1="12" y1="8" x2="12" y2="13" />
                            <line x1="12" y1="16.3" x2="12" y2="16.31" />
                        </svg>
                        Revisá estos datos
                    </p>
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="form-contacto" action="contacto.php" method="post" novalidate>
                <label class="modal__campo">
                    Nombre
                    <input type="text" name="nombre" placeholder="Tu nombre y apellido"
                        value="<?= htmlspecialchars($valores['nombre']) ?>" autocomplete="name">
                </label>

                <label class="modal__campo">
                    Email
                    <input type="email" name="email" placeholder="tu.nombre@ejemplo.com"
                        value="<?= htmlspecialchars($valores['email']) ?>" autocomplete="email">
                </label>

                <label class="modal__campo">
                    Teléfono
                    <input type="tel" name="telefono" placeholder="011 1234-5678"
                        value="<?= htmlspecialchars($valores['telefono']) ?>" autocomplete="tel">
                </label>

                <label class="modal__campo">
                    Tipo de consulta
                    <select name="tipo_consulta">
                        <option value="" disabled <?= $valores['tipo_consulta'] === '' ? 'selected' : '' ?>>
                            Elegí una opción...
                        </option>
                        <?php foreach ($TIPOS_CONSULTA as $clave => $etiqueta): ?>
                            <option value="<?= htmlspecialchars($clave) ?>"
                                <?= $valores['tipo_consulta'] === $clave ? 'selected' : '' ?>>
                                <?= htmlspecialchars($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="modal__campo">
                    Mensaje
                    <textarea name="mensaje" rows="5"
                        placeholder="Contanos en qué te podemos ayudar..."><?= htmlspecialchars($valores['mensaje']) ?></textarea>
                    <span class="form-contacto__ayuda">Mínimo 15 caracteres.</span>
                </label>

                <button type="submit" class="boton-reservar">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <polygon points="3,11 22,2 13,21 11,13 3,11" />
                    </svg>
                    Enviar mensaje
                </button>
            </form>

        <?php endif; ?>

    </main>

    <?php
    $navActivo = '';
    $haySesionProfesor = isset($_SESSION['profesor_id']);
    require 'componentes/nav-inferior.php';
    ?>

    <script src="imagenes-fallback.js"></script>
    <script src="js/comun.js"></script>
</body>

</html>

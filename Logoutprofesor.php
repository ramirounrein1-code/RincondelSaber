<?php
/**
 * Logoutprofesor.php
 * Cierra solo la sesión de profesor (no toca estudiante_id, por si
 * en algún momento conviven, aunque hoy la app no las usa a la vez).
 */

session_set_cookie_params(['path' => '/']);
session_start();

unset($_SESSION['profesor_id']);
unset($_SESSION['profesor_nombre']);
unset($_SESSION['profesor_apellido']);
unset($_SESSION['profesor_email']);

header('Location: Loginprofesor.php');
exit;
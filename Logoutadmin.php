<?php
/**
 * Logoutadmin.php
 * Cierra solo la sesión de administrador.
 */

session_set_cookie_params(['path' => '/']);
session_start();

unset($_SESSION['administrador_id']);
unset($_SESSION['administrador_nombre']);
unset($_SESSION['administrador_apellido']);
unset($_SESSION['administrador_email']);

header('Location: Loginadmin.php');
exit;

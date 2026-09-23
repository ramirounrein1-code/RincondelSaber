<?php
/**
 * Logout.php
 * Cierra la sesión y vuelve al inicio.
 */

session_set_cookie_params(['path' => '/']);
session_start();
session_unset();
session_destroy();
header('Location: index.php');
exit;
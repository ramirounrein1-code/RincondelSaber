<?php
/* ============================================
   conexion.php - CONEXIÓN A BASE DE DATOS
   Local XAMPP + Hosting InfinityFree
   ============================================ */

if ($_SERVER["HTTP_HOST"] === "localhost" || $_SERVER["HTTP_HOST"] === "127.0.0.1") {
    $host = "localhost";
    $base_datos = "mi-mejor-amigo";
    $usuario = "root";
    $contrasena = "";
} else {
    $host = "sql202.infinityfree.com";
    $base_datos = "if0_42882884_rincon_del_saber";
    $usuario = "if0_42882884";
    $contrasena = "agustinramiro1";
}
try {
    $conexion = new PDO(
        "mysql:host=$host;dbname=$base_datos;charset=utf8mb4",
        $usuario,
        $contrasena
    );
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $error) {
    die("Error de conexión: " . $error->getMessage());
}
?>

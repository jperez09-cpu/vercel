<?php
require_once 'conexion.php';

// Contraseña cifrada
$nombre_usuario = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT); // Contraseña segura
$rol = 'admin'; // Asignamos el rol de administrador

$stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, password, rol) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $nombre_usuario, $password, $rol);

if ($stmt->execute()) {
    echo "Usuario administrador agregado exitosamente.";
} else {
    echo "Error al agregar usuario.";
}
?>

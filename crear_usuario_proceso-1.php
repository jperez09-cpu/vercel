<?php
session_start();
require_once 'conexion.php';

// Asegurarse de que el usuario sea admin
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $rol = $_POST['rol'] ?? '';

    // Validar campos obligatorios
    if ($nombre_usuario === '' || $password === '' || $rol === '') {
        echo '<script>alert("Todos los campos son obligatorios."); window.location.href = "crear_usuario";</script>';
        exit;
    }

    // Verificar si el nombre de usuario ya está en uso
    $query = "SELECT id FROM usuarios WHERE nombre_usuario = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo '<script>alert("El nombre de usuario ya está en uso."); window.location.href = "crear_usuario";</script>';
        exit;
    }

    // Hashear la contraseña
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Insertar nuevo usuario
    $query = "INSERT INTO usuarios (nombre_usuario, password, rol) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $nombre_usuario, $password_hash, $rol);

    if ($stmt->execute()) {
        echo '<script>alert("✅ Usuario creado exitosamente."); window.location.href = "index";</script>';
    } else {
        echo '<script>alert("❌ Hubo un error al crear el usuario."); window.location.href = "crear_usuario";</script>';
    }
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// SEGURIDAD: Validación estricta de sesión y rol
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['admin', 'concejal'])) {
    header('Location: index');
    exit;
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Obtener y limpiar datos
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $nombre_usuario  = trim($_POST['nombre_usuario'] ?? '');
    $password        = $_POST['password'] ?? '';
    $rol             = $_POST['rol'] ?? 'user';
    $zona            = trim($_POST['zona'] ?? '');
    $telefono        = trim($_POST['telefono'] ?? '');

    // Manejo de valores que pueden ser NULL (IDs numéricos)
    $id_barrio   = (!empty($_POST['id_barrio'])) ? (int)$_POST['id_barrio'] : null;
    $id_concejal = (!empty($_POST['id_concejal'])) ? (int)$_POST['id_concejal'] : null;

    // 2. Validar campos obligatorios
    if (empty($nombre_completo) || empty($nombre_usuario) || empty($password) || is_null($id_barrio)) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Por favor complete todos los campos obligatorios.'];
        header('Location: crear_usuario');
        exit;
    }

    // 3. Verificar si el usuario ya existe
    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE nombre_usuario = ?");
    $stmt_check->bind_param("s", $nombre_usuario);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'El nombre de usuario ya existe.'];
        $stmt_check->close();
        header('Location: crear_usuario');
        exit;
    }
    $stmt_check->close();

    // 4. Hashear contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 5. Insertar usuario
    // Asegúrate de que el orden sea: (s)Nombre, (s)User, (s)Pass, (s)Rol, (i)Barrio, (s)Zona, (s)Tel, (i)Concejal
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre_completo, nombre_usuario, password, rol, id_barrio, zona, telefono, id_concejal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    // El formato "ssssissi" es correcto si id_barrio e id_concejal son INT en tu DB
    $stmt->bind_param("ssssissi", 
        $nombre_completo, 
        $nombre_usuario, 
        $hashed_password, 
        $rol, 
        $id_barrio, 
        $zona, 
        $telefono, 
        $id_concejal
    );

    if ($stmt->execute()) {
        $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => '✅ Usuario creado correctamente.'];
    } else {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => '❌ Error en la base de datos: ' . $stmt->error];
    }

    $stmt->close();
    $conn->close();
    header('Location: crear_usuario');
    exit;
} else {
    header('Location: crear_usuario');
    exit;
}
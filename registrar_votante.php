<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'conexion.php';

// Verificar sesión
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_id'])) {
    header("Location: index");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recibir datos del formulario
    $nombre    = trim($_POST['nombre']);
    $apellido  = trim($_POST['apellido']);
    $cedula    = trim($_POST['cedula']);
    $telefono  = trim($_POST['telefono']);
    $id_barrio = (int) $_POST['id_barrios'];
    $zona      = trim($_POST['zona']);

    /*
     * VALIDACIÓN CLAVE:
     * El mismo usuario NO puede registrar la misma cédula dos veces
     * Pero otros usuarios sí pueden
     */
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM votantes 
        WHERE cedula = ? AND id_usuario = ?
    ");
    $stmt->bind_param("si", $cedula, $id_usuario);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        header("Location: registrar.php?error=cedula_existente");
        exit;
    }

    // Obtener id_concejal del usuario
    $stmt_usuario = $conn->prepare("
        SELECT id_concejal 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt_usuario->bind_param("i", $id_usuario);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();

    if ($result_usuario->num_rows === 0) {
        echo "<script>
            alert('Error: El usuario no tiene concejal asignado.');
            window.location.href='registrar.php';
        </script>";
        exit;
    }

    $row_usuario = $result_usuario->fetch_assoc();
    $id_concejal = $row_usuario['id_concejal'];
    $stmt_usuario->close();

    // Insertar nuevo votante
    $stmt_insert = $conn->prepare("
        INSERT INTO votantes
        (nombre, apellido, cedula, telefono, id_barrios, zona, id_usuario, id_concejal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt_insert->bind_param(
        "ssssisii",
        $nombre,
        $apellido,
        $cedula,
        $telefono,
        $id_barrio,
        $zona,
        $id_usuario,
        $id_concejal
    );

    if ($stmt_insert->execute()) {
        header("Location: registrar.php?success=1");
    } else {
        // Error por índice único (si existe)
        if ($conn->errno === 1062) {
            header("Location: registrar.php?error=cedula_existente");
        } else {
            header("Location: registrar.php?error=1");
        }
    }

    $stmt_insert->close();
    $conn->close();
    exit;
}
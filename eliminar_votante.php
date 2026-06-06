<?php
session_start();
require_once 'conexion.php';

// Verifica que el usuario esté logueado y tenga permisos
if (!isset($_SESSION['usuario']) ||
    !in_array($_SESSION['rol'], ['admin', 'concejal'])
) {
    header('Location: index.php');
    exit;
}


// Verifica que se recibió el id
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];

    // Preparar y ejecutar consulta para eliminar el votante
    $stmt = $conn->prepare("DELETE FROM votantes WHERE id = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        // Redirigir con mensaje de éxito
        header('Location: consultar_votantes.php?msg=eliminado');
        exit;
    } else {
        // Redirigir con mensaje de error
        header('Location: consultar_votantes.php?msg=error');
        exit;
    }
} else {
    // Si no hay id, vuelve a la lista
    header('Location: consultar_votantes.php');
    exit;
}
?>

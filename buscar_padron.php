<?php
header('Content-Type: application/json');
include 'conexion.php';

// Mostrar errores para depurar (quitar en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$cedula = $_GET['cedula'] ?? '';

if (!empty($cedula) && is_numeric($cedula)) {
    // Asegúrate de que los nombres de las columnas coincidan con tu tabla padron_jas
    // Por ejemplo: 'cedula', 'nro_cedula', 'nombres', 'apellidos', etc.
    $stmt = $conn->prepare("SELECT nombre, apellido FROM padron_jas WHERE cedula = ? LIMIT 1");
    $stmt->bind_param("s", $cedula);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'success', 
            'nombre' => $row['nombre'], 
            'apellido' => $row['apellido']
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
} else {
    echo json_encode(['status' => 'invalid']);
}
$conn->close();
?>
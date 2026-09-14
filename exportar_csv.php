<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require 'conexion.php';
require_once 'sesion.php';
iniciarSesionSegura();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'user';

$buscar = $_GET['buscar'] ?? '';
$f_usuario = $_GET['f_usuario'] ?? '';
$f_concejal = $_GET['f_concejal'] ?? '';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_votantes.csv"');
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
$delimiter = ';';

fputcsv(
    $output,
    ['Nombre', 'Apellido', 'Cedula', 'Telefono', 'Barrio', 'Zona', 'Local', 'Mesa', 'Dirigente', 'Concejal', 'Fecha Registro'],
    $delimiter
);

$where = [];
$params = [];
$tipos = '';

$where[] = "(v.nombre LIKE ? OR v.cedula LIKE ?)";
$params[] = "%$buscar%";
$params[] = "%$buscar%";
$tipos .= 'ss';

if ($rol === 'user') {
    $where[] = "v.id_usuario = ?";
    $params[] = $usuario_id;
    $tipos .= 'i';
} elseif ($rol === 'concejal') {
    $stmtCon = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ?");
    $stmtCon->bind_param("s", $_SESSION['usuario']);
    $stmtCon->execute();
    $resCon = $stmtCon->get_result()->fetch_assoc();
    $id_concejal = $resCon['id'] ?? 0;

    $where[] = "v.id_concejal = ?";
    $params[] = $id_concejal;
    $tipos .= 'i';

    if ($f_usuario !== '' && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $params[] = (int) $f_usuario;
        $tipos .= 'i';
    }
} elseif ($rol === 'admin') {
    if ($f_concejal !== '' && is_numeric($f_concejal)) {
        $where[] = "v.id_concejal = ?";
        $params[] = (int) $f_concejal;
        $tipos .= 'i';
    }

    if ($f_usuario !== '' && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $params[] = (int) $f_usuario;
        $tipos .= 'i';
    }
}

$sql = "
SELECT
    v.nombre,
    v.apellido,
    v.cedula,
    v.telefono,
    b.descripcion AS barrio,
    v.zona,
    pm.local,
    pm.mesa,
    u.nombre_completo AS usuario,
    c.nombre AS concejal,
    v.fecha_registro
FROM votantes v
LEFT JOIN barrios b ON b.id_barrios = v.id_barrios
LEFT JOIN padron_mesa pm ON pm.cedula = v.cedula
LEFT JOIN usuarios u ON u.id = v.id_usuario
LEFT JOIN concejales c ON c.id = v.id_concejal
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY v.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total = 0;
while ($row = $result->fetch_assoc()) {
    fputcsv(
        $output,
        [
            $row['nombre'],
            $row['apellido'],
            $row['cedula'],
            $row['telefono'],
            $row['barrio'],
            $row['zona'],
            $row['local'],
            $row['mesa'],
            $row['usuario'],
            $row['concejal'],
            $row['fecha_registro'],
        ],
        $delimiter
    );
    $total++;
}

fputcsv($output, [], $delimiter);
fputcsv($output, ['TOTAL DE VOTANTES', $total], $delimiter);

fclose($output);
exit;
?>

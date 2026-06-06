<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$rol = $_SESSION['rol'] ?? 'user';
$usuario_id = $_SESSION['usuario_id'];
$cod_usuario = $_SESSION['usuario'];

// Filtros
$buscar = $_GET['buscar'] ?? '';
$f_concejal = $_GET['f_concejal'] ?? '';

$where = [];
$params = [];
$tipos = "";

/* ===============================
   FILTRO DE BÚSQUEDA
================================ */
if ($buscar !== '') {
    $where[] = "(u.nombre_completo LIKE ? OR u.nombre_usuario LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $tipos .= "ss";
}

/* ===============================
   FILTRO SEGÚN ROL
================================ */
if ($rol === 'user') {

    $where[] = "u.id = ?";
    $params[] = $usuario_id;
    $tipos .= "i";

} elseif ($rol === 'concejal') {

    $stmt = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1");
    $stmt->bind_param("s", $cod_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $concejal_id = $res->fetch_assoc()['id'] ?? 0;

    $where[] = "u.id_concejal = ?";
    $params[] = $concejal_id;
    $tipos .= "i";

} elseif ($rol === 'admin' && $f_concejal !== '') {

    $where[] = "u.id_concejal = ?";
    $params[] = $f_concejal;
    $tipos .= "i";
}

$whereSQL = count($where) ? "WHERE " . implode(" AND ", $where) : "";

/* ===============================
   CONSULTA
================================ */
$sql = "SELECT 
            u.nombre_usuario,
            u.nombre_completo,
            u.telefono,
            b.descripcion AS barrio,
            u.zona,
            c.nombre AS concejal
        FROM usuarios u
        LEFT JOIN barrios b ON u.id_barrio = b.id_barrios
        LEFT JOIN concejales c ON u.id_concejal = c.id
        $whereSQL
        ORDER BY u.nombre_completo ASC";

$stmt = $conn->prepare($sql);

if ($tipos) {
    $stmt->bind_param($tipos, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* ===============================
   DESCARGA CSV
================================ */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=dirigentes.csv');

$output = fopen('php://output', 'w');
/* BOM UTF-8 para que Excel reconozca acentos */
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ['PLANILLA DE DIRIGENTES', '', '', '', '', ''], ';');

/* Línea vacía opcional */
fputcsv($output, ['', '', '', '', '', ''], ';');

fputcsv($output, ['Nombre', 'Cedula', 'Telefono', 'Barrio', 'Zona', 'Concejal'],';');

while ($row = $result->fetch_assoc()) {

    fputcsv($output, [
        $row['nombre_completo'],
        $row['nombre_usuario'],
        $row['telefono'],
        $row['barrio'],
        $row['zona'],
        $row['concejal']
    ], ';');
}

fclose($output);
exit;
?>

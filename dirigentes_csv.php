<?php
ob_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once 'sesion.php';
iniciarSesionSegura();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'] ?? '', ['admin', 'concejal'], true)) {
    header('Location: login');
    exit;
}

$rol = $_SESSION['rol'];
$cod_usuario = $_SESSION['usuario'];
$buscar = trim($_GET['buscar'] ?? '');
$f_concejal = $_GET['f_concejal'] ?? '';
$where = [];
$params = [];
$tipos = '';

if ($buscar !== '') {
    $where[] = '(u.nombre_completo LIKE ? OR u.nombre_usuario LIKE ?)';
    $termino = "%{$buscar}%";
    $params[] = $termino;
    $params[] = $termino;
    $tipos .= 'ss';
}

if ($rol === 'concejal') {
    $stmtConcejal = $conn->prepare('SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1');
    $stmtConcejal->bind_param('s', $cod_usuario);
    $stmtConcejal->execute();
    $concejal = $stmtConcejal->get_result()->fetch_assoc();
    $stmtConcejal->close();
    $where[] = 'u.id_concejal = ?';
    $params[] = (int) ($concejal['id'] ?? 0);
    $tipos .= 'i';
} elseif ($f_concejal !== '' && ctype_digit((string) $f_concejal)) {
    $where[] = 'u.id_concejal = ?';
    $params[] = (int) $f_concejal;
    $tipos .= 'i';
}

$whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT u.nombre_usuario, u.nombre_completo, u.telefono,
               b.descripcion AS barrio, u.zona, c.nombre AS concejal
        FROM usuarios u
        LEFT JOIN barrios b ON u.id_barrio = b.id_barrios
        LEFT JOIN concejales c ON u.id_concejal = c.id
        {$whereSQL}
        ORDER BY u.nombre_completo ASC";

$stmt = $conn->prepare($sql);
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

function escaparExcel($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="dirigentes.xls"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: public');

echo "\xEF\xBB\xBF";
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #000; padding: 6px; vertical-align: top; }
th { background: #d9eaf7; font-weight: bold; text-align: center; }
.titulo { border: 0; font-size: 16pt; font-weight: bold; text-align: center; padding: 10px; }
.texto { mso-number-format: "\@"; white-space: nowrap; }
</style>
</head>
<body>
<table>
<colgroup>
    <col style="width: 30%;">
    <col style="width: 14%;">
    <col style="width: 16%;">
    <col style="width: 16%;">
    <col style="width: 10%;">
    <col style="width: 14%;">
</colgroup>
<tr><td class="titulo" colspan="6">PLANILLA DE DIRIGENTES</td></tr>
<tr>
    <th>Nombre</th><th>Cedula</th><th>Telefono</th>
    <th>Barrio</th><th>Zona</th><th>Concejal</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= escaparExcel($row['nombre_completo']) ?></td>
    <td class="texto"><?= escaparExcel($row['nombre_usuario']) ?></td>
    <td class="texto"><?= escaparExcel($row['telefono']) ?></td>
    <td><?= escaparExcel($row['barrio']) ?></td>
    <td><?= escaparExcel($row['zona']) ?></td>
    <td><?= escaparExcel($row['concejal']) ?></td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
<?php
$stmt->close();
exit;

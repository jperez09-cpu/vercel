<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'PhpSpreadsheet/vendor/autoload.php';
require 'conexion.php';
require_once 'sesion.php';
iniciarSesionSegura();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'user';

$buscar = $_GET['buscar'] ?? '';
$f_usuario = $_GET['f_usuario'] ?? '';
$f_concejal = $_GET['f_concejal'] ?? '';

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
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Votantes');

$headers = ['Nombre', 'Apellido', 'Cedula', 'Telefono', 'Barrio', 'Zona', 'Local', 'Mesa', 'Usuario', 'Concejal', 'Fecha Registro'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD'],
    ],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $row['nombre']);
    $sheet->setCellValue("B$rowNum", $row['apellido']);
    $sheet->setCellValue("C$rowNum", $row['cedula']);
    $sheet->setCellValue("D$rowNum", $row['telefono']);
    $sheet->setCellValue("E$rowNum", $row['barrio']);
    $sheet->setCellValue("F$rowNum", $row['zona']);
    $sheet->setCellValue("G$rowNum", $row['local']);
    $sheet->setCellValue("H$rowNum", $row['mesa']);
    $sheet->setCellValue("I$rowNum", $row['usuario']);
    $sheet->setCellValue("J$rowNum", $row['concejal']);
    $sheet->setCellValue("K$rowNum", $row['fecha_registro']);
    $rowNum++;
}

$sheet->setCellValue("A$rowNum", "TOTAL DE VOTANTES");
$sheet->mergeCells("A$rowNum:J$rowNum");
$sheet->setCellValue("K$rowNum", $rowNum - 2);

foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="reporte_votantes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>

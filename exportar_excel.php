<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

$filas = [];
while ($row = $result->fetch_assoc()) {
    $filas[] = $row;
}

$autoload = __DIR__ . '/PhpSpreadsheet/vendor/autoload.php';
if (!is_file($autoload)) {
    exportarExcelHtml($filas);
}

require $autoload;

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Votantes');

$headers = ['Nro', 'Nombre', 'Apellido', 'Cedula', 'Telefono', 'Barrio', 'Zona', 'Local', 'Mesa', 'Usuario', 'Concejal', 'Fecha Registro'];
$sheet->fromArray($headers, null, 'A1');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
];
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(28);

$rowNum = 2;
$nro = 1;
foreach ($filas as $row) {
    $sheet->setCellValue("A$rowNum", $nro++);
    $sheet->setCellValue("B$rowNum", $row['nombre']);
    $sheet->setCellValue("C$rowNum", $row['apellido']);
    $sheet->setCellValueExplicit("D$rowNum", (string) $row['cedula'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("E$rowNum", (string) $row['telefono'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue("F$rowNum", $row['barrio']);
    $sheet->setCellValue("G$rowNum", $row['zona']);
    $sheet->setCellValue("H$rowNum", $row['local']);
    $sheet->setCellValue("I$rowNum", $row['mesa']);
    $sheet->setCellValue("J$rowNum", $row['usuario']);
    $sheet->setCellValue("K$rowNum", $row['concejal']);
    $sheet->setCellValue("L$rowNum", $row['fecha_registro']);
    $rowNum++;
}

$sheet->setCellValue("A$rowNum", "TOTAL DE VOTANTES");
$sheet->mergeCells("A$rowNum:K$rowNum");
$sheet->setCellValue("L$rowNum", $rowNum - 2);
$sheet->getStyle("A$rowNum:L$rowNum")->getFont()->setBold(true);
$sheet->getStyle("A$rowNum:L$rowNum")->getFill()
    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
    ->getStartColor()->setRGB('D9EAF7');

$lastDataRow = max(1, $rowNum);
$sheet->getStyle("A1:L$lastDataRow")->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => 'B7B7B7'],
        ],
    ],
    'alignment' => [
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
        'wrapText' => true,
    ],
]);

$sheet->getStyle("A2:A$lastDataRow")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("D2:E$lastDataRow")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("I2:I$lastDataRow")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("L2:L$lastDataRow")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

$columnWidths = [
    'A' => 6,
    'B' => 18,
    'C' => 18,
    'D' => 12,
    'E' => 13,
    'F' => 22,
    'G' => 18,
    'H' => 24,
    'I' => 9,
    'J' => 24,
    'K' => 20,
    'L' => 17,
];
foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

$sheet->freezePane('A2');
$sheet->setAutoFilter("A1:L1");
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
    ->setFitToPage(true)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageMargins()
    ->setTop(0.35)
    ->setRight(0.25)
    ->setLeft(0.25)
    ->setBottom(0.35);
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
$sheet->getHeaderFooter()->setOddFooter('&LReporte de votantes&RPagina &P de &N');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="reporte_votantes.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function exportarExcelHtml(array $filas): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_votantes.xls"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<style>
        @page { size: A4 landscape; margin: 0.35in 0.25in; }
        body { font-family: Arial, sans-serif; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th { background: #4F81BD; color: #fff; font-weight: bold; text-align: center; vertical-align: middle; }
        td, th { border: 1px solid #b7b7b7; padding: 4px; vertical-align: top; white-space: normal; }
        .center { text-align: center; }
        .text { mso-number-format:"\@"; }
        .total { background: #D9EAF7; font-weight: bold; }
        .nro { width: 35px; }
        .nombre, .apellido { width: 100px; }
        .cedula { width: 70px; }
        .telefono { width: 80px; }
        .barrio { width: 130px; }
        .zona { width: 100px; }
        .local { width: 150px; }
        .mesa { width: 50px; }
        .usuario { width: 150px; }
        .concejal { width: 120px; }
        .fecha { width: 95px; }
    </style>';
    echo '</head><body>';
    echo '<table>';
    echo '<thead><tr>';
    $headers = ['Nro', 'Nombre', 'Apellido', 'Cedula', 'Telefono', 'Barrio', 'Zona', 'Local', 'Mesa', 'Usuario', 'Concejal', 'Fecha Registro'];
    $classes = ['nro', 'nombre', 'apellido', 'cedula', 'telefono', 'barrio', 'zona', 'local', 'mesa', 'usuario', 'concejal', 'fecha'];
    foreach ($headers as $i => $header) {
        echo '<th class="' . $classes[$i] . '">' . h($header) . '</th>';
    }
    echo '</tr></thead><tbody>';

    $nro = 1;
    foreach ($filas as $row) {
        echo '<tr>';
        echo '<td class="center">' . $nro++ . '</td>';
        echo '<td>' . h($row['nombre']) . '</td>';
        echo '<td>' . h($row['apellido']) . '</td>';
        echo '<td class="center text">' . h($row['cedula']) . '</td>';
        echo '<td class="center text">' . h($row['telefono']) . '</td>';
        echo '<td>' . h($row['barrio']) . '</td>';
        echo '<td>' . h($row['zona']) . '</td>';
        echo '<td>' . h($row['local']) . '</td>';
        echo '<td class="center">' . h($row['mesa']) . '</td>';
        echo '<td>' . h($row['usuario']) . '</td>';
        echo '<td>' . h($row['concejal']) . '</td>';
        echo '<td class="center">' . h($row['fecha_registro']) . '</td>';
        echo '</tr>';
    }

    echo '<tr class="total"><td colspan="11">TOTAL DE VOTANTES</td><td class="center">' . count($filas) . '</td></tr>';
    echo '</tbody></table></body></html>';
    exit;
}

function h($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

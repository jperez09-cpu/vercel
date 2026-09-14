<?php

ob_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('zlib.output_compression', '0');
ini_set('output_buffering', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

require __DIR__ . '/fpdf/fpdf.php';
require __DIR__ . '/conexion.php';
require_once 'sesion.php';
iniciarSesionSegura();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'user';
$cod_usuario = $_SESSION['usuario'] ?? '';

$buscar = $_GET['buscar'] ?? '';
$f_usuario = $_GET['f_usuario'] ?? '';
$f_concejal = $_GET['f_concejal'] ?? '';

$concejal_id_logueado = null;
if ($rol === 'concejal') {
    $stmt = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1");
    $stmt->bind_param("s", $cod_usuario);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $concejal_id_logueado = (int) $row['id'];
    }
}

function textoPlanillaPdf($valor, int $limite = 0): string
{
    $texto = trim((string) ($valor ?? ''));
    if ($limite > 0 && $texto !== '') {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($texto, 'UTF-8') > $limite) {
                $texto = rtrim(mb_substr($texto, 0, $limite - 1, 'UTF-8')) . '.';
            }
        } elseif (strlen($texto) > $limite) {
            $texto = rtrim(substr($texto, 0, $limite - 1)) . '.';
        }
    }

    return function_exists('iconv')
        ? (iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto) ?: '')
        : utf8_decode($texto);
}

function limpiarSalidaPdf(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

$where = [];
$tipos = '';
$params = [];

$where[] = "(v.nombre LIKE ? OR v.apellido LIKE ? OR v.cedula LIKE ?)";
$tipos .= 'sss';
$params[] = "%$buscar%";
$params[] = "%$buscar%";
$params[] = "%$buscar%";

if ($rol === 'user') {
    $where[] = "v.id_usuario = ?";
    $tipos .= 'i';
    $params[] = $usuario_id;
} elseif ($rol === 'concejal') {
    if (!$concejal_id_logueado) {
        http_response_code(403);
        exit('Concejal no encontrado');
    }

    $where[] = "v.id_concejal = ?";
    $tipos .= 'i';
    $params[] = $concejal_id_logueado;

    if ($f_usuario !== '' && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $tipos .= 'i';
        $params[] = (int) $f_usuario;
    }
} elseif ($rol === 'admin') {
    if ($f_concejal !== '' && is_numeric($f_concejal)) {
        $where[] = "v.id_concejal = ?";
        $tipos .= 'i';
        $params[] = (int) $f_concejal;
    }

    if ($f_usuario !== '' && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $tipos .= 'i';
        $params[] = (int) $f_usuario;
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        v.id,
        v.nombre,
        v.apellido,
        v.cedula,
        v.telefono,
        v.zona,
        b.descripcion AS barrio,
        u.id AS usuario_id,
        u.nombre_completo AS dirigente,
        u.nombre_usuario AS dirigente_cedula,
        u.telefono AS dirigente_telefono,
        u.zona AS dirigente_zona
    FROM votantes v
    LEFT JOIN barrios b ON b.id_barrios = v.id_barrios
    LEFT JOIN usuarios u ON u.id = v.id_usuario
    $whereSql
    ORDER BY u.nombre_completo ASC, v.id ASC
";

$stmt = $conn->prepare($sql);
if ($tipos !== '') {
    $stmt->bind_param($tipos, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$grupos = [];
while ($row = $result->fetch_assoc()) {
    $usuarioKey = $row['usuario_id'] !== null ? (string) $row['usuario_id'] : 'sin_usuario';

    if (!isset($grupos[$usuarioKey])) {
        $grupos[$usuarioKey] = [
            'dirigente' => $row['dirigente'] ?: 'Sin dirigente',
            'cedula' => $row['dirigente_cedula'] ?: '',
            'telefono' => $row['dirigente_telefono'] ?: '',
            'zona' => $row['dirigente_zona'] ?: '',
            'votantes' => [],
        ];
    }

    $grupos[$usuarioKey]['votantes'][] = [
        'nombre_completo' => trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')),
        'cedula' => $row['cedula'] ?? '',
        'telefono' => $row['telefono'] ?? '',
        'barrio' => $row['barrio'] ?? '',
        'zona' => $row['zona'] ?? '',
    ];
}

if (empty($grupos)) {
    http_response_code(404);
    exit('No se encontraron votantes para exportar.');
}

class ReporteVotantesPDF extends FPDF
{
    public array $encabezadoActual = [];

    function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 9, 'PLANILLA DE VOTANTES', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $this->Ln(3);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'PUNTERO/DIRIGENTE', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(72, 8, textoPlanillaPdf($this->encabezadoActual['dirigente'] ?? '', 38), 1, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(28, 8, 'N' . chr(186) . ' DE CEDULA', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(48, 8, textoPlanillaPdf($this->encabezadoActual['cedula'] ?? '', 20), 1, 1, 'L');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'TELEFONO', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(148, 8, textoPlanillaPdf($this->encabezadoActual['telefono'] ?? '', 45), 1, 1, 'L');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'ZONA', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(148, 8, textoPlanillaPdf($this->encabezadoActual['zona'] ?? '', 45), 1, 1, 'L');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(235, 241, 252);
        $this->Cell(12, 8, 'N' . chr(186), 1, 0, 'C', true);
        $this->Cell(72, 8, 'NOMBRES Y APELLIDOS', 1, 0, 'C', true);
        $this->Cell(28, 8, 'N' . chr(186) . ' DE CEDULA', 1, 0, 'C', true);
        $this->Cell(30, 8, 'N' . chr(186) . ' DE TELEFONO', 1, 0, 'C', true);
        $this->Cell(28, 8, 'BARRIO', 1, 0, 'C', true);
        $this->Cell(20, 8, 'ZONA', 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, textoPlanillaPdf('Pagina ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
}

$pdf = new ReporteVotantesPDF('P', 'mm', 'A4');
$pdf->SetCompression(true);
$pdf->AliasNbPages();
$pdf->SetMargins(10, 8, 10);
$pdf->SetAutoPageBreak(true, 12);

foreach ($grupos as $grupo) {
    $bloques = array_chunk($grupo['votantes'], 20);
    $numeroFila = 1;

    foreach ($bloques as $bloque) {
        $pdf->encabezadoActual = [
            'dirigente' => $grupo['dirigente'],
            'cedula' => $grupo['cedula'],
            'telefono' => $grupo['telefono'],
            'zona' => $grupo['zona'],
        ];
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 9);

        for ($i = 0; $i < 20; $i++) {
            $fila = $bloque[$i] ?? null;
            $numero = $fila ? (string) $numeroFila : '';

            $pdf->Cell(12, 8, $numero, 1, 0, 'C');
            $pdf->Cell(72, 8, textoPlanillaPdf($fila['nombre_completo'] ?? '', 38), 1, 0, 'L');
            $pdf->Cell(28, 8, textoPlanillaPdf($fila['cedula'] ?? '', 15), 1, 0, 'C');
            $pdf->Cell(30, 8, textoPlanillaPdf($fila['telefono'] ?? '', 16), 1, 0, 'C');
            $pdf->Cell(28, 8, textoPlanillaPdf($fila['barrio'] ?? '', 16), 1, 0, 'L');
            $pdf->Cell(20, 8, textoPlanillaPdf($fila['zona'] ?? '', 10), 1, 1, 'C');

            if ($fila) {
                $numeroFila++;
            }
        }
    }
}

limpiarSalidaPdf();

$archivoTemporal = tempnam(sys_get_temp_dir(), 'reporte_pdf_');
if ($archivoTemporal === false) {
    http_response_code(500);
    exit('No se pudo generar el archivo PDF.');
}

$pdf->Output('F', $archivoTemporal);
$tamanoArchivo = @filesize($archivoTemporal);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reporte_votantes.pdf"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
if ($tamanoArchivo !== false) {
    header('Content-Length: ' . $tamanoArchivo);
}

readfile($archivoTemporal);
@unlink($archivoTemporal);
exit;

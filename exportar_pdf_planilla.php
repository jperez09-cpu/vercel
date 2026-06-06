<?php
ob_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('zlib.output_compression', '0');
ini_set('output_buffering', '0');
set_time_limit(0);

require 'fpdf/fpdf.php';
require 'conexion.php';
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
    $stmtCon = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1");
    $stmtCon->bind_param("s", $cod_usuario);
    $stmtCon->execute();
    $resCon = $stmtCon->get_result();
    if ($row = $resCon->fetch_assoc()) {
        $concejal_id_logueado = (int) $row['id'];
    }
}

$puedeExportarPlanilla = $rol === 'admin' || ($rol === 'concejal' && $concejal_id_logueado === 4);
if (!$puedeExportarPlanilla) {
    http_response_code(403);
    echo 'Acceso restringido.';
    exit;
}

function textoPlanilla(string $texto, int $limite): string
{
    $texto = trim($texto);
    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') <= $limite) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $limite - 1, 'UTF-8')) . '.';
    }

    if (strlen($texto) <= $limite) {
        return $texto;
    }

    return rtrim(substr($texto, 0, $limite - 1)) . '.';
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
        die('Concejal no encontrado');
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
        'barrio' => textoPlanilla((string) ($row['barrio'] ?? ''), 16),
        'zona' => textoPlanilla((string) ($row['zona'] ?? ''), 10),
    ];
}

if (empty($grupos)) {
    die('No se encontraron votantes para exportar.');
}

class PlanillaPDF extends FPDF
{
    public array $encabezadoActual = [];
    public ?string $cabeceraTemporal = null;
    public ?string $cabeceraPreparada = null;
    public float $cabeceraAlto = 22.0;

    function prepararCabecera(string $ruta): ?string
    {
        if (!file_exists($ruta)) {
            return null;
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if ($extension !== 'png') {
            return $ruta;
        }

        if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
            return $ruta;
        }

        $imagen = @imagecreatefrompng($ruta);
        if (!$imagen) {
            return $ruta;
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $fondo = imagecreatetruecolor($ancho, $alto);
        $blanco = imagecolorallocate($fondo, 255, 255, 255);
        imagefill($fondo, 0, 0, $blanco);
        imagecopy($fondo, $imagen, 0, 0, 0, 0, $ancho, $alto);

        $temporal = tempnam(sys_get_temp_dir(), 'cab_');
        if ($temporal === false) {
            imagedestroy($imagen);
            imagedestroy($fondo);
            return $ruta;
        }

        $rutaJpg = $temporal . '.jpg';
        @unlink($temporal);
        imagejpeg($fondo, $rutaJpg, 92);

        imagedestroy($imagen);
        imagedestroy($fondo);

        if (file_exists($rutaJpg)) {
            $this->cabeceraTemporal = $rutaJpg;
            return $rutaJpg;
        }

        return $ruta;
    }

    function dibujarCabecera(): void
    {
        $x = 10;
        $y = 8;
        $ancho = 190;

        if ($this->cabeceraPreparada !== null && file_exists($this->cabeceraPreparada)) {
            $this->Image($this->cabeceraPreparada, $x, $y, $ancho);
            $this->SetY($y + $this->cabeceraAlto + 10);
            return;
        }

        $rutas = [
            __DIR__ . '/assets/cabecera.jpg',
            __DIR__ . '/assets/cabecera.png',
        ];

        foreach ($rutas as $ruta) {
            if (!file_exists($ruta)) {
                continue;
            }

            try {
                $rutaPreparada = $this->prepararCabecera($ruta) ?? $ruta;
                $dimensiones = @getimagesize($rutaPreparada);
                $alto = 22;

                if (is_array($dimensiones) && !empty($dimensiones[0]) && !empty($dimensiones[1])) {
                    $alto = ($ancho * $dimensiones[1]) / $dimensiones[0];
                }

                $this->cabeceraPreparada = $rutaPreparada;
                $this->cabeceraAlto = $alto;
                $this->Image($rutaPreparada, $x, $y, $ancho);
                $this->SetY($y + $alto + 10);
                return;
            } catch (Throwable $e) {
                continue;
            }
        }

        $this->SetY($y + 16);
    }

    function Header()
    {
        $this->dibujarCabecera();

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'PUNTERO/DIRIGENTE', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(72, 8, utf8_decode($this->encabezadoActual['dirigente'] ?? ''), 1, 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(28, 8, 'N' . chr(186) . ' DE CEDULA', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(48, 8, utf8_decode($this->encabezadoActual['cedula'] ?? ''), 1, 1, 'L');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'TELEFONO', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(148, 8, utf8_decode($this->encabezadoActual['telefono'] ?? ''), 1, 1, 'L');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(42, 8, 'ZONA', 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell(148, 8, utf8_decode($this->encabezadoActual['zona'] ?? ''), 1, 1, 'L');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'PLANILLA DE VOTANTES', 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(12, 8, 'N' . chr(186), 1, 0, 'C');
        $this->Cell(72, 8, 'NOMBRES Y APELLIDOS', 1, 0, 'C');
        $this->Cell(28, 8, 'N' . chr(186) . ' DE CEDULA', 1, 0, 'C');
        $this->Cell(30, 8, 'N' . chr(186) . ' DE TELEFONO', 1, 0, 'C');
        $this->Cell(28, 8, 'BARRIO', 1, 0, 'C');
        $this->Cell(20, 8, 'ZONA', 1, 1, 'C');
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 8, utf8_decode('Pagina ' . $this->PageNo()), 0, 0, 'C');
    }
}

$pdf = new PlanillaPDF('P', 'mm', 'A4');
$pdf->SetCompression(true);
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
            $pdf->Cell(72, 8, utf8_decode($fila['nombre_completo'] ?? ''), 1, 0, 'L');
            $pdf->Cell(28, 8, (string) ($fila['cedula'] ?? ''), 1, 0, 'C');
            $pdf->Cell(30, 8, (string) ($fila['telefono'] ?? ''), 1, 0, 'C');
            $pdf->Cell(28, 8, utf8_decode($fila['barrio'] ?? ''), 1, 0, 'L');
            $pdf->Cell(20, 8, utf8_decode($fila['zona'] ?? ''), 1, 1, 'C');

            if ($fila) {
                $numeroFila++;
            }
        }
    }
}

limpiarSalidaPdf();

$archivoTemporal = tempnam(sys_get_temp_dir(), 'planilla_pdf_');
if ($archivoTemporal === false) {
    http_response_code(500);
    exit('No se pudo generar el archivo PDF.');
}

$pdf->Output('F', $archivoTemporal);
$tamanoArchivo = @filesize($archivoTemporal);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="planilla_votantes.pdf"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
if ($tamanoArchivo !== false) {
    header('Content-Length: ' . $tamanoArchivo);
}

readfile($archivoTemporal);
@unlink($archivoTemporal);
if ($pdf->cabeceraTemporal && file_exists($pdf->cabeceraTemporal)) {
    @unlink($pdf->cabeceraTemporal);
}
exit;

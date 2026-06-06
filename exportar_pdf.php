<?php

ob_start();

require 'fpdf/fpdf.php';
require 'conexion.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
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
        $concejal_id_logueado = $row['id'];
    }
}

$where = [];
$tipos = '';
$params = [];

$where[] = "(v.nombre LIKE ? OR v.cedula LIKE ?)";
$tipos .= 'ss';
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

    if (!empty($f_usuario) && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $tipos .= 'i';
        $params[] = (int) $f_usuario;
    }
} elseif ($rol === 'admin') {
    if (!empty($f_concejal) && is_numeric($f_concejal)) {
        $where[] = "v.id_concejal = ?";
        $tipos .= 'i';
        $params[] = (int) $f_concejal;
    }

    if (!empty($f_usuario) && is_numeric($f_usuario)) {
        $where[] = "v.id_usuario = ?";
        $tipos .= 'i';
        $params[] = (int) $f_usuario;
    }
}

$where_sql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT
        v.nombre,
        v.apellido,
        v.cedula,
        v.telefono,
        v.zona,
        b.descripcion AS barrio,
        pm.local,
        pm.mesa
    FROM votantes v
    LEFT JOIN barrios b ON v.id_barrios = b.id_barrios
    LEFT JOIN padron_mesa pm ON pm.cedula = v.cedula
    $where_sql
    ORDER BY v.nombre
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();

$result = $stmt->get_result();
$votantes = $result->fetch_all(MYSQLI_ASSOC);

class PDF extends FPDF
{
    function Header()
    {
        $this->Image('assets/logo_PLRA.png', 10, 8, 18);

        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 8, utf8_decode('PLRA - Sistema de Gestion de Votantes'), 0, 1, 'C');

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, 'REPORTE DE VOTANTES', 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'C');

        $this->Ln(4);
        $this->SetFont('Arial', 'B', 9);

        $this->Cell(35, 7, 'Nombre', 1);
        $this->Cell(35, 7, 'Apellido', 1);
        $this->Cell(24, 7, 'Cedula', 1);
        $this->Cell(28, 7, 'Telefono', 1);
        $this->Cell(45, 7, 'Barrio', 1);
        $this->Cell(15, 7, 'Zona', 1);
        $this->Cell(55, 7, 'Local', 1);
        $this->Cell(15, 7, 'Mesa', 1);
        $this->Ln();
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Pagina ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

foreach ($votantes as $row) {
    $pdf->Cell(35, 6, utf8_decode($row['nombre']), 1);
    $pdf->Cell(35, 6, utf8_decode($row['apellido']), 1);
    $pdf->Cell(24, 6, $row['cedula'], 1);
    $pdf->Cell(28, 6, $row['telefono'], 1);
    $pdf->Cell(45, 6, utf8_decode($row['barrio']), 1);
    $pdf->Cell(15, 6, $row['zona'], 1);
    $pdf->Cell(55, 6, utf8_decode((string) ($row['local'] ?? '')), 1);
    $pdf->Cell(15, 6, (string) ($row['mesa'] ?? ''), 1);
    $pdf->Ln();
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Total de votantes: ' . count($votantes), 0, 1, 'R');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="reporte_votantes.pdf"');

$pdf->Output('D', 'reporte_votantes.pdf');
exit;
?>

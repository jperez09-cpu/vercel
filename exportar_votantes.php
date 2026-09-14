<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sesion.php';
iniciarSesionSegura();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'user';
$cod_usuario = $_SESSION['usuario'] ?? '';

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

$usuarios = [];
$concejales = [];

if ($rol === 'admin') {
    $resU = $conn->query("SELECT id, nombre_completo AS nombre FROM usuarios ORDER BY nombre_completo");
    while ($resU && $r = $resU->fetch_assoc()) {
        $usuarios[] = $r;
    }

    $resC = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
    while ($resC && $r = $resC->fetch_assoc()) {
        $concejales[] = $r;
    }
} elseif ($rol === 'concejal' && $concejal_id_logueado) {
    $stmt = $conn->prepare("SELECT id, nombre_completo AS nombre FROM usuarios WHERE id_concejal = ? ORDER BY nombre_completo");
    $stmt->bind_param("i", $concejal_id_logueado);
    $stmt->execute();
    $resU = $stmt->get_result();
    while ($r = $resU->fetch_assoc()) {
        $usuarios[] = $r;
    }

    $stmt = $conn->prepare("SELECT id, nombre FROM concejales WHERE id = ?");
    $stmt->bind_param("i", $concejal_id_logueado);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $concejales[] = $r;
    }
}

$search = $_GET['buscar'] ?? '';
$fil_usuario = $_GET['f_usuario'] ?? '';
$fil_concejal = $_GET['f_concejal'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Exportar Votantes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
body {
    background: linear-gradient(to bottom right, #0033cc, #6699ff);
    font-family: 'Segoe UI', sans-serif;
    color: #333;
}
.container {
    background-color: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    padding: 30px;
    margin-top: 50px;
    max-width: 760px;
}
h2 {
    color: #0033cc;
    font-weight: 600;
}
.form-control:focus {
    border-color: #0056b3;
    box-shadow: 0 0 0 0.2rem rgba(0,86,179,0.25);
}
.btn-primary { background-color: #0056b3; border-color: #004fa3; }
.btn-success { background-color: #28a745; border-color: #218838; }
.btn-danger { background-color: #dc3545; border-color: #c82333; }
.btn-dark { background-color: #1f2937; border-color: #111827; }
</style>
</head>
<body>

<main class="container">
<h2 class="mb-4">Exportar Votantes</h2>

<form method="GET" id="formFiltros" autocomplete="off" class="mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <input
            type="text"
            name="buscar"
            class="form-control flex-grow-1"
            placeholder="Buscar por nombre o cedula"
            value="<?= htmlspecialchars($search) ?>"
            style="min-width: 180px; max-width: 300px;"
        />

        <?php if ($rol === 'admin' || $rol === 'concejal'): ?>
            <select name="f_concejal" id="f_concejal" class="form-select flex-grow-1" style="min-width: 180px; max-width: 220px;">
                <?php if ($rol === 'admin'): ?><option value="">-- Todos los concejales --</option><?php endif; ?>
                <?php foreach ($concejales as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $fil_concejal == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="f_usuario" id="f_usuario" class="form-select flex-grow-1" style="min-width: 180px; max-width: 220px;" data-selected="<?= htmlspecialchars($fil_usuario) ?>">
                <option value="">-- Todos los usuarios --</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $fil_usuario == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <div class="d-flex gap-2 flex-wrap justify-content-center mt-2">
            <button type="submit" class="btn btn-primary">Actualizar filtros</button>
            <a href="index" class="btn btn-secondary">Volver</a>
        </div>
    </div>
</form>

<div class="d-flex gap-2 flex-wrap justify-content-center mt-3">
    <a href="exportar_excel?action=xlsx&<?= http_build_query($_GET) ?>" class="btn btn-success">Exportar Excel</a>
    <a href="exportar_pdf?action=pdf&<?= http_build_query($_GET) ?>" class="btn btn-danger">Exportar PDF</a>
</div>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectConcejal = document.getElementById('f_concejal');
    const selectUsuario = document.getElementById('f_usuario');
    const usuarioSeleccionado = selectUsuario ? selectUsuario.dataset.selected : null;

    function cargarUsuarios(idConcejal) {
        if (!selectUsuario) return;
        selectUsuario.innerHTML = '<option value="">Cargando...</option>';
        fetch(`ajax_usuarios_por_concejal?id_concejal=${idConcejal}`)
            .then(resp => resp.json())
            .then(data => {
                selectUsuario.innerHTML = '<option value="">-- Todos los usuarios --</option>';
                data.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.textContent = u.nombre;
                    if (usuarioSeleccionado && String(u.id) === String(usuarioSeleccionado)) {
                        opt.selected = true;
                    }
                    selectUsuario.appendChild(opt);
                });
            })
            .catch(() => {
                selectUsuario.innerHTML = '<option value="">Error al cargar</option>';
            });
    }

    if (selectConcejal) {
        selectConcejal.addEventListener('change', () => {
            if (selectConcejal.value) {
                cargarUsuarios(selectConcejal.value);
            } else if (selectUsuario) {
                selectUsuario.innerHTML = '<option value="">-- Todos los usuarios --</option>';
            }
        });

        if (selectConcejal.value) {
            cargarUsuarios(selectConcejal.value);
        }
    }

    if (selectUsuario) {
        selectUsuario.addEventListener('change', () => {
            if (selectUsuario.value !== '') {
                document.getElementById('formFiltros').submit();
            }
        });
    }
});
</script>

</body>
</html>

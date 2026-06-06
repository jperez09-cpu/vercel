<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'user';
$cod_usuario = $_SESSION['usuario']; 

$concejal_id_logueado = null;
$usuarios = []; 
$concejales = [];

// 1. Obtener ID de concejal si el rol es concejal
if ($rol === 'concejal') {
    $stmt = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1");
    $stmt->bind_param("s", $cod_usuario);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $concejal_id_logueado = $row['id'];
    }
}

// 2. Cargar datos para los SELECTS
if ($rol === 'admin') {
    $resC = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
    while ($r = $resC->fetch_assoc()) { $concejales[] = $r; }
    
    $fil_concejal = $_GET['f_concejal'] ?? '';
    if ($fil_concejal) {
        $stmtU = $conn->prepare("SELECT id, nombre_completo AS nombre FROM usuarios WHERE id_concejal = ? ORDER BY nombre_completo");
        $stmtU->bind_param("i", $fil_concejal);
        $stmtU->execute();
        $resU = $stmtU->get_result();
        while ($r = $resU->fetch_assoc()) { $usuarios[] = $r; }
    }
} elseif ($rol === 'concejal' && $concejal_id_logueado) {
    $stmtU = $conn->prepare("SELECT id, nombre_completo AS nombre FROM usuarios WHERE id_concejal = ? ORDER BY nombre_completo");
    $stmtU->bind_param("i", $concejal_id_logueado);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    while ($r = $resU->fetch_assoc()) { $usuarios[] = $r; }
}

// 3. Filtros y Paginación
$search = $_GET['buscar'] ?? '';
$fil_usuario = $_GET['f_usuario'] ?? '';
$fil_concejal = $_GET['f_concejal'] ?? '';
$por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 10;
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;

// 4. Construcción del WHERE
$where = "WHERE (v.nombre LIKE ? OR v.cedula LIKE ?)";
$tipos = "ss";
$params = ["%$search%", "%$search%"];

if ($rol === 'user') {
    $where .= " AND v.id_usuario = ?";
    $tipos .= "i";
    $params[] = $usuario_id;
} elseif ($rol === 'concejal' && $concejal_id_logueado) {
    $where .= " AND v.id_concejal = ?";
    $tipos .= "i";
    $params[] = $concejal_id_logueado;
    if (!empty($fil_usuario)) { $where .= " AND v.id_usuario = ?"; $tipos .= "i"; $params[] = (int)$fil_usuario; }
} elseif ($rol === 'admin') {
    if (!empty($fil_concejal)) { $where .= " AND v.id_concejal = ?"; $tipos .= "i"; $params[] = (int)$fil_concejal; }
    if (!empty($fil_usuario)) { $where .= " AND v.id_usuario = ?"; $tipos .= "i"; $params[] = (int)$fil_usuario; }
}

// 5. Consultas
$sqlCount = "SELECT COUNT(*) total FROM votantes v $where";
$stmtC = $conn->prepare($sqlCount);
$stmtC->bind_param($tipos, ...$params);
$stmtC->execute();
$total = $stmtC->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total / $por_pagina);

$sql = "SELECT v.*, b.descripcion AS barrio, u.nombre_completo AS dirigente 
        FROM votantes v 
        LEFT JOIN barrios b ON v.id_barrios = b.id_barrios 
        LEFT JOIN usuarios u ON v.id_usuario = u.id 
        $where ORDER BY v.id DESC LIMIT ? OFFSET ?";
$tiposFinal = $tipos . "ii";
$paramsFinal = array_merge($params, [$por_pagina, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($tiposFinal, ...$paramsFinal);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Consultar Votantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body { background: linear-gradient(to bottom right, #0033cc, #6699ff); font-family: 'Segoe UI', sans-serif; min-height: 100vh; }
        .container { background: #fff; border-radius: 16px; padding: 30px; margin-top: 30px; margin-bottom: 30px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
        .table thead th { background: #0056b3; color: white; position: sticky; top: 0; z-index: 10; }
        .page-item.active .page-link { background: #0033cc; border-color: #0033cc; }
        .btn-action { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
    </style>
</head>
<body>

<main class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <h2 class="text-primary m-0">🔎 Consultar Votantes</h2>
        <span class="badge bg-primary fs-5"><?= $total ?> Votantes</span>
    </div>

    <form method="GET" id="formFiltros" class="mb-4">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" name="buscar" class="form-control" placeholder="Nombre o Cédula" value="<?= htmlspecialchars($search) ?>">
            </div>

            <?php if ($rol === 'admin'): ?>
            <div class="col-md-2">
                <select name="f_concejal" id="f_concejal" class="form-select">
                    <option value="">-- Concejales --</option>
                    <?php foreach ($concejales as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $fil_concejal == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($rol === 'admin' || $rol === 'concejal'): ?>
            <div class="col-md-2">
                <select name="f_usuario" id="f_usuario" class="form-select" data-selected="<?= $fil_usuario ?>">
                    <option value="">-- Dirigentes --</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $fil_usuario == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-2">
                <select name="por_pagina" class="form-select" onchange="this.form.submit()">
                    <?php foreach ([10, 25, 50, 100] as $v): ?>
                        <option value="<?= $v ?>" <?= $por_pagina == $v ? 'selected' : '' ?>><?= $v ?> x pág.</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                <a href="index" class="btn btn-secondary w-100">Inicio</a>
            </div>
        </div>
    </form>

    <div class="table-responsive" style="max-height: 500px;">
        <table class="table table-hover table-striped align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Votante</th>
                    <th>Cédula</th>
                    <th>Barrio</th>
                    <?php if ($rol !== 'user'): ?><th>Dirigente</th><?php endif; ?>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $i = $offset + 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-primary fw-bold"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['nombre'].' '.$row['apellido']) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($row['cedula']) ?></td>
                        <td><?= htmlspecialchars($row['barrio'] ?? '-') ?></td>
                        <?php if ($rol !== 'user'): ?>
                            <td class="small text-muted"><?= htmlspecialchars($row['dirigente'] ?? 'Sin asignar') ?></td>
                        <?php endif; ?>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="editar_votante?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm btn-action" title="Editar">✏️</a>
                                <button type="button" class="btn btn-danger btn-sm btn-action" 
                                        data-bs-toggle="modal" data-bs-target="#modalEliminar" 
                                        data-id="<?= $row['id'] ?>" 
                                        data-nombre="<?= htmlspecialchars($row['nombre'].' '.$row['apellido']) ?>" 
                                        data-cedula="<?= htmlspecialchars($row['cedula']) ?>" title="Eliminar">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center p-4 text-muted">No hay resultados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_paginas > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center flex-wrap">
            <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina-1 ?>&buscar=<?= urlencode($search) ?>&f_usuario=<?= $fil_usuario ?>&f_concejal=<?= urlencode($fil_concejal) ?>&por_pagina=<?= $por_pagina ?>">«</a>
            </li>
            <?php
            $rango = 2;
            for ($p = 1; $p <= $total_paginas; $p++) {
                if ($p == 1 || $p == $total_paginas || ($p >= $pagina - $rango && $p <= $pagina + $rango)) {
                    echo '<li class="page-item '.($p == $pagina ? 'active' : '').'"><a class="page-link" href="?pagina='.$p.'&buscar='.urlencode($search).'&f_usuario='.$fil_usuario.'&f_concejal='.$fil_concejal.'&por_pagina='.$por_pagina.'">'.$p.'</a></li>';
                } elseif ($p == $pagina - $rango - 1 || $p == $pagina + $rango + 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            ?>
            <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                <a class="page-link" href="?pagina=<?= $pagina+1 ?>&buscar=<?= urlencode($search) ?>&f_usuario=<?= $fil_usuario ?>&f_concejal=<?= urlencode($fil_concejal) ?>&por_pagina=<?= $por_pagina ?>">»</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</main>

<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formEliminar" method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Eliminar Registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ¿Confirmas que deseas eliminar a <strong id="elNombre"></strong>?<br>
                    Cédula: <strong id="elCedula"></strong>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Confirmar Eliminación</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Lógica de Filtros Dinámicos
    const selectConcejal = document.getElementById('f_concejal');
    const selectUsuario = document.getElementById('f_usuario');

    if (selectConcejal && selectUsuario) {
        selectConcejal.addEventListener('change', function() {
            const id = this.value;
            selectUsuario.innerHTML = '<option value="">Cargando...</option>';
            fetch(`ajax_usuarios_por_concejal.php?id_concejal=${id}`)
                .then(r => r.json())
                .then(data => {
                    selectUsuario.innerHTML = '<option value="">-- Dirigentes --</option>';
                    data.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.nombre;
                        selectUsuario.appendChild(opt);
                    });
                });
        });
    }

    // Lógica del Modal de Eliminación
    const modal = document.getElementById('modalEliminar');
    modal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        const id = btn.dataset.id;
        document.getElementById('elNombre').textContent = btn.dataset.nombre;
        document.getElementById('elCedula').textContent = btn.dataset.cedula;
        document.getElementById('formEliminar').action = 'eliminar_votante.php?id=' + id;
    });
});
</script>
</body>
</html>
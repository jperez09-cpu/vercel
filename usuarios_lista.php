<?php
require_once 'sesion.php';
iniciarSesionSegura();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login");
    exit();
}

if (!isset($_SESSION['usuario']) || !in_array($_SESSION['rol'], ['admin','concejal'])) {
    header('Location: index');
    exit;
}

include 'conexion.php';
$rol = $_SESSION['rol'];
$usuario_cod = $_SESSION['usuario'];

// Para concejal logueado
$concejal_id_logueado = null;
if($rol === 'concejal'){
    $stmt = $conn->prepare("SELECT id FROM concejales WHERE nro_cedula = ? LIMIT 1");
    $stmt->bind_param("s", $usuario_cod);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $concejal_id_logueado = $row['id'] ?? null;
}

// Filtros
$buscar = $_GET['buscar'] ?? '';
$f_concejal = $_GET['f_concejal'] ?? '';

// Construcción del WHERE
$where = [];
$params = [];
$tipos = "";

if($buscar !== ''){
    $where[] = "(u.nombre_completo LIKE ? OR u.nombre_usuario LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $tipos .= "ss";
}

if($rol === 'admin' && $f_concejal !== '' && is_numeric($f_concejal)){
    $where[] = "u.id_concejal = ?";
    $params[] = $f_concejal;
    $tipos .= "i";
}elseif($rol === 'concejal'){
    $where[] = "u.id_concejal = ?";
    $params[] = $concejal_id_logueado;
    $tipos .= "i";
}

// Paginación
$pagina = max(1,intval($_GET['pagina'] ?? 1));
$por_pagina = 10;
$offset = ($pagina-1)*$por_pagina;

// Total registros
$sqlCount = "SELECT COUNT(*) total FROM usuarios u";
if(count($where)) $sqlCount .= " WHERE ".implode(" AND ",$where);
$stmtC = $conn->prepare($sqlCount);
if($tipos) $stmtC->bind_param($tipos, ...$params);
$stmtC->execute();
$total = $stmtC->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total/$por_pagina);

// Consulta principal
$sql = "SELECT u.*, b.descripcion as barrio, c.nombre as concejal
        FROM usuarios u
        LEFT JOIN barrios b ON u.id_barrio = b.id_barrios
        LEFT JOIN concejales c ON u.id_concejal = c.id";
if(count($where)) $sql .= " WHERE ".implode(" AND ",$where);
$sql .= " ORDER BY u.id ASC LIMIT ? OFFSET ?";

$tipos .= "ii";
$params[] = $por_pagina;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Lista de concejales para filtros (admin)
$concejales = [];
if($rol === 'admin'){
    // El Admin ve a todos
    $resC = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
    while($c = $resC->fetch_assoc()) $concejales[] = $c;
} elseif($rol === 'concejal' && $concejal_id_logueado){
    // 🟢 CORRECCIÓN AQUÍ: Buscamos el nombre real del concejal logueado
    $stmtNom = $conn->prepare("SELECT id, nombre FROM concejales WHERE id = ?");
    $stmtNom->bind_param("i", $concejal_id_logueado);
    $stmtNom->execute();
    $resNom = $stmtNom->get_result();
    if($cNom = $resNom->fetch_assoc()){
        $concejales[] = $cNom; // Ahora guarda ['id' => X, 'nombre' => 'Nombre Real']
    }
    $stmtNom->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lista de Usuarios</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(to bottom right, #0033cc, #6699ff);
    font-family:'Segoe UI',sans-serif;
    color:#333;
}
.container {
    background-color: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    padding: 30px;
    margin-top: 50px;
}
h2 { color:#0033b3; font-weight:600; margin-bottom:20px; }
.form-control:focus, .form-select:focus {
    border-color: #0056b3;
    box-shadow: 0 0 0 0.2rem rgba(0,86,179,0.25);
}
.btn-primary { background-color:#0056b3; border-color:#004fa3; }
.btn-warning { background-color:#ffc107; border-color:#e0a800; color:#000; }
.btn-danger { background-color:#dc3545; border-color:#c82333; }
.btn-secondary { background-color:#6c757d; border-color:#5a6268; }
.table thead th {
    background-color:#0056b3;
    color:#fff;
    position:sticky;
    top:0;
    z-index:1;
}
.table td, .table th { vertical-align:middle; }
.table-responsive { max-height:500px; overflow-y:auto; }

/* Móvil */
@media (max-width: 767.98px) {
    form.d-flex {
        flex-direction: column; /* filtros apilados si no caben */
        align-items: stretch;
    }

    form.d-flex input,
    form.d-flex select {
        width: 100%;
        max-width: 100%;
        margin-bottom: 0.5rem;
    }

    /* Botones siempre en la misma línea */
    .botones-form {
        flex-direction: row !important;
        justify-content: center;
        width: 100%;
    }

    .botones-form .btn {
        flex: 1;            /* que ocupen espacio proporcional */
        max-width: 180px;   /* límite de ancho para que no queden gigantes */
        margin-bottom: 0;   /* eliminar margen inferior */
    }
}
</style>
</head>
<body>
<div class="container">
<h2>📋 Lista de Dirigentes</h2>

<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <!-- Filtro Buscar -->
    <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre o usuario" value="<?= htmlspecialchars($buscar) ?>" style="min-width:180px; max-width:300px;">

    <!-- Filtro Concejal -->
    <?php if($rol==='admin' || $rol==='concejal'): ?>
    <select name="f_concejal" class="form-select" style="min-width:180px; max-width:220px;">
        <?php if($rol==='admin'): ?><option value="">-- Todos los concejales --</option><?php endif; ?>
        <?php foreach($concejales as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $f_concejal==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <!-- Botones Buscar y Volver -->
    <div class="ms-auto d-flex gap-2 flex-wrap botones-form">
        <button type="submit" class="btn btn-primary">🔍 Buscar</button>
        <a href="index" class="btn btn-secondary">🏠 Volver</a>
        <a href="dirigentes_csv?buscar=<?= urlencode($buscar) ?>&f_concejal=<?= urlencode($f_concejal) ?>" class="btn btn-success">📥 Excel</a>
    </div>
</form>

<div class="table-responsive">
<table class="table table-striped align-middle mb-0">
<thead>
<tr>
<th>#</th> <th>Nombre completo</th>
<th>Usuario</th>
<th>Rol</th>
<th>Barrio</th>
<th>Zona</th>
<th>Teléfono</th>
<th class="text-center">Acciones</th>
</tr>
</thead>
<tbody>
<?php if($result->num_rows > 0): 
    // Calcular el número inicial para esta página
    $nro = $offset + 1; 
    
    while($row = $result->fetch_assoc()): 
?>
<tr>
    <td class="fw-bold text-primary"><?= $nro++ ?></td>
    
    <td><?= htmlspecialchars($row['nombre_completo']) ?></td>
    <td><?= htmlspecialchars($row['nombre_usuario']) ?></td>
    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['rol']) ?></span></td>
    <td><?= htmlspecialchars($row['barrio'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($row['zona'] ?? 'N/A') ?></td>
    <td><?= htmlspecialchars($row['telefono'] ?? 'N/A') ?></td>
    <td class="text-center">
        <div class="d-grid gap-1 d-md-flex justify-content-md-center">
            <a href="editar_usuario?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm px-3">✏️ Editar</a>
            <button 
                class="btn btn-danger btn-sm px-3"
                data-bs-toggle="modal" 
                data-bs-target="#modalEliminarUsuario" 
                data-id="<?= $row['id'] ?>" 
                data-nombre="<?= htmlspecialchars($row['nombre_completo']) ?>"
                data-usuario="<?= htmlspecialchars($row['nombre_usuario']) ?>"
            >🗑️ Eliminar</button>
        </div>
    </td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="8" class="text-center text-muted p-4">No se encontraron dirigentes con esos criterios.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Modal Eliminación -->
<div class="modal fade" id="modalEliminarUsuario" tabindex="-1" aria-labelledby="modalEliminarLabelUsuario" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formEliminarUsuario" method="POST" action="">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="modalEliminarLabelUsuario">Confirmar Eliminación</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          ¿Estás seguro de eliminar al usuario <strong id="modalNombreUsuario"></strong> con usuario <strong id="modalUsuario"></strong>?
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-danger">✅ Sí, eliminar</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Paginación -->
<?php if($total_paginas>1): ?>
<nav aria-label="Paginación"><ul class="pagination justify-content-center mt-3">
<?php for($i=1;$i<=$total_paginas;$i++): ?>
<li class="page-item <?= $i==$pagina?'active':'' ?>"><a class="page-link" href="?pagina=<?= $i ?>&buscar=<?= urlencode($buscar) ?>&f_concejal=<?= urlencode($f_concejal) ?>"><?= $i ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalEliminar = document.getElementById('modalEliminarUsuario');
    modalEliminar.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nombre = button.getAttribute('data-nombre');
        const usuario = button.getAttribute('data-usuario');
        modalEliminar.querySelector('#modalNombreUsuario').textContent = nombre;
        modalEliminar.querySelector('#modalUsuario').textContent = usuario;
        modalEliminar.querySelector('#formEliminarUsuario').action = 'eliminar_usuario?id=' + id;
    });
});
</script>
</body>
</html>

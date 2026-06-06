<?php
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['admin', 'concejal'])) {
    header('Location: login');
    exit;
}

include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: usuarios_lista.php');
    exit;
}

// Obtener datos actuales del usuario
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    echo "Usuario no encontrado.";
    exit;
}

// Obtener lista de barrios
$barrios = [];
$barrios_result = $conn->query("SELECT id_barrios, descripcion FROM barrios ORDER BY descripcion");
if ($barrios_result) {
    while ($row = $barrios_result->fetch_assoc()) {
        $barrios[] = $row;
    }
}

// Obtener lista de concejales
$concejales = [];
$concejales_result = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
if ($concejales_result) {
    while ($row = $concejales_result->fetch_assoc()) {
        $concejales[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #0044cc, #66ccff);
            font-family: 'Segoe UI', sans-serif;
            padding: 30px;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            max-width: 600px;
        }

        h2 {
            color: #0044cc;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>✏️ Editar Usuario</h2>

    <form action="editar_usuario_proceso.php" method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id']) ?>">

        <div class="mb-3">
            <label for="nombre_completo" class="form-label">Nombre completo</label>
            <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" required value="<?= htmlspecialchars($usuario['nombre_completo']) ?>">
        </div>

        <div class="mb-3">
            <label for="nombre_usuario" class="form-label">Nombre de usuario</label>
            <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required value="<?= htmlspecialchars($usuario['nombre_usuario']) ?>">
        </div>

        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-select" required>
                <option value="user" <?= $usuario['rol'] === 'user' ? 'selected' : '' ?>>Usuario</option>
                <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                <option value="concejal" <?= $usuario['rol'] === 'concejal' ? 'selected' : '' ?>>Concejal</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="id_concejal" class="form-label">Concejal</label>
            <select name="id_concejal" id="id_concejal" class="form-select" required>
                <option value="">Seleccione un concejal</option>
                <?php foreach ($concejales as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($usuario['id_concejal'] == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="id_barrio" class="form-label">Barrio</label>
            <select name="id_barrio" id="id_barrio" class="form-select" required>
                <option value="">Seleccione un barrio</option>
                <?php foreach ($barrios as $b): ?>
                    <option value="<?= $b['id_barrios'] ?>" <?= $usuario['id_barrio'] == $b['id_barrios'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['descripcion']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="zona" class="form-label">Zona</label>
            <input type="text" name="zona" id="zona" class="form-control" value="<?= htmlspecialchars($usuario['zona']) ?>">
        </div>

        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="tel" name="telefono" id="telefono" class="form-control" required value="<?= htmlspecialchars($usuario['telefono']) ?>" pattern="[0-9]{7,15}">
        </div>

        <div class="d-flex justify-content-center gap-3 mt-4">
            <a href="usuarios_lista" class="btn btn-secondary">🔙 Volver</a>
            <button type="submit" class="btn btn-primary">💾 Guardar Cambios</button>
        </div>

    </form>
</div>

</body>
</html>

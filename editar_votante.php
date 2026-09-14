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

$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    echo "ID no válido.";
    exit;
}

// Obtener datos del votante
$stmt = $conn->prepare("SELECT * FROM votantes WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo "Votante no encontrado.";
    exit;
}

$votante = $resultado->fetch_assoc();

// Obtener lista de barrios
$barrios = [];
$result_barrio = $conn->query("SELECT id_barrios, descripcion FROM barrios ORDER BY descripcion ASC");
while ($row = $result_barrio->fetch_assoc()) {
    $barrios[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre     = trim($_POST['nombre']);
    $apellido   = trim($_POST['apellido']);
    $cedula     = trim($_POST['cedula']);
    $telefono   = trim($_POST['telefono']);
    $zona       = trim($_POST['zona']);
    $id_barrios = (int) $_POST['id_barrios'];

    $stmt = $conn->prepare("
        UPDATE votantes 
        SET nombre = ?, apellido = ?, cedula = ?, telefono = ?, zona = ?, id_barrios = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssssiii",
        $nombre,
        $apellido,
        $cedula,
        $telefono,
        $zona,
        $id_barrios,
        $id
    );

    if ($stmt->execute()) {
        header("Location: consultar_votantes?actualizado=1");
        exit;
    } else {
        $error = "Error al actualizar los datos: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Votante</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(to bottom right, #0033cc, #6699ff);
      font-family: 'Segoe UI', sans-serif;
    }
    .form-container {
      background-color: #fff;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
      padding: 30px;
      margin-top: 50px;
      max-width: 850px;
    }
    h2 {
      color: #0033cc;
      font-weight: 600;
    }
  </style>
</head>
<body>

<main class="container-fluid px-3 px-md-5 py-4">
  <div class="form-container mx-auto">
    <h2 class="mb-4 text-center">✏️ Editar Votante</h2>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">

      <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($votante['nombre']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" class="form-control" required value="<?= htmlspecialchars($votante['apellido']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Cédula</label>
        <input type="text" name="cedula" class="form-control" required value="<?= htmlspecialchars($votante['cedula']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" required value="<?= htmlspecialchars($votante['telefono']) ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Barrio</label>
        <select name="id_barrios" class="form-select" required>
          <option value="">Seleccione un barrio</option>
          <?php foreach ($barrios as $barrio): ?>
            <option value="<?= $barrio['id_barrios'] ?>" <?= $votante['id_barrios'] == $barrio['id_barrios'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($barrio['descripcion']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Zona</label>
        <input type="text" name="zona" class="form-control" required value="<?= htmlspecialchars($votante['zona']) ?>">
      </div>

      <div class="col-12 text-center mt-4">
        <button type="submit" class="btn btn-success px-4">💾 Guardar Cambios</button>
        <a href="consultar_votantes" class="btn btn-secondary px-4 ms-2">↩️ Volver</a>
      </div>
    </form>
  </div>
</main>

</body>
</html>

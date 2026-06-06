<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Si es admin, permitir ver todas las funciones, si es un usuario normal, restringir acceso
if ($_SESSION['rol'] !== 'admin') {
    echo "Acceso restringido solo para administradores.";
    exit();
}

require_once 'config/conexion.php';

$query = "SELECT * FROM votantes";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Consultar Votantes</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container mt-5">
    <h2 class="text-center">Consulta de Votantes</h2>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Apellido</th>
          <th>Cédula</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['nombre']; ?></td>
            <td><?php echo $row['apellido']; ?></td>
            <td><?php echo $row['cedula']; ?></td>
            <td>
              <a href="editar_votante.php?id=<?php echo $row['id']; ?>" class="btn btn-warning">Editar</a>
              <a href="eliminar_votante.php?id=<?php echo $row['id']; ?>" class="btn btn-danger">Eliminar</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
  </div>
</body>
</html>

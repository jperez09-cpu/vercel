<?php

require_once 'sesion.php';
iniciarSesionSegura();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_destroy();
    header('Location: login');
    exit;
}

$nombre_completo = $_SESSION['nombre_usuario'] ?? 'usuario';
$rol = $_SESSION['rol'] ?? 'usuario';

$menu = [
    'admin' => [
        ['Inicio', 'index', 'btn-inicio'],
        ['Registrar Votante', 'registrar', 'btn-registrar'],
        ['Consultar Votantes', 'consultar_votantes', 'btn-consultar'],
        ['Exportar Votantes', 'exportar_votantes', 'btn-excel'],
        ['Crear Usuario/Dirigente', 'crear_usuario', 'btn-usuario'],
        ['Lista de Dirigentes', 'usuarios_lista', 'btn-lista'],
        ['Cerrar sesion', 'logout', 'btn-logout'],
    ],
    'usuario' => [
        ['Inicio', 'index', 'btn-inicio'],
        ['Registrar Votante', 'registrar', 'btn-registrar'],
        ['Consultar Votantes', 'consultar_votantes', 'btn-consultar'],
        ['Exportar Votantes', 'exportar_votantes', 'btn-excel'],
        ['Cerrar sesion', 'logout', 'btn-logout'],
    ],
    'concejal' => [
        ['Inicio', 'index', 'btn-inicio'],
        ['Registrar Votante', 'registrar', 'btn-registrar'],
        ['Consultar Votantes', 'consultar_votantes', 'btn-consultar'],
        ['Crear Usuario/Dirigente', 'crear_usuario', 'btn-usuario'],
        ['Lista de Dirigentes', 'usuarios_lista', 'btn-lista'],
        ['Exportar Votantes', 'exportar_votantes', 'btn-excel'],
        ['Cerrar sesion', 'logout', 'btn-logout'],
    ],
];

$menu_actual = $menu[$rol] ?? $menu['usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="manifest" href="assets/manifest.php" type="application/manifest+json">
  <meta name="theme-color" content="#1976d2">
  <title>PLRA JAS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <style>
    body {
      background: linear-gradient(to bottom right, #0033cc, #66aaff);
      font-family: 'Segoe UI', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      border-radius: 20px;
      padding: 40px 30px;
      backdrop-filter: blur(12px);
      background-color: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
      max-width: 460px;
      width: 100%;
      color: white;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .card h2 {
      color: #ffffff;
      font-weight: 600;
    }

    .btn-custom {
      border-radius: 12px;
      font-size: 1.05rem;
      margin-bottom: 10px;
      color: white;
      font-weight: 500;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
      transition: all 0.2s ease-in-out;
    }

    .btn-custom:hover {
      opacity: 0.9;
      transform: translateY(-1px);
    }

    .btn-inicio { background: #007bff; }
    .btn-registrar { background: #28a745; }
    .btn-consultar { background: #17a2b8; }
    .btn-excel { background: #20c997; }
    .btn-usuario { background: #6f42c1; }
    .btn-lista { background: #1d680d; }
    .btn-logout { background: #343a40; }
  </style>
</head>
<body>

<div class="card text-center">
  <h2 class="mb-3">PLRA JAS</h2>
  <p class="text-light mb-4">Bienvenido, <strong><?= htmlspecialchars($nombre_completo) ?></strong></p>

  <?php foreach ($menu_actual as [$nombre, $ruta, $clase]): ?>
    <a href="<?= htmlspecialchars($ruta) ?>" class="btn btn-custom <?= $clase ?> w-100"><?= htmlspecialchars($nombre) ?></a>
  <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js')
        .then(reg => console.log('Service Worker registrado con exito', reg))
        .catch(err => console.warn('Error al registrar el Service Worker', err));
    });
  }
</script>
</body>
</html>

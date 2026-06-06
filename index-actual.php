<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login');
    exit;
}

$usuario = $_SESSION['usuario'] ?? 'Usuario';
$rol = $_SESSION['rol'] ?? 'usuario';

$menu = [
    'admin' => [
        'Inicio' => 'index.php',
        'Registrar Votante' => 'registrar.php',
        'Consultar Votantes' => 'consultar_votantes.php',
        'Exportar Excel' => 'exportar_csv.php',
        'Exportar PDF' => 'exportar_pdf.php',
        'Crear Usuario' => 'crear_usuario.php',
        'Cerrar sesión' => 'logout.php',
    ],
    'usuario' => [
        'Inicio' => 'index.php',
        'Registrar Votante' => 'registrar.php',
        'Consultar Votantes' => 'consultar_votantes.php',
        'Exportar Excel' => 'exportar_csv.php',
        'Exportar PDF' => 'exportar_pdf.php',
        'Cerrar sesión' => 'logout.php',
    ],
];

$menu_actual = $menu[$rol] ?? $menu['usuario'];
$archivo_actual = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel con Menú Lateral y Responsive</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    /* Sidebar azul fijo desktop */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      width: 220px;
      background-color: #0d6efd; /* azul bootstrap */
      padding: 1.5rem 1rem;
      overflow-y: auto;
      color: white;
      z-index: 1040;
      transition: transform 0.3s ease-in-out;
    }

    .sidebar h3 {
      color: white;
      margin-bottom: 1.5rem;
      font-weight: 600;
      font-size: 1.5rem;
    }

    .sidebar .nav-link {
      color: white;
      margin-bottom: 0.5rem;
      font-weight: 500;
      border-radius: 0.375rem;
      display: block;
      padding: 0.5rem 1rem;
      transition: background-color 0.2s ease;
      text-decoration: none;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      background-color: #3399ff; /* azul más claro */
      color: white;
      text-decoration: none;
    }

    /* Contenido desplazado por sidebar */
    .content {
      margin-left: 220px;
      padding: 2rem;
      min-height: 100vh;
      background-color: #f8f9fa;
      transition: margin-left 0.3s ease-in-out;
    }

    /* Botón hamburguesa para móvil */
    .navbar-toggler {
      border-color: rgba(255, 255, 255, 0.1);
    }

    /* Ajustes para móviles */
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 220px;
        transform: translateX(-100%);
        /* Oculto por defecto */
      }
      .sidebar.show {
        transform: translateX(0);
      }

      .content {
        margin-left: 0;
        padding: 1rem;
      }

      /* Barra superior */
      .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1050;
      }

      /* Agregar margen superior al contenido para no taparlo con navbar */
      .content {
        padding-top: 70px;
      }
    }
  </style>
</head>
<body>

  <!-- Navbar móvil con botón toggle -->
  <nav class="navbar navbar-dark bg-primary d-md-none">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button" id="menu-toggle" aria-label="Toggle menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <span class="navbar-text text-white fw-semibold"><?= htmlspecialchars($usuario) ?> (<?= htmlspecialchars($rol) ?>)</span>
    </div>
  </nav>

  <!-- Sidebar -->
  <nav class="sidebar" id="sidebarMenu" aria-label="Menú principal">
    <h3>Menú</h3>
    <ul class="nav flex-column">
      <?php foreach ($menu_actual as $texto => $url):
        $clase_activa = ($archivo_actual === basename($url)) ? 'active' : '';
      ?>
      <li class="nav-item">
        <a href="<?= htmlspecialchars($url) ?>" class="nav-link <?= $clase_activa ?>">
          <?= htmlspecialchars($texto) ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <!-- Contenido principal -->
  <main class="content">
    <h1>Panel de Control</h1>
    <p>Bienvenido, <?= htmlspecialchars($usuario) ?> (<?= htmlspecialchars($rol) ?>)</p>
    <!-- Aquí contenido -->
  </main>

  <script>
    // Toggle menú sidebar en móvil
    const toggleBtn = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebarMenu');

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
    });

    // Opcional: cerrar menú al hacer clic en enlace (en móvil)
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 769) {
          sidebar.classList.remove('show');
        }
      });
    });
  </script>

</body>
</html>

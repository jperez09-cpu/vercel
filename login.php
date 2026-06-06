<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sesion.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$error = '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (isset($_GET['limpiar_cookies']) && $_GET['limpiar_cookies'] === '1') {
    borrarCookiesViejasSesion();
}

if ($requestMethod === 'POST') {
    require_once 'conexion.php';

    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre_usuario === '' || $password === '') {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM usuarios WHERE nombre_usuario = ? LIMIT 1');
        $stmt->bind_param('s', $nombre_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $usuario = $result->fetch_assoc();

            if (password_verify($password, $usuario['password'])) {
                iniciarSesionSegura();

                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario'] = $usuario['nombre_usuario'];
                $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                header('Location: /index');
                exit;
            }
        }

        $error = 'Usuario o contrasena incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>Sistema de Gestion Interna</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <meta name="theme-color" content="#1976d2">

  <style>
    :root { --bg-color: #f8f9fa; --text-color: #212529; --box-color: #ffffff; }
    @media (prefers-color-scheme: dark) {
      :root { --bg-color: #121212; --text-color: #f1f1f1; --box-color: #1e1e1e; }
    }

    body {
      background-color: var(--bg-color);
      color: var(--text-color);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
    }

    .login-container {
      background: var(--box-color);
      width: 90%;
      max-width: 420px;
      padding: 2.5rem;
      border-radius: 1.5rem;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    h2, h4 { font-weight: 600; color: #0033cc; }
    .btn-primary { background-color: #0033cc; border: none; padding: 0.7rem; }
    .btn-primary:hover { background-color: #002699; }
  </style>
</head>
<body>

  <form action="/login" method="POST" class="login-container" novalidate autocomplete="off">
    <div class="text-center mb-4">
        <h2 class="h4 mb-1">J. Augusto Saldivar</h2>
        <p class="text-muted">Administracion de Contactos Barriales</p>
        <hr>
        <h5 class="mt-3">Iniciar Sesion</h5>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-danger py-2" style="font-size: 0.9rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-3">
      <label for="nombre_usuario" class="form-label">Usuario</label>
      <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required value="<?= htmlspecialchars($_POST['nombre_usuario'] ?? '') ?>">
    </div>

    <div class="mb-4">
      <label for="password" class="form-label">Contrasena</label>
      <input type="password" name="password" id="password" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary w-100 shadow-sm">Ingresar al Sistema</button>
  </form>

  <footer class="text-center mt-4">
    <p style="font-size: 0.85rem; color: #666;">
        &copy; 2026 Sistema de Gestion Interna - J.A.S <br>
        <a href="/privacidad" style="color: #0d6efd; text-decoration: none;">Politica de Privacidad</a>
    </p>
  </footer>
<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations()
    .then(registrations => registrations.forEach(registration => registration.unregister()))
    .catch(() => {});
}
</script>
</body>
</html>

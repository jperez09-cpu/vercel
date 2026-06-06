<?php
session_start();
require_once 'conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido');
    }

    $nombre_usuario = trim($_POST['nombre_usuario']);
    $password = $_POST['password'];

    // Buscar usuario por nombre_usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if ($usuario) {
        // Validar bloqueo por intentos fallidos
        $ahora = new DateTime();
        $ultimoIntento = new DateTime($usuario['ultimo_intento'] ?? '2000-01-01');
        $diff = $ultimoIntento->diff($ahora);
        $minutosPasados = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        if ($usuario['intentos_fallidos'] >= 5 && $minutosPasados < 15) {
            $error = "Cuenta bloqueada temporalmente. Inténtalo más tarde.";
        } else {
            // Verificar password
            if (password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);

                // Resetear intentos fallidos
                $stmtUpdate = $conn->prepare("UPDATE usuarios SET intentos_fallidos = 0, ultimo_intento = NULL WHERE id = ?");
                $stmtUpdate->bind_param("i", $usuario['id']);
                $stmtUpdate->execute();

                // Guardar datos de sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario'] = $usuario['nombre_usuario'];
                $_SESSION['rol'] = $usuario['rol'];

                // Cargar permisos (si tienes tabla roles_permisos)
                $permisos = [];
                $stmtPerm = $conn->prepare("SELECT modulo, permiso FROM roles_permisos WHERE rol = ?");
                $stmtPerm->bind_param("s", $usuario['rol']);
                $stmtPerm->execute();
                $permResult = $stmtPerm->get_result();
                while ($perm = $permResult->fetch_assoc()) {
                    $key = $perm['modulo'] . '.' . $perm['permiso'];
                    $permisos[$key] = true;
                }
                $_SESSION['permisos'] = $permisos;

                // Recordarme cookie (segura solo en HTTPS)
                if (!empty($_POST['recordarme'])) {
                    setcookie("recordarme", $usuario['id'], time() + (86400 * 30), "/", "", isset($_SERVER['HTTPS']), true);
                }

                header("Location: index");
                exit;
            } else {
                // Incrementar intentos fallidos
                $stmtUpdate = $conn->prepare("UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1, ultimo_intento = NOW() WHERE id = ?");
                $stmtUpdate->bind_param("i", $usuario['id']);
                $stmtUpdate->execute();

                $error = "Usuario o contraseña incorrectos.";
            }
        }
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: #f8f9fa;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 1rem;
    }
    .login-container {
      width: 100%;
      max-width: 400px;
      background: #fff;
      padding: 2rem;
      border-radius: 0.5rem;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body>
  <form method="POST" class="login-container" novalidate>
    <h4 class="mb-4 text-center">Iniciar Sesión</h4>

    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="mb-3">
      <label for="nombre_usuario" class="form-label">Usuario</label>
      <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required />
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Contraseña</label>
      <input type="password" name="password" id="password" class="form-control" required />
    </div>
    <div class="mb-3 form-check">
      <input type="checkbox" name="recordarme" id="recordarme" class="form-check-input" />
      <label for="recordarme" class="form-check-label">Recordarme</label>
    </div>
    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
  </form>
</body>
</html>

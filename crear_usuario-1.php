<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Crear Usuario - Registro de Votantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #0033cc, #6699ff);
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            margin-top: 60px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            max-width: 500px;
        }
        h2 {
            color: #0033cc;
            margin-bottom: 30px;
        }
        .form-label {
            font-weight: 600;
        }
        .btn-primary {
            background-color: #0056b3;
            border-color: #004fa3;
        }
        .btn-primary:hover {
            background-color: #004099;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #5a6268;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2 class="text-center">👤 Crear Usuario</h2>
        <form action="crear_usuario_proceso.php" method="POST">
            <div class="mb-3">
                <label for="nombre_usuario" class="form-label">Nombre de usuario</label>
                <input type="text" name="nombre_usuario" class="form-control" id="nombre_usuario" required />
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" id="password" required />
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select name="rol" class="form-select" id="rol" required>
                    <option value="user">Usuario</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                <button type="submit" class="btn btn-primary">✅ Crear Usuario</button>
                <a href="index.php" class="btn btn-secondary">🏠 Volver</a>
            </div>
        </form>
    </div>

</body>
</html>

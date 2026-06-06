<?php
// Reporte de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// SEGURIDAD: Solo admin y concejal pueden entrar
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['admin', 'concejal'])) {
    header('Location: index');
    exit;
}

require_once 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="robots" content="noindex, nofollow">
    <title>Crear Usuario - Registro de Votantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #0033cc, #6699ff);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container-custom {
            background-color: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            max-width: 500px;
            width: 100%;
        }
        h2 {
            color: #0033cc;
            margin-bottom: 30px;
            font-weight: 700;
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

    <div class="container-custom">
        <h2 class="text-center">👤 Crear Usuario</h2>
        
        <form action="crear_usuario_proceso.php" method="POST" autocomplete="off">
            
            <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="modal fade" id="mensajeModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header <?= $_SESSION['mensaje']['tipo'] === 'success' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                    <h5 class="modal-title">
                      <?= $_SESSION['mensaje']['tipo'] === 'success' ? '✅ Éxito' : '❌ Error' ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                  </div>
                </div>
              </div>
            </div>
            <?php unset($_SESSION['mensaje']); endif; ?>

            <div class="mb-3">
                <label for="nombre_completo" class="form-label">Nombre completo</label>
                <input type="text" name="nombre_completo" class="form-control" id="nombre_completo" required />
            </div>

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
                    <option value="concejal">Concejal</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="id_concejal" class="form-label">Concejal:</label>
                <select name="id_concejal" id="id_concejal" class="form-select">
                    <option value="">Seleccione un concejal</option>
                    <?php
                    $concejales = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
                    if ($concejales) {
                        while ($row = $concejales->fetch_assoc()) {
                            echo '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['nombre']).'</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="barrio" class="form-label">Barrio:</label>
                <select name="id_barrio" id="barrio" class="form-select" required>
                    <option value="">Seleccione un barrio</option>
                    <?php
                    $barrios = $conn->query("SELECT id_barrios, descripcion FROM barrios ORDER BY descripcion");
                    if ($barrios) {
                        while ($row = $barrios->fetch_assoc()) {
                            echo '<option value="'.htmlspecialchars($row['id_barrios']).'">'.htmlspecialchars($row['descripcion']).'</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="zona" class="form-label">Zona</label>
                <input type="text" name="zona" class="form-control" id="zona" />
            </div>

            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono</label>
                <input type="tel" name="telefono" class="form-control" id="telefono" pattern="[0-9]{7,15}" />
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary me-2">✅ Crear Usuario</button>
                <a href="index" class="btn btn-secondary">🏠 Volver</a>
            </div>
        </form>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var mensajeModalElem = document.getElementById('mensajeModal');
        if (mensajeModalElem) {
            var modal = new bootstrap.Modal(mensajeModalElem);
            modal.show();
        }
    });
</script>

</body>
</html>
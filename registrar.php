<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// Verificamos sesión (Asegúrate de que 'usuario' esté definido en el login)
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Registro de Votantes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(to bottom right, #0033cc, #6699ff);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            margin-top: 50px;
            margin-bottom: 50px;
            max-width: 800px;
        }
        h2 { color: #0033cc; font-weight: 600; }
        .btn-primary { background-color: #0069d9; border-color: #0062cc; }
        .loading-spinner { display: none; }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center">
    <div class="form-container w-100">
        <header class="text-center mb-4">
            <h2>📝 Registro de Votantes</h2>
            <p class="text-muted">Consulte la cédula para autocompletar datos</p>
        </header>

        <form action="registrar_votante.php" method="POST" class="row g-3" id="formVotante">
            
            <div class="col-md-6">
                <label class="form-label fw-bold">Cédula</label>
                <div class="input-group">
                    <input type="number" name="cedula" id="cedula" class="form-control" placeholder="Ej: 1234567" required />
                    <button class="btn btn-dark" type="button" id="btnBuscarPadron">
                        <span class="spinner-border spinner-border-sm loading-spinner" id="spinner"></span>
                        <span id="btnTexto">🔍 Buscar</span>
                    </button>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Teléfono</label>
                <input type="text" name="telefono" class="form-control" required />
            </div>

            <div class="col-md-6">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" id="nombre" class="form-control" readonly required />
            </div>

            <div class="col-md-6">
                <label class="form-label">Apellido</label>
                <input type="text" name="apellido" id="apellido" class="form-control" readonly required />
            </div>

            <div class="col-md-6">
                <label for="barrio" class="form-label fw-bold">Barrio</label>
                <select name="id_barrios" id="barrio" class="form-select" required>
                    <option value="">Seleccione un barrio</option>
                    <?php
                    include 'conexion.php';
                    $query = "SELECT id_barrios, descripcion FROM barrios ORDER BY descripcion";
                    $result = $conn->query($query);
                    if ($result) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<option value=\"{$row['id_barrios']}\">".htmlspecialchars($row['descripcion'])."</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label fw-bold">Zona</label>
                <input type="text" name="zona" class="form-control" />
            </div>

            <div class="col-12 text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">✅ Guardar</button>
                <a href="index" class="btn btn-secondary btn-lg">🔙 Volver</a>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="mensajeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div id="modalHeader" class="modal-header text-white">
        <h5 class="modal-title" id="modalTitulo">Mensaje</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalCuerpo"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnBuscar = document.getElementById('btnBuscarPadron');
    const inputCedula = document.getElementById('cedula');
    const spinner = document.getElementById('spinner');
    const btnTexto = document.getElementById('btnTexto');
    
    // Función para mostrar el modal
    function lanzarModal(titulo, mensaje, tipo = 'primary') {
        const modalEl = new bootstrap.Modal(document.getElementById('mensajeModal'));
        const header = document.getElementById('modalHeader');
        header.className = `modal-header text-white bg-${tipo}`;
        document.getElementById('modalTitulo').innerText = titulo;
        document.getElementById('modalCuerpo').innerText = mensaje;
        modalEl.show();
    }

    // Búsqueda en el Padrón
    btnBuscar.addEventListener('click', function() {
        const cedula = inputCedula.value.trim();
        
        if (cedula === "" || isNaN(cedula)) {
            lanzarModal("⚠️ Atención", "Por favor, ingrese un número de cédula válido.", "warning");
            return;
        }

        // Interfaz de carga
        btnBuscar.disabled = true;
        spinner.style.display = "inline-block";
        btnTexto.innerText = " Buscando...";

        fetch(`buscar_padron.php?cedula=${cedula}`)
            .then(response => response.json())
            .then(data => {
                btnBuscar.disabled = false;
                spinner.style.display = "none";
                btnTexto.innerText = "🔍 Buscar";

                if (data.status === 'success') {
                    document.getElementById('nombre').value = data.nombre;
                    document.getElementById('apellido').value = data.apellido;
                } else {
                    document.getElementById('nombre').value = "";
                    document.getElementById('apellido').value = "";
                    lanzarModal("❌ No encontrado", "La cédula " + cedula + " no existe en el padrón JAS.", "danger");
                }
            })
            .catch(error => {
                btnBuscar.disabled = false;
                spinner.style.display = "none";
                btnTexto.innerText = "🔍 Buscar";
                lanzarModal("Error", "No se pudo conectar con la base de datos del padrón.", "danger");
            });
    });

    // Manejo de mensajes por URL (Success/Error al registrar)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        lanzarModal("✔️ Éxito", "Votante registrado correctamente.", "success");
    } else if (urlParams.has('error')) {
        lanzarModal("⚠️ Error", "Hubo un problema al guardar los datos.", "danger");
    }
});
</script>
</body>
</html>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sesion.php';
iniciarSesionSegura();
require_once 'conexion.php';
require_once 'dia_d_settings.php';

function obtenerColumnasTabla(mysqli $conn, string $tabla): array
{
    $columnas = [];
    $resultado = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $tabla) . "`");

    if (!$resultado) {
        return $columnas;
    }

    while ($fila = $resultado->fetch_assoc()) {
        $columnas[strtolower($fila['Field'])] = $fila;
    }

    return $columnas;
}

function buscarCampo(array $columnas, array $candidatos): ?string
{
    foreach ($candidatos as $campo) {
        $llave = strtolower($campo);
        if (isset($columnas[$llave])) {
            return $columnas[$llave]['Field'];
        }
    }

    return null;
}

function buscarCampoPorFragmento(array $columnas, array $fragmentos, ?callable $filtro = null): ?string
{
    foreach ($columnas as $campo) {
        $nombre = strtolower($campo['Field']);

        foreach ($fragmentos as $fragmento) {
            if (strpos($nombre, strtolower($fragmento)) === false) {
                continue;
            }

            if ($filtro && !$filtro($campo)) {
                continue;
            }

            return $campo['Field'];
        }
    }

    return null;
}

function tipoBindParaCampo(string $tipoSql): string
{
    $tipo = strtolower($tipoSql);

    if (
        strpos($tipo, 'int') !== false ||
        strpos($tipo, 'bit') !== false ||
        strpos($tipo, 'decimal') !== false ||
        strpos($tipo, 'float') !== false ||
        strpos($tipo, 'double') !== false
    ) {
        return 'i';
    }

    return 's';
}

function bindearParametros(mysqli_stmt $stmt, string $tipos, array &$valores): bool
{
    $parametros = [$tipos];

    foreach ($valores as $indice => &$valor) {
        $parametros[] = &$valor;
    }

    return call_user_func_array([$stmt, 'bind_param'], $parametros);
}

function obtenerValorMarcado(string $tipoSql)
{
    $tipo = strtolower($tipoSql);

    if (strpos($tipo, 'datetime') !== false || strpos($tipo, 'timestamp') !== false) {
        return date('Y-m-d H:i:s');
    }

    if (strpos($tipo, 'time') !== false) {
        return date('H:i:s');
    }

    if (strpos($tipo, 'date') !== false) {
        return date('Y-m-d');
    }

    if (
        strpos($tipo, 'int') !== false ||
        strpos($tipo, 'bit') !== false ||
        strpos($tipo, 'decimal') !== false ||
        strpos($tipo, 'float') !== false ||
        strpos($tipo, 'double') !== false
    ) {
        return 1;
    }

    if (strpos($tipo, 'enum(') === 0 && preg_match_all("/'([^']+)'/", $tipoSql, $coincidencias)) {
        $preferidos = ['si', 'SI', 'S', '1', 'true', 'TRUE', 'voto', 'VOTO'];

        foreach ($preferidos as $preferido) {
            if (in_array($preferido, $coincidencias[1], true)) {
                return $preferido;
            }
        }

        return $coincidencias[1][0] ?? 'SI';
    }

    return 'SI';
}

function estaMarcado($valor): bool
{
    if ($valor === null) {
        return false;
    }

    if (is_numeric($valor)) {
        return (float) $valor > 0;
    }

    $texto = strtolower(trim((string) $valor));

    if ($texto === '' || in_array($texto, ['0', 'no', 'n', 'false', 'null'], true)) {
        return false;
    }

    return true;
}

function escaparIdentificador(string $valor): string
{
    return '`' . str_replace('`', '``', $valor) . '`';
}

function asegurarTablaAuxiliarDiaD(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `padron_dia_d_estado` (
            `cedula` VARCHAR(32) NOT NULL,
            `en_padron` TINYINT(1) NOT NULL DEFAULT 0,
            `ya_voto` TINYINT(1) NOT NULL DEFAULT 0,
            `fecha_voto` DATETIME NULL DEFAULT NULL,
            `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`cedula`),
            KEY `idx_pds_ya_voto` (`ya_voto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    @$conn->query($sql);
}

function buscarRegistroPadron(
    mysqli $conn,
    string $tabla,
    string $campoCedula,
    string $cedula,
    array $campos
): ?array {
    $campos = array_values(array_unique(array_filter($campos)));

    if (empty($campos)) {
        return null;
    }

    $camposSql = implode(', ', array_map('escaparIdentificador', $campos));
    $sql = "SELECT $camposSql FROM " . escaparIdentificador($tabla) . " WHERE " . escaparIdentificador($campoCedula) . " = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $cedula);
    $stmt->execute();
    $resultado = $stmt->get_result();

    return $resultado ? ($resultado->fetch_assoc() ?: null) : null;
}

$tablaPadron = 'padron_jas';
$columnas = obtenerColumnasTabla($conn, $tablaPadron);
diaDAsegurarTablaConfig($conn);
$marcacionHabilitada = diaDMarcacionHabilitada($conn);

$campoCedula = buscarCampo($columnas, ['cedula', 'nro_cedula', 'documento']);
$campoNombre = buscarCampo($columnas, ['nombre', 'nombres', 'nombre_completo']);
$campoApellido = buscarCampo($columnas, ['apellido', 'apellidos']);
$campoVoto = buscarCampo($columnas, ['voto_dia_d', 'voto', 'votado', 'ya_voto', 'ha_votado', 'sufrago', 'sufragio']);
$campoFechaVoto = buscarCampo($columnas, ['fecha_voto_dia_d', 'fecha_voto', 'hora_voto', 'fecha_hora_voto', 'voto_fecha']);

if (!$campoVoto) {
    $campoVoto = buscarCampoPorFragmento($columnas, ['vot', 'sufrag']);
}

if (!$campoFechaVoto) {
    $campoFechaVoto = buscarCampoPorFragmento(
        $columnas,
        ['vot', 'sufrag', 'dia_d'],
        static function (array $campo): bool {
            $tipo = strtolower($campo['Type'] ?? '');
            return strpos($tipo, 'date') !== false || strpos($tipo, 'time') !== false;
        }
    );
}

$campoMarcado = $campoVoto ?: $campoFechaVoto;

$rol = $_SESSION['rol'] ?? 'publico';
$cedula = preg_replace('/\D+/', '', $_REQUEST['cedula'] ?? '');
$mensaje = '';
$tipoMensaje = 'info';
$registro = null;

if (empty($columnas)) {
    $mensaje = 'No fue posible leer la estructura de la tabla del padron.';
    $tipoMensaje = 'danger';
} elseif (!$campoCedula) {
    $mensaje = 'No se encontro una columna de cedula compatible en padron_jas.';
    $tipoMensaje = 'danger';
} elseif ($cedula !== '') {
    $camposConsulta = [$campoCedula, $campoNombre, $campoApellido, $campoVoto, $campoFechaVoto];
    $registro = buscarRegistroPadron($conn, $tablaPadron, $campoCedula, $cedula, $camposConsulta);

    if (!$registro) {
        $mensaje = 'La cedula ingresada no existe en el padron.';
        $tipoMensaje = 'warning';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'marcar') {
    if ($cedula === '') {
        $mensaje = 'Ingresa una cedula valida para marcar el voto.';
        $tipoMensaje = 'warning';
    } elseif (!$marcacionHabilitada) {
        $mensaje = 'La marcacion de votos esta deshabilitada temporalmente por administracion.';
        $tipoMensaje = 'warning';
    } elseif (!$registro) {
        $mensaje = 'No se encontro el registro en el padron.';
        $tipoMensaje = 'warning';
    } elseif (!$campoMarcado) {
        $mensaje = 'No se encontro una columna compatible para marcar el voto en padron_jas.';
        $tipoMensaje = 'danger';
    } elseif (estaMarcado($registro[$campoMarcado] ?? null)) {
        $mensaje = 'Este ciudadano ya figura como marcado.';
        $tipoMensaje = 'info';
    } else {
        $tipoSqlVoto = $columnas[strtolower($campoMarcado)]['Type'] ?? 'varchar(5)';
        $valorVoto = obtenerValorMarcado($tipoSqlVoto);
        $tipos = tipoBindParaCampo($tipoSqlVoto);
        $valores = [$valorVoto];
        $set = [escaparIdentificador($campoMarcado) . ' = ?'];

        if ($campoFechaVoto && $campoFechaVoto !== $campoMarcado) {
            $tipoSqlFecha = $columnas[strtolower($campoFechaVoto)]['Type'] ?? 'datetime';
            $valorFecha = obtenerValorMarcado($tipoSqlFecha);
            $set[] = escaparIdentificador($campoFechaVoto) . ' = ?';
            $tipos .= tipoBindParaCampo($tipoSqlFecha);
            $valores[] = $valorFecha;
        }

        $tipos .= 's';
        $valores[] = $cedula;

        $sqlUpdate = "UPDATE " . escaparIdentificador($tablaPadron) .
            " SET " . implode(', ', $set) .
            " WHERE " . escaparIdentificador($campoCedula) . " = ? LIMIT 1";

        $stmtUpdate = $conn->prepare($sqlUpdate);

        if (!$stmtUpdate) {
            $mensaje = 'No se pudo preparar la actualizacion del voto.';
            $tipoMensaje = 'danger';
        } else {
            bindearParametros($stmtUpdate, $tipos, $valores);

            if ($stmtUpdate->execute()) {
                asegurarTablaAuxiliarDiaD($conn);

                $fechaAuxiliar = date('Y-m-d H:i:s');
                if ($campoFechaVoto && isset($valorFecha) && !empty($valorFecha)) {
                    if (strlen((string) $valorFecha) === 8) {
                        $fechaAuxiliar = date('Y-m-d') . ' ' . $valorFecha;
                    } elseif (strlen((string) $valorFecha) >= 10) {
                        $fechaAuxiliar = (string) $valorFecha;
                    }
                }

                $sqlAux = "
                    INSERT INTO `padron_dia_d_estado` (`cedula`, `en_padron`, `ya_voto`, `fecha_voto`)
                    VALUES (?, 1, 1, ?)
                    ON DUPLICATE KEY UPDATE
                        en_padron = VALUES(en_padron),
                        ya_voto = VALUES(ya_voto),
                        fecha_voto = VALUES(fecha_voto)
                ";
                $stmtAux = $conn->prepare($sqlAux);
                if ($stmtAux) {
                    $stmtAux->bind_param('ss', $cedula, $fechaAuxiliar);
                    $stmtAux->execute();
                }

                $camposConsulta = [$campoCedula, $campoNombre, $campoApellido, $campoVoto, $campoFechaVoto];
                $registro = buscarRegistroPadron($conn, $tablaPadron, $campoCedula, $cedula, $camposConsulta);
                $mensaje = 'Voto marcado correctamente para la cedula ingresada.';
                $tipoMensaje = 'success';
            } else {
                $mensaje = 'No se pudo marcar el voto: ' . $stmtUpdate->error;
                $tipoMensaje = 'danger';
            }
        }
    }
}

$nombreMostrar = '';
if ($registro) {
    $nombre = $campoNombre && isset($registro[$campoNombre]) ? trim((string) $registro[$campoNombre]) : '';
    $apellido = $campoApellido && isset($registro[$campoApellido]) ? trim((string) $registro[$campoApellido]) : '';
    $nombreMostrar = trim($nombre . ' ' . $apellido);

    if ($nombreMostrar === '') {
        $nombreMostrar = $nombre !== '' ? $nombre : 'Sin nombre disponible';
    }
}

$votoMarcado = $registro && $campoMarcado ? estaMarcado($registro[$campoMarcado] ?? null) : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dia D</title>
    <link rel="manifest" href="assets/manifest.php" type="application/manifest+json">
    <meta name="theme-color" content="#1976d2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #002b7f 0%, #0d6efd 55%, #77b7ff 100%);
            font-family: 'Segoe UI', sans-serif;
        }

        .page-shell {
            width: min(760px, 100%);
        }

        .surface-card {
            border: 0;
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
        }

        .hero-band {
            background: linear-gradient(135deg, #0d6efd, #dc3545);
            color: #fff;
            border-radius: 24px 24px 0 0;
            padding: 28px 28px 22px;
        }

        .hero-band h1 {
            font-size: 2rem;
            margin-bottom: 0.35rem;
        }

        .status-chip {
            border-radius: 999px;
            font-weight: 600;
            padding: 0.55rem 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }

        .record-box {
            border: 1px solid #e9ecef;
            border-radius: 18px;
            background: #f8fbff;
        }

        .record-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0b3d91;
        }
    </style>
</head>
<body>
    <main class="container py-4 py-md-5 d-flex justify-content-center">
        <div class="page-shell">
            <section class="surface-card bg-white overflow-hidden">
                <div class="hero-band">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1>Dia D</h1>
                            <p class="mb-0">Buscar por cedula en el padron y marcar como voto.</p>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                            <span class="badge <?= $marcacionHabilitada ? 'text-bg-success' : 'text-bg-danger' ?>">
                                <?= $marcacionHabilitada ? 'Marcacion habilitada' : 'Marcacion deshabilitada' ?>
                            </span>
                            <a href="index" class="btn btn-light fw-semibold">Volver al menu</a>
                        </div>
                    </div>
                </div>

                <div class="p-4 p-md-5">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="cedula" class="form-label fw-semibold">Numero de cedula</label>
                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                class="form-control form-control-lg"
                                id="cedula"
                                name="cedula"
                                value="<?= htmlspecialchars($cedula) ?>"
                                placeholder="Ej: 1234567"
                                required
                                <?= $cedula === '' ? 'autofocus' : '' ?>
                            >
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Buscar en padron</button>
                        </div>
                    </form>

                    <?php if ($registro): ?>
                        <section class="record-box p-4 mt-4">
                            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                <div>
                                    <div class="text-muted small">Nombre</div>
                                    <div class="record-value"><?= htmlspecialchars($nombreMostrar) ?></div>
                                </div>
                                <div class="text-md-end">
                                    <div class="text-muted small">Cedula</div>
                                    <div class="record-value"><?= htmlspecialchars($registro[$campoCedula]) ?></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div class="status-chip <?= $votoMarcado ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis' ?>">
                                    <?= $votoMarcado ? 'Estado: ya marcado' : 'Estado: pendiente de voto' ?>
                                </div>

                                <?php if ($campoMarcado): ?>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="accion" value="marcar">
                                        <input type="hidden" name="cedula" value="<?= htmlspecialchars($cedula) ?>">
                                        <button type="submit" class="btn btn-danger btn-lg" <?= ($votoMarcado || !$marcacionHabilitada) ? 'disabled' : '' ?>>
                                            Marcar como voto
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="d-flex flex-column align-items-start gap-2">
                                        <span class="text-danger fw-semibold">No se detecto una columna de voto o fecha de voto en la tabla.</span>
                                        <?php if ($rol === 'admin'): ?>
                                            <a href="inicializar_dia_d" class="btn btn-outline-danger">Crear columnas de Dia D</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($campoFechaVoto && !empty($registro[$campoFechaVoto])): ?>
                                <div class="mt-3 text-muted small">
                                    Ultimo registro de voto: <?= htmlspecialchars((string) $registro[$campoFechaVoto]) ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php if ($mensaje !== ''): ?>
        <?php
            $tituloModal = 'Informacion';
            $claseTitulo = 'text-primary';

            if ($tipoMensaje === 'success') {
                $tituloModal = 'Exito';
                $claseTitulo = 'text-success';
            } elseif ($tipoMensaje === 'warning') {
                $tituloModal = 'Aviso';
                $claseTitulo = 'text-warning';
            } elseif ($tipoMensaje === 'danger') {
                $tituloModal = 'Error';
                $claseTitulo = 'text-danger';
            }
        ?>
        <div class="modal fade" id="modalMensajeDiaD" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-body text-center py-4">
                        <h2 class="h4 <?= $claseTitulo ?> mb-2"><?= htmlspecialchars($tituloModal) ?></h2>
                        <p class="mb-0"><?= htmlspecialchars($mensaje) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($mensaje !== ''): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('modalMensajeDiaD');
                if (!modalElement) {
                    return;
                }

                const modal = new bootstrap.Modal(modalElement);
                modal.show();

                window.setTimeout(() => {
                    modal.hide();
                }, 2000);
            });
        </script>
    <?php endif; ?>
</body>
</html>

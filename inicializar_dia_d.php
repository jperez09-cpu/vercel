<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sesion.php';
iniciarSesionSegura();
require_once 'dia_d_admin_auth.php';

if (!diaDAdminTieneAcceso()) {
    header('Location: control_dia_d');
    exit;
}

require_once 'conexion.php';
require_once 'dia_d_settings.php';

function obtenerColumnas(mysqli $conn, string $tabla): array
{
    $columnas = [];
    $resultado = $conn->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $tabla) . "`");

    if (!$resultado) {
        return $columnas;
    }

    while ($fila = $resultado->fetch_assoc()) {
        $columnas[strtolower($fila['Field'])] = $fila['Field'];
    }

    return $columnas;
}

function buscarCampo(array $columnas, array $candidatos): ?string
{
    foreach ($candidatos as $campo) {
        $llave = strtolower($campo);
        if (isset($columnas[$llave])) {
            return $columnas[$llave];
        }
    }

    return null;
}

function buscarCampoPorFragmento(array $columnas, array $fragmentos, ?callable $filtro = null): ?string
{
    foreach ($columnas as $campo) {
        $nombre = strtolower($campo);

        foreach ($fragmentos as $fragmento) {
            if (strpos($nombre, strtolower($fragmento)) === false) {
                continue;
            }

            if ($filtro && !$filtro($campo)) {
                continue;
            }

            return $campo;
        }
    }

    return null;
}

function escaparIdentificador(string $valor): string
{
    return '`' . str_replace('`', '``', $valor) . '`';
}

function existeIndice(mysqli $conn, string $tabla, string $indice): bool
{
    $sql = "SHOW INDEX FROM " . escaparIdentificador($tabla) . " WHERE Key_name = '" . $conn->real_escape_string($indice) . "'";
    $resultado = $conn->query($sql);
    return $resultado && $resultado->num_rows > 0;
}

function existeTabla(mysqli $conn, string $tabla): bool
{
    $sql = "SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'";
    $resultado = $conn->query($sql);
    return $resultado && $resultado->num_rows > 0;
}

$tabla = 'padron_jas';
$columnas = obtenerColumnas($conn, $tabla);
$errores = [];
$acciones = [];

if (!isset($columnas['voto_dia_d'])) {
    $sql = "ALTER TABLE `padron_jas` ADD COLUMN `voto_dia_d` TINYINT(1) NOT NULL DEFAULT 0";
    if ($conn->query($sql)) {
        $acciones[] = 'Se creo la columna voto_dia_d.';
    } else {
        $errores[] = 'No se pudo crear voto_dia_d: ' . $conn->error;
    }
}

if (!isset($columnas['fecha_voto_dia_d'])) {
    $sql = "ALTER TABLE `padron_jas` ADD COLUMN `fecha_voto_dia_d` DATETIME NULL DEFAULT NULL";
    if ($conn->query($sql)) {
        $acciones[] = 'Se creo la columna fecha_voto_dia_d.';
    } else {
        $errores[] = 'No se pudo crear fecha_voto_dia_d: ' . $conn->error;
    }
}

if (!existeIndice($conn, 'padron_jas', 'idx_padron_cedula_dia_d')) {
    $sql = "ALTER TABLE `padron_jas` ADD INDEX `idx_padron_cedula_dia_d` (`cedula`)";
    if ($conn->query($sql)) {
        $acciones[] = 'Se creo el indice idx_padron_cedula_dia_d.';
    } else {
        $errores[] = 'No se pudo crear el indice del padron por cedula: ' . $conn->error;
    }
}

if (!existeIndice($conn, 'votantes', 'idx_votantes_cedula_dia_d')) {
    $sql = "ALTER TABLE `votantes` ADD INDEX `idx_votantes_cedula_dia_d` (`cedula`)";
    if ($conn->query($sql)) {
        $acciones[] = 'Se creo el indice idx_votantes_cedula_dia_d.';
    } else {
        $errores[] = 'No se pudo crear el indice de votantes por cedula: ' . $conn->error;
    }
}

if (existeTabla($conn, 'padron_mesa')) {
    if (!existeIndice($conn, 'padron_mesa', 'idx_padron_mesa_cedula_dia_d')) {
        $sql = "ALTER TABLE `padron_mesa` ADD INDEX `idx_padron_mesa_cedula_dia_d` (`cedula`)";
        if ($conn->query($sql)) {
            $acciones[] = 'Se creo el indice de locales por cedula.';
        } else {
            $errores[] = 'No se pudo crear el indice de locales por cedula: ' . $conn->error;
        }
    }

    if (!existeIndice($conn, 'padron_mesa', 'idx_padron_mesa_local_dia_d')) {
        $sql = "ALTER TABLE `padron_mesa` ADD INDEX `idx_padron_mesa_local_dia_d` (`local`)";
        if ($conn->query($sql)) {
            $acciones[] = 'Se creo el indice de locales de votacion.';
        } else {
            $errores[] = 'No se pudo crear el indice de locales de votacion: ' . $conn->error;
        }
    }
}

$sqlHelper = "
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
if ($conn->query($sqlHelper)) {
    $acciones[] = 'Se verifico la tabla auxiliar padron_dia_d_estado.';
} else {
    $errores[] = 'No se pudo crear la tabla auxiliar de Dia D: ' . $conn->error;
}

diaDAsegurarTablaConfig($conn);
$acciones[] = 'Se verifico la configuracion de habilitacion de marcacion.';

$columnasActualizadas = obtenerColumnas($conn, $tabla);
$campoCedula = buscarCampo($columnasActualizadas, ['cedula', 'nro_cedula', 'documento']);
$campoVoto = buscarCampo($columnasActualizadas, ['voto_dia_d', 'voto', 'votado', 'ya_voto', 'ha_votado', 'sufrago', 'sufragio']);
$campoFechaVoto = buscarCampo($columnasActualizadas, ['fecha_voto_dia_d', 'fecha_voto', 'hora_voto', 'fecha_hora_voto', 'voto_fecha']);

if (!$campoVoto) {
    $campoVoto = buscarCampoPorFragmento($columnasActualizadas, ['vot', 'sufrag']);
}

if (!$campoFechaVoto) {
    $campoFechaVoto = buscarCampoPorFragmento($columnasActualizadas, ['fecha', 'hora', 'vot', 'dia_d']);
}

if ($campoCedula && empty($errores)) {
    $condicionMarcado = "COALESCE(p." . escaparIdentificador($campoVoto ?: 'voto_dia_d') . ", 0) > 0";
    if (!$campoVoto) {
        $condicionMarcado = "0";
    }

    $exprFecha = $campoFechaVoto
        ? "MAX(p." . escaparIdentificador($campoFechaVoto) . ")"
        : "NULL";

    $sqlSync = "
        INSERT INTO `padron_dia_d_estado` (`cedula`, `en_padron`, `ya_voto`, `fecha_voto`)
        SELECT
            v.cedula,
            MAX(CASE WHEN p." . escaparIdentificador($campoCedula) . " IS NULL THEN 0 ELSE 1 END) AS en_padron,
            MAX(CASE WHEN " . $condicionMarcado . " THEN 1 ELSE 0 END) AS ya_voto,
            $exprFecha AS fecha_voto
        FROM (
            SELECT DISTINCT cedula
            FROM votantes
            WHERE cedula IS NOT NULL AND cedula <> ''
        ) v
        LEFT JOIN padron_jas p
            ON p." . escaparIdentificador($campoCedula) . " = v.cedula
        GROUP BY v.cedula
        ON DUPLICATE KEY UPDATE
            en_padron = VALUES(en_padron),
            ya_voto = VALUES(ya_voto),
            fecha_voto = VALUES(fecha_voto)
    ";

    if ($conn->query($sqlSync)) {
        $acciones[] = 'Se sincronizo la tabla auxiliar del Dia D con las cedulas de votantes.';
    } else {
        $errores[] = 'No se pudo sincronizar la tabla auxiliar del Dia D: ' . $conn->error;
    }
}

if (empty($acciones) && empty($errores)) {
    $acciones[] = 'Las columnas de Dia D ya existian.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicializar Dia D</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #002b7f 0%, #0d6efd 55%, #77b7ff 100%);
            font-family: 'Segoe UI', sans-serif;
        }

        .card-box {
            width: min(680px, 100%);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
        }
    </style>
</head>
<body>
    <main class="container py-4">
        <section class="card card-box border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-4 text-primary">Inicializacion de Dia D</h1>

                <?php foreach ($acciones as $accion): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($accion) ?></div>
                <?php endforeach; ?>

                <?php foreach ($errores as $error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="dia_d" class="btn btn-primary">Volver a Dia D</a>
                    <a href="index" class="btn btn-secondary">Ir al menu</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'sesion.php';
iniciarSesionSegura();
require_once 'dia_d_admin_auth.php';

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    diaDAdminCerrarAcceso();
    header('Location: control_dia_d');
    exit;
}

$errorAcceso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if (!diaDAdminIntentarAcceso($_POST['admin_password'])) {
        $errorAcceso = 'Clave incorrecta.';
    } else {
        header('Location: control_dia_d');
        exit;
    }
}

if (!diaDAdminTieneAcceso()) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Acceso Control Dia D</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #031c56 0%, #0d6efd 55%, #81bbff 100%);
                font-family: 'Segoe UI', sans-serif;
            }

            .access-card {
                width: min(460px, 100%);
                border-radius: 24px;
                box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
            }
        </style>
    </head>
    <body>
        <main class="container py-4">
            <section class="card access-card border-0 mx-auto">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 text-primary mb-3">Control Dia D</h1>
                    <p class="text-muted mb-4">Este enlace esta protegido por una clave separada del menu principal.</p>

                    <?php if ($errorAcceso !== ''): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errorAcceso) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="admin_password" class="form-label fw-semibold">Clave de acceso</label>
                            <input type="password" name="admin_password" id="admin_password" class="form-control" required autofocus>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                            <a href="dia_d" class="btn btn-outline-secondary">Ir a Dia D publico</a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

require_once 'conexion.php';
require_once 'dia_d_settings.php';

@$conn->query("SET SESSION SQL_BIG_SELECTS=1");
diaDAsegurarTablaConfig($conn);

$mensajeAccion = '';
$tipoMensajeAccion = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_panel']) && $_POST['accion_panel'] === 'toggle_marcacion') {
    $habilitar = ($_POST['nuevo_estado'] ?? '0') === '1';

    if (diaDEstablecerMarcacion($conn, $habilitar)) {
        $mensajeAccion = $habilitar
            ? 'La marcacion de votos fue habilitada.'
            : 'La marcacion de votos fue deshabilitada.';
        $tipoMensajeAccion = 'success';
    } else {
        $mensajeAccion = 'No se pudo actualizar el estado de la marcacion.';
        $tipoMensajeAccion = 'danger';
    }
}

$marcacionHabilitada = diaDMarcacionHabilitada($conn);

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

function existeTabla(mysqli $conn, string $tabla): bool
{
    $sql = "SHOW TABLES LIKE '" . $conn->real_escape_string($tabla) . "'";
    $resultado = $conn->query($sql);
    return $resultado && $resultado->num_rows > 0;
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

function escaparIdentificador(string $valor): string
{
    return '`' . str_replace('`', '``', $valor) . '`';
}

function normalizarCedulaSql(string $expresion): string
{
    return "REPLACE(REPLACE(REPLACE(TRIM($expresion), '.', ''), '-', ''), ' ', '')";
}

function esCampoNumerico(string $tipoSql): bool
{
    $tipo = strtolower($tipoSql);
    return strpos($tipo, 'int') !== false
        || strpos($tipo, 'bit') !== false
        || strpos($tipo, 'decimal') !== false
        || strpos($tipo, 'float') !== false
        || strpos($tipo, 'double') !== false;
}

function esCampoFechaHora(string $tipoSql): bool
{
    $tipo = strtolower($tipoSql);
    return strpos($tipo, 'date') !== false || strpos($tipo, 'time') !== false;
}

function condicionMarcadoSql(string $alias, string $campo, string $tipoSql): string
{
    $columna = $alias . '.' . escaparIdentificador($campo);

    if (esCampoNumerico($tipoSql)) {
        return "COALESCE($columna, 0) > 0";
    }

    if (esCampoFechaHora($tipoSql)) {
        return "($columna IS NOT NULL AND $columna NOT IN ('0000-00-00', '0000-00-00 00:00:00', '00:00:00'))";
    }

    return "TRIM(LOWER(COALESCE($columna, ''))) NOT IN ('', '0', 'no', 'n', 'false', 'null')";
}

function sincronizarEstadoDiaDIncremental(mysqli $conn): void
{
    $columnasPadron = obtenerColumnasTabla($conn, 'padron_jas');
    $campoCedula = buscarCampo($columnasPadron, ['cedula', 'nro_cedula', 'documento']);
    $campoVoto = buscarCampo($columnasPadron, ['voto_dia_d', 'voto', 'votado', 'ya_voto', 'ha_votado', 'sufrago', 'sufragio']);
    $campoFecha = buscarCampo($columnasPadron, ['fecha_voto_dia_d', 'fecha_voto', 'hora_voto', 'fecha_hora_voto', 'voto_fecha']);

    if (!$campoCedula) {
        return;
    }

    $campoCedulaSql = escaparIdentificador($campoCedula);
    $condicionMarcado = $campoVoto
        ? condicionMarcadoSql('p', $campoVoto, $columnasPadron[strtolower($campoVoto)]['Type'] ?? '')
        : '0';
    $exprFecha = $campoFecha ? 'MAX(p.' . escaparIdentificador($campoFecha) . ')' : 'NULL';

    // Add only new voters so the live panel stays light on shared hosting.
    $sql = "
        INSERT INTO `padron_dia_d_estado` (`cedula`, `en_padron`, `ya_voto`, `fecha_voto`)
        SELECT
            candidatos.cedula,
            MAX(CASE WHEN p.$campoCedulaSql IS NULL THEN 0 ELSE 1 END),
            MAX(CASE WHEN $condicionMarcado THEN 1 ELSE 0 END),
            $exprFecha
        FROM (
            SELECT DISTINCT v.cedula
            FROM votantes v
            LEFT JOIN `padron_dia_d_estado` estado ON estado.cedula = v.cedula
            WHERE v.cedula IS NOT NULL
                AND v.cedula <> ''
                AND estado.cedula IS NULL
        ) candidatos
        LEFT JOIN padron_jas p ON p.$campoCedulaSql = candidatos.cedula
        GROUP BY candidatos.cedula
        ON DUPLICATE KEY UPDATE
            en_padron = VALUES(en_padron),
            ya_voto = VALUES(ya_voto),
            fecha_voto = VALUES(fecha_voto)
    ";
    @$conn->query($sql);

    // Handle recent voter entries such as 1.234.567 or 1-234-567 without scanning the full padron.
    $sqlFormateadas = "
        SELECT DISTINCT v.cedula
        FROM votantes v
        LEFT JOIN `padron_dia_d_estado` estado ON estado.cedula = v.cedula
        WHERE v.cedula IS NOT NULL
            AND v.cedula <> ''
            AND COALESCE(estado.en_padron, 0) = 0
            AND REPLACE(REPLACE(REPLACE(v.cedula, '.', ''), '-', ''), ' ', '') <> v.cedula
        LIMIT 200
    ";
    $resultadoFormateadas = @$conn->query($sqlFormateadas);
    if (!$resultadoFormateadas) {
        return;
    }

    $campoVotoSelect = $campoVoto ? escaparIdentificador($campoVoto) : 'NULL';
    $campoFechaSelect = $campoFecha ? escaparIdentificador($campoFecha) : 'NULL';
    $stmtPadron = $conn->prepare("
        SELECT $campoVotoSelect AS valor_voto, $campoFechaSelect AS fecha_voto
        FROM padron_jas
        WHERE $campoCedulaSql = ?
        LIMIT 1
    ");
    $stmtAuxiliar = $conn->prepare("
        INSERT INTO `padron_dia_d_estado` (`cedula`, `en_padron`, `ya_voto`, `fecha_voto`)
        VALUES (?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            en_padron = VALUES(en_padron),
            ya_voto = VALUES(ya_voto),
            fecha_voto = VALUES(fecha_voto)
    ");
    if (!$stmtPadron || !$stmtAuxiliar) {
        return;
    }

    while ($fila = $resultadoFormateadas->fetch_assoc()) {
        $cedulaOriginal = (string) $fila['cedula'];
        $cedulaLimpia = preg_replace('/\D+/', '', $cedulaOriginal);
        if ($cedulaLimpia === '') {
            continue;
        }

        $stmtPadron->bind_param('s', $cedulaLimpia);
        $stmtPadron->execute();
        $registroPadron = $stmtPadron->get_result()->fetch_assoc();
        if (!$registroPadron) {
            continue;
        }

        $yaVoto = 0;
        if ($campoVoto) {
            $valorVoto = $registroPadron['valor_voto'];
            $yaVoto = is_numeric($valorVoto)
                ? ((float) $valorVoto > 0 ? 1 : 0)
                : (!in_array(strtolower(trim((string) $valorVoto)), ['', '0', 'no', 'n', 'false', 'null'], true) ? 1 : 0);
        }
        $fechaVoto = $registroPadron['fecha_voto'] ?: null;

        $stmtAuxiliar->bind_param('sis', $cedulaOriginal, $yaVoto, $fechaVoto);
        $stmtAuxiliar->execute();
    }
}

function obtenerVotosNoRegistradosPorLocal(mysqli $conn, array $localesVotacion): array
{
    $resultado = ['_total' => 0];
    foreach ($localesVotacion as $local) {
        $resultado[$local] = 0;
    }

    if (!existeTabla($conn, 'padron_dia_d_estado')) {
        return $resultado;
    }

    $sqlTotal = "
        SELECT COUNT(DISTINCT pds.cedula) AS total
        FROM padron_dia_d_estado pds
        LEFT JOIN votantes v ON v.cedula = pds.cedula
        WHERE pds.ya_voto = 1
            AND v.cedula IS NULL
    ";
    $filaTotal = @$conn->query($sqlTotal);
    if ($filaTotal) {
        $resultado['_total'] = (int) ($filaTotal->fetch_assoc()['total'] ?? 0);
    }

    if (empty($localesVotacion)) {
        return $resultado;
    }

    $columnasPadron = obtenerColumnasTabla($conn, 'padron_jas');
    $campoCedula = buscarCampo($columnasPadron, ['cedula', 'nro_cedula', 'documento']);
    $campoLocal = buscarCampo($columnasPadron, ['local_generales', 'local', 'local_votacion', 'colegio', 'lugar_votacion', 'establecimiento']);
    if (!$campoCedula || !$campoLocal) {
        return $resultado;
    }

    $campoCedulaSql = escaparIdentificador($campoCedula);
    $campoLocalSql = escaparIdentificador($campoLocal);
    $placeholders = implode(', ', array_fill(0, count($localesVotacion), '?'));
    $sql = "
        SELECT
            TRIM(p.$campoLocalSql) AS local_votacion,
            COUNT(DISTINCT pds.cedula) AS total
        FROM padron_dia_d_estado pds
        INNER JOIN padron_jas p ON p.$campoCedulaSql = pds.cedula
        LEFT JOIN votantes v ON v.cedula = pds.cedula
        WHERE TRIM(p.$campoLocalSql) IN ($placeholders)
            AND pds.ya_voto = 1
            AND v.cedula IS NULL
        GROUP BY TRIM(p.$campoLocalSql)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $resultado;
    }

    $tipos = str_repeat('s', count($localesVotacion));
    $stmt->bind_param($tipos, ...$localesVotacion);
    if (!$stmt->execute()) {
        return $resultado;
    }

    $filas = $stmt->get_result();
    while ($filas && $fila = $filas->fetch_assoc()) {
        $local = (string) $fila['local_votacion'];
        if (array_key_exists($local, $resultado)) {
            $resultado[$local] = (int) $fila['total'];
        }
    }

    return $resultado;
}

function sincronizarVotosExternosDiaD(mysqli $conn, array $localesVotacion): bool
{
    if (
        !existeTabla($conn, 'padron_dia_d_estado')
    ) {
        return false;
    }

    $columnasPadron = obtenerColumnasTabla($conn, 'padron_jas');
    $campoCedula = buscarCampo($columnasPadron, ['cedula', 'nro_cedula', 'documento']);
    $campoFecha = buscarCampo($columnasPadron, ['fecha_voto_dia_d', 'fecha_voto', 'hora_voto', 'fecha_hora_voto', 'voto_fecha']);
    $camposVoto = [];
    foreach (['voto_dia_d', 'voto', 'votado', 'ya_voto', 'ha_votado', 'sufrago', 'sufragio'] as $candidato) {
        $campo = buscarCampo($columnasPadron, [$candidato]);
        if ($campo) {
            $camposVoto[$campo] = condicionMarcadoSql('p', $campo, $columnasPadron[strtolower($campo)]['Type'] ?? '');
        }
    }
    if (!$campoCedula || empty($camposVoto)) {
        return false;
    }

    $campoCedulaSql = escaparIdentificador($campoCedula);
    $marcadoSql = '(' . implode(' OR ', $camposVoto) . ')';
    $fechaSql = $campoFecha ? 'MAX(p.' . escaparIdentificador($campoFecha) . ')' : 'NULL';
    $sql = "
        INSERT INTO padron_dia_d_estado (`cedula`, `en_padron`, `ya_voto`, `fecha_voto`)
        SELECT
            p.$campoCedulaSql,
            1,
            1,
            $fechaSql
        FROM padron_jas p
        WHERE $marcadoSql
        GROUP BY p.$campoCedulaSql
        ON DUPLICATE KEY UPDATE
            en_padron = VALUES(en_padron),
            ya_voto = VALUES(ya_voto),
            fecha_voto = VALUES(fecha_voto)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    return $stmt->execute();
}

$tablaEstadoDiaD = 'padron_dia_d_estado';
$helperDisponible = existeTabla($conn, $tablaEstadoDiaD);
$columnasHelper = $helperDisponible ? obtenerColumnasTabla($conn, $tablaEstadoDiaD) : [];
if ($helperDisponible) {
    sincronizarEstadoDiaDIncremental($conn);
}

$concejales = [];
$usuarios = [];
$localesVotacion = [
    'COL. HEROES DEL CHACO',
    'COL. SAN ROQUE GONZALEZ DE SANTA CRUZ',
];
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion_panel'] ?? '') === 'sincronizar_votos_externos'
) {
    if (sincronizarVotosExternosDiaD($conn, $localesVotacion)) {
        $mensajeAccion = 'Los votos externos fueron sincronizados correctamente.';
        $tipoMensajeAccion = 'success';
    } else {
        $mensajeAccion = 'No se pudieron sincronizar los votos externos.';
        $tipoMensajeAccion = 'danger';
    }
}
$resC = $conn->query("SELECT id, nombre FROM concejales ORDER BY nombre");
while ($resC && $fila = $resC->fetch_assoc()) {
    $concejales[] = $fila;
}

$fil_concejal = $_GET['f_concejal'] ?? '';
if ($fil_concejal !== '' && ctype_digit((string) $fil_concejal)) {
    $stmtUsuarios = $conn->prepare("SELECT id, nombre_completo AS nombre FROM usuarios WHERE id_concejal = ? ORDER BY nombre_completo");
    $idConcejal = (int) $fil_concejal;
    $stmtUsuarios->bind_param('i', $idConcejal);
    $stmtUsuarios->execute();
    $resU = $stmtUsuarios->get_result();
    while ($fila = $resU->fetch_assoc()) {
        $usuarios[] = $fila;
    }
}

$search = trim($_GET['buscar'] ?? '');
$fil_usuario = $_GET['f_usuario'] ?? '';
$fil_local = trim($_GET['f_local'] ?? '');
if (!in_array($fil_local, $localesVotacion, true)) {
    $fil_local = '';
}
$votosNoRegistradosPorLocal = obtenerVotosNoRegistradosPorLocal($conn, $localesVotacion);
$totalVotosNoRegistrados = $fil_local !== ''
    ? ($votosNoRegistradosPorLocal[$fil_local] ?? 0)
    : ($votosNoRegistradosPorLocal['_total'] ?? 0);
$estado = $_GET['estado'] ?? 'faltan';
$por_pagina = max(10, (int) ($_GET['por_pagina'] ?? 25));
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $por_pagina;

$estadosValidos = ['todos', 'votaron', 'faltan', 'sin_padron'];
if (!in_array($estado, $estadosValidos, true)) {
    $estado = 'faltan';
}

$baseWhere = [];
$tiposBase = '';
$paramsBase = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $baseWhere[] = "(CONCAT(COALESCE(v.nombre, ''), ' ', COALESCE(v.apellido, '')) LIKE ? OR v.cedula LIKE ? OR u.nombre_completo LIKE ? OR c.nombre LIKE ? OR pm.local LIKE ?)";
    $tiposBase .= 'sssss';
    array_push($paramsBase, $like, $like, $like, $like, $like);
}

if ($fil_concejal !== '' && ctype_digit((string) $fil_concejal)) {
    $baseWhere[] = "v.id_concejal = ?";
    $tiposBase .= 'i';
    $paramsBase[] = (int) $fil_concejal;
}

if ($fil_usuario !== '' && ctype_digit((string) $fil_usuario)) {
    $baseWhere[] = "v.id_usuario = ?";
    $tiposBase .= 'i';
    $paramsBase[] = (int) $fil_usuario;
}

$whereStatsPorLocal = $baseWhere;
$tiposStatsPorLocal = $tiposBase;
$paramsStatsPorLocal = $paramsBase;
$whereStatsPorLocal[] = "TRIM(pm.local) IN (?, ?)";
$tiposStatsPorLocal .= 'ss';
array_push($paramsStatsPorLocal, ...$localesVotacion);
if ($fil_local !== '') {
    $baseWhere[] = "TRIM(pm.local) = ?";
    $tiposBase .= 's';
    $paramsBase[] = $fil_local;
}

$joinMesaDiaD = "LEFT JOIN padron_mesa pm ON pm.cedula = v.cedula";
$joinEstadoDiaD = '';
$exprTienePadron = '0';
$exprMarcado = '0';
$exprFechaVoto = 'NULL';
$alertaConfiguracion = '';

if ($helperDisponible) {
    $joinEstadoDiaD = "LEFT JOIN " . escaparIdentificador($tablaEstadoDiaD) . " pds ON pds.cedula = v.cedula";
    $exprTienePadron = "COALESCE(pds.en_padron, 0) = 1";
    $exprMarcado = "COALESCE(pds.ya_voto, 0) = 1";
    $exprFechaVoto = "pds.fecha_voto";
} else {
    $alertaConfiguracion = 'Falta la tabla auxiliar del Dia D. Ejecuta la inicializacion para crear el indice rapido del padron.';
}

$whereStatsSql = $baseWhere ? 'WHERE ' . implode(' AND ', $baseWhere) : '';

$whereTabla = $baseWhere;
$tiposTabla = $tiposBase;
$paramsTabla = $paramsBase;

if ($estado === 'votaron' && $helperDisponible) {
    $whereTabla[] = $exprMarcado;
} elseif ($estado === 'faltan' && $helperDisponible) {
    $whereTabla[] = $exprTienePadron . " AND NOT (" . $exprMarcado . ")";
} elseif ($estado === 'sin_padron' && $helperDisponible) {
    $whereTabla[] = "NOT (" . $exprTienePadron . ")";
}

$whereTablaSql = $whereTabla ? 'WHERE ' . implode(' AND ', $whereTabla) : '';

$sqlStats = "
    SELECT
        COUNT(*) AS total_registrados,
        SUM(CASE WHEN $exprTienePadron THEN 1 ELSE 0 END) AS total_cruzados,
        SUM(CASE WHEN $exprMarcado THEN 1 ELSE 0 END) AS total_votaron,
        SUM(CASE WHEN $exprTienePadron AND NOT ($exprMarcado) THEN 1 ELSE 0 END) AS total_faltan,
        SUM(CASE WHEN NOT ($exprTienePadron) THEN 1 ELSE 0 END) AS total_sin_padron
    FROM votantes v
    LEFT JOIN usuarios u ON v.id_usuario = u.id
    LEFT JOIN concejales c ON v.id_concejal = c.id
    $joinMesaDiaD
    $joinEstadoDiaD
    $whereStatsSql
";
$stmtStats = $conn->prepare($sqlStats);
if ($stmtStats && $tiposBase !== '') {
    $stmtStats->bind_param($tiposBase, ...$paramsBase);
}
if ($stmtStats) {
    $stmtStats->execute();
    $stats = $stmtStats->get_result()->fetch_assoc();
} else {
    $stats = [
        'total_registrados' => 0,
        'total_cruzados' => 0,
        'total_votaron' => 0,
        'total_faltan' => 0,
        'total_sin_padron' => 0,
    ];
}

$totalRegistrados = (int) ($stats['total_registrados'] ?? 0);
$totalCruzados = (int) ($stats['total_cruzados'] ?? 0);
$totalVotaron = (int) ($stats['total_votaron'] ?? 0);
$totalFaltan = (int) ($stats['total_faltan'] ?? 0);
$totalSinPadron = (int) ($stats['total_sin_padron'] ?? 0);
$porcentajeAvance = $totalRegistrados > 0 ? round(($totalVotaron / $totalRegistrados) * 100, 1) : 0;

$whereStatsPorLocalSql = $whereStatsPorLocal ? 'WHERE ' . implode(' AND ', $whereStatsPorLocal) : '';
$sqlStatsPorLocal = "
    SELECT
        COALESCE(NULLIF(TRIM(pm.local), ''), 'Sin local') AS local_votacion,
        COUNT(*) AS total_registrados,
        SUM(CASE WHEN $exprMarcado THEN 1 ELSE 0 END) AS total_votaron,
        SUM(CASE WHEN $exprTienePadron AND NOT ($exprMarcado) THEN 1 ELSE 0 END) AS total_faltan,
        SUM(CASE WHEN NOT ($exprTienePadron) THEN 1 ELSE 0 END) AS total_sin_padron
    FROM votantes v
    LEFT JOIN usuarios u ON v.id_usuario = u.id
    LEFT JOIN concejales c ON v.id_concejal = c.id
    $joinMesaDiaD
    $joinEstadoDiaD
    $whereStatsPorLocalSql
    GROUP BY COALESCE(NULLIF(TRIM(pm.local), ''), 'Sin local')
    ORDER BY local_votacion
";
$stmtStatsPorLocal = $conn->prepare($sqlStatsPorLocal);
if ($stmtStatsPorLocal && $tiposStatsPorLocal !== '') {
    $stmtStatsPorLocal->bind_param($tiposStatsPorLocal, ...$paramsStatsPorLocal);
}
$statsPorLocal = [];
foreach ($localesVotacion as $localVotacion) {
    $statsPorLocal[$localVotacion] = [
        'local_votacion' => $localVotacion,
        'total_registrados' => 0,
        'total_votaron' => 0,
        'total_faltan' => 0,
        'total_sin_padron' => 0,
    ];
}
if ($stmtStatsPorLocal) {
    $stmtStatsPorLocal->execute();
    $resultadoStatsPorLocal = $stmtStatsPorLocal->get_result();
    while ($resultadoStatsPorLocal && $fila = $resultadoStatsPorLocal->fetch_assoc()) {
        $statsPorLocal[(string) $fila['local_votacion']] = $fila;
    }
}
$statsPorLocal = array_values($statsPorLocal);

$sqlCount = "
    SELECT COUNT(*) AS total
    FROM votantes v
    LEFT JOIN usuarios u ON v.id_usuario = u.id
    LEFT JOIN concejales c ON v.id_concejal = c.id
    $joinMesaDiaD
    $joinEstadoDiaD
    $whereTablaSql
";
$stmtCount = $conn->prepare($sqlCount);
if ($stmtCount && $tiposTabla !== '') {
    $stmtCount->bind_param($tiposTabla, ...$paramsTabla);
}
$totalFilas = 0;
if ($stmtCount) {
    $stmtCount->execute();
    $totalFilas = (int) ($stmtCount->get_result()->fetch_assoc()['total'] ?? 0);
}
$totalPaginas = max(1, (int) ceil($totalFilas / $por_pagina));

$columnaFechaSelect = $helperDisponible
    ? $exprFechaVoto . " AS fecha_voto_dia_d"
    : "NULL AS fecha_voto_dia_d";

$sqlTabla = "
    SELECT
        v.id,
        v.nombre,
        v.apellido,
        v.cedula,
        pm.local AS local_votacion,
        b.descripcion AS barrio,
        u.nombre_completo AS dirigente,
        c.nombre AS concejal,
        CASE WHEN $exprTienePadron THEN 1 ELSE 0 END AS tiene_padron,
        CASE WHEN $exprMarcado THEN 1 ELSE 0 END AS ya_voto,
        $columnaFechaSelect
    FROM votantes v
    LEFT JOIN usuarios u ON v.id_usuario = u.id
    LEFT JOIN concejales c ON v.id_concejal = c.id
    LEFT JOIN barrios b ON v.id_barrios = b.id_barrios
    $joinMesaDiaD
    $joinEstadoDiaD
    $whereTablaSql
    ORDER BY
        CASE WHEN $exprMarcado THEN 0 ELSE 1 END DESC,
        c.nombre ASC,
        u.nombre_completo ASC,
        v.nombre ASC
    LIMIT ? OFFSET ?
";
$stmtTabla = $conn->prepare($sqlTabla);
$tiposTablaFinal = $tiposTabla . 'ii';
$paramsTablaFinal = array_merge($paramsTabla, [$por_pagina, $offset]);
if ($stmtTabla) {
    $stmtTabla->bind_param($tiposTablaFinal, ...$paramsTablaFinal);
    $stmtTabla->execute();
    $resultadoTabla = $stmtTabla->get_result();
} else {
    $resultadoTabla = false;
}

function enlaceConEstado(array $query, string $estado): string
{
    $query['estado'] = $estado;
    $query['pagina'] = 1;
    return 'control_dia_d?' . http_build_query($query);
}

$queryActual = $_GET;
$actualizado = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control Dia D</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #031c56 0%, #0d6efd 55%, #81bbff 100%);
            font-family: 'Segoe UI', sans-serif;
        }

        .main-shell {
            background: rgba(255, 255, 255, 0.97);
            border-radius: 28px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
            margin: 24px auto;
            padding: 28px;
        }

        .hero-banner {
            background: linear-gradient(135deg, #0d6efd, #7c4dff 55%, #dc3545);
            border-radius: 24px;
            color: #fff;
            padding: 24px;
        }

        .stat-card {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 10px 24px rgba(13, 110, 253, 0.08);
            height: 100%;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .filter-panel,
        .table-panel {
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.08);
        }

        .nav-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1rem;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #d8e4ff;
            color: #2957c8;
            background: #f6f9ff;
        }

        .nav-pill.active {
            background: #2957c8;
            color: #fff;
            border-color: #2957c8;
        }

        .status-badge {
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .table thead th {
            background: #0b4db3;
            color: #fff;
            position: sticky;
            top: 0;
            z-index: 5;
        }
    </style>
</head>
<body>
<main class="container py-4 py-md-5">
    <section class="main-shell">
        <div class="hero-banner mb-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="h2 mb-2">Control Dia D</h1>
                    <p class="mb-1">Cruce en tiempo real entre tus votantes registrados y el padron de quienes ya marcaron su voto.</p>
                    <small class="opacity-75">Actualizado a las <strong><?= htmlspecialchars($actualizado) ?></strong>. Recarga automatica en <span id="countdown">30</span>s.</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="dia_d" class="btn btn-light">Ir a Dia D</a>
                    <a href="control_dia_d?logout=1" class="btn btn-outline-light">Cerrar acceso</a>
                </div>
            </div>
        </div>

        <?php if ($mensajeAccion !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($tipoMensajeAccion) ?>"><?= htmlspecialchars($mensajeAccion) ?></div>
        <?php endif; ?>

        <section class="filter-panel p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <div class="text-muted small mb-1">Marcacion publica del Dia D</div>
                    <div class="fw-semibold <?= $marcacionHabilitada ? 'text-success' : 'text-danger' ?>">
                        <?= $marcacionHabilitada ? 'Habilitada' : 'Deshabilitada' ?>
                    </div>
                </div>
                <form method="POST" class="d-flex gap-2 flex-wrap m-0">
                    <input type="hidden" name="accion_panel" value="toggle_marcacion">
                    <input type="hidden" name="nuevo_estado" value="<?= $marcacionHabilitada ? '0' : '1' ?>">
                    <button type="submit" class="btn <?= $marcacionHabilitada ? 'btn-outline-danger' : 'btn-success' ?>">
                        <?= $marcacionHabilitada ? 'Deshabilitar marcacion' : 'Habilitar marcacion' ?>
                    </button>
                </form>
                <form method="POST" class="d-flex gap-2 flex-wrap m-0">
                    <input type="hidden" name="accion_panel" value="sincronizar_votos_externos">
                    <button type="submit" class="btn btn-outline-primary">Sincronizar votos externos</button>
                </form>
            </div>
        </section>

        <?php if ($alertaConfiguracion !== ''): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div><?= htmlspecialchars($alertaConfiguracion) ?></div>
                <a href="inicializar_dia_d" class="btn btn-sm btn-warning">Inicializar columnas Dia D</a>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="text-muted small mb-2">Total registrados</div>
                        <div class="stat-value text-primary"><?= $totalRegistrados ?></div>
                        <div class="small text-muted mt-2">Base general segun filtros aplicados</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="text-muted small mb-2">Ya votaron</div>
                        <div class="stat-value text-success"><?= $totalVotaron ?></div>
                        <div class="small text-muted mt-2">Avance: <?= number_format($porcentajeAvance, 1) ?>%</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="text-muted small mb-2">Faltan votar</div>
                        <div class="stat-value text-danger"><?= $totalFaltan ?></div>
                        <div class="small text-muted mt-2">Pendientes dentro del padron cruzado</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="text-muted small mb-2">Sin cruce en padron</div>
                        <div class="stat-value text-warning"><?= $totalSinPadron ?></div>
                        <div class="small text-muted mt-2">Coincidencias activas: <?= $totalCruzados ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="text-muted small mb-2">Votos fuera de votantes</div>
                        <div class="stat-value text-info"><?= $totalVotosNoRegistrados ?></div>
                        <div class="small text-muted mt-2">Marcados en padron sin registro previo</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="progress mb-4" style="height: 14px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, max(0, $porcentajeAvance)) ?>%;"></div>
        </div>

        <section class="table-panel p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="h4 mb-1">Estadisticas por local de votacion</h2>
                    <p class="text-muted mb-0">Avance por local segun los filtros de busqueda, concejal y dirigente.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Local</th>
                            <th>Registrados</th>
                            <th>Ya votaron</th>
                            <th>Faltan votar</th>
                            <th>Sin cruce</th>
                            <th>Fuera de votantes</th>
                            <th>Avance</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($statsPorLocal)): ?>
                            <?php foreach ($statsPorLocal as $estadisticaLocal): ?>
                                <?php
                                    $totalLocal = (int) ($estadisticaLocal['total_registrados'] ?? 0);
                                    $votaronLocal = (int) ($estadisticaLocal['total_votaron'] ?? 0);
                                    $avanceLocal = $totalLocal > 0 ? round(($votaronLocal / $totalLocal) * 100, 1) : 0;
                                    $nombreLocal = (string) ($estadisticaLocal['local_votacion'] ?? 'Sin local');
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($nombreLocal) ?></td>
                                    <td><?= $totalLocal ?></td>
                                    <td class="text-success fw-semibold"><?= $votaronLocal ?></td>
                                    <td class="text-danger fw-semibold"><?= (int) ($estadisticaLocal['total_faltan'] ?? 0) ?></td>
                                    <td class="text-warning fw-semibold"><?= (int) ($estadisticaLocal['total_sin_padron'] ?? 0) ?></td>
                                    <td class="text-info fw-semibold"><?= (int) ($votosNoRegistradosPorLocal[$nombreLocal] ?? 0) ?></td>
                                    <td><?= number_format($avanceLocal, 1) ?>%</td>
                                    <td>
                                        <?php if ($nombreLocal !== 'Sin local'): ?>
                                            <a href="?<?= http_build_query(array_merge($queryActual, ['f_local' => $nombreLocal, 'pagina' => 1])) ?>" class="btn btn-sm btn-outline-primary">Filtrar</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No hay datos por local para los filtros aplicados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="filter-panel p-4 mb-4">
            <form method="GET" id="formFiltros">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Buscar</label>
                        <input type="text" name="buscar" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Nombre, cedula, dirigente, concejal o local">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Concejal</label>
                        <select name="f_concejal" id="f_concejal" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach ($concejales as $concejal): ?>
                                <option value="<?= $concejal['id'] ?>" <?= $fil_concejal == $concejal['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($concejal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold">Dirigente</label>
                        <select name="f_usuario" id="f_usuario" class="form-select" data-selected="<?= htmlspecialchars((string) $fil_usuario) ?>">
                            <option value="">-- Todos --</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>" <?= $fil_usuario == $usuario['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($usuario['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold">Local de votacion</label>
                        <select name="f_local" class="form-select">
                            <option value="">-- Todos --</option>
                            <?php foreach ($localesVotacion as $localVotacion): ?>
                                <option value="<?= htmlspecialchars($localVotacion) ?>" <?= $fil_local === $localVotacion ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($localVotacion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1">
                        <label class="form-label fw-semibold">Pag.</label>
                        <select name="por_pagina" class="form-select">
                            <?php foreach ([10, 25, 50, 100] as $valorPagina): ?>
                                <option value="<?= $valorPagina ?>" <?= $por_pagina === $valorPagina ? 'selected' : '' ?>><?= $valorPagina ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1">
                        <label class="form-label fw-semibold">Vista</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($estado)) ?>" readonly>
                    </div>
                </div>

                <input type="hidden" name="estado" value="<?= htmlspecialchars($estado) ?>">

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                    <a href="control_dia_d" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </section>

        <div class="d-flex gap-2 flex-wrap mb-4">
            <a href="<?= htmlspecialchars(enlaceConEstado($queryActual, 'faltan')) ?>" class="nav-pill <?= $estado === 'faltan' ? 'active' : '' ?>">Faltan votar</a>
            <a href="<?= htmlspecialchars(enlaceConEstado($queryActual, 'votaron')) ?>" class="nav-pill <?= $estado === 'votaron' ? 'active' : '' ?>">Ya votaron</a>
            <a href="<?= htmlspecialchars(enlaceConEstado($queryActual, 'sin_padron')) ?>" class="nav-pill <?= $estado === 'sin_padron' ? 'active' : '' ?>">Sin cruce en padron</a>
            <a href="<?= htmlspecialchars(enlaceConEstado($queryActual, 'todos')) ?>" class="nav-pill <?= $estado === 'todos' ? 'active' : '' ?>">Todos</a>
        </div>

        <section class="table-panel p-4">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
                <div>
                    <h2 class="h4 mb-1">Listado Dia D</h2>
                    <p class="text-muted mb-0"><?= $totalFilas ?> resultados en la vista actual</p>
                </div>
            </div>

            <div class="table-responsive" style="max-height: 620px;">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Votante</th>
                            <th>Cedula</th>
                            <th>Estado</th>
                            <th>Dirigente</th>
                            <th>Concejal</th>
                            <th>Local</th>
                            <th>Barrio</th>
                            <th>Marca</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultadoTabla && $resultadoTabla->num_rows > 0): ?>
                            <?php $i = $offset + 1; while ($fila = $resultadoTabla->fetch_assoc()): ?>
                                <?php
                                    $estadoTexto = 'Sin cruce';
                                    $estadoClase = 'bg-warning-subtle text-warning-emphasis';

                                    if ((int) $fila['ya_voto'] === 1) {
                                        $estadoTexto = 'Ya voto';
                                        $estadoClase = 'bg-success-subtle text-success';
                                    } elseif ((int) $fila['tiene_padron'] === 1) {
                                        $estadoTexto = 'Falta votar';
                                        $estadoClase = 'bg-danger-subtle text-danger';
                                    }
                                ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= $i++ ?></td>
                                    <td><?= htmlspecialchars(trim($fila['nombre'] . ' ' . $fila['apellido'])) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($fila['cedula']) ?></td>
                                    <td><span class="status-badge <?= $estadoClase ?>"><?= htmlspecialchars($estadoTexto) ?></span></td>
                                    <td><?= htmlspecialchars($fila['dirigente'] ?? 'Sin dirigente') ?></td>
                                    <td><?= htmlspecialchars($fila['concejal'] ?? 'Sin concejal') ?></td>
                                    <td><?= htmlspecialchars($fila['local_votacion'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($fila['barrio'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars((string) ($fila['fecha_voto_dia_d'] ?? '-')) ?></td>
                                    <td>
                                        <a href="dia_d?cedula=<?= urlencode($fila['cedula']) ?>" class="btn btn-sm btn-outline-primary">Ver Dia D</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">No se encontraron registros para esta vista.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($queryActual, ['pagina' => $pagina - 1])) ?>">«</a>
                        </li>
                        <?php
                            $rango = 2;
                            for ($p = 1; $p <= $totalPaginas; $p++) {
                                if ($p === 1 || $p === $totalPaginas || ($p >= $pagina - $rango && $p <= $pagina + $rango)) {
                                    $clase = $p === $pagina ? 'active' : '';
                                    echo '<li class="page-item ' . $clase . '"><a class="page-link" href="?' . http_build_query(array_merge($queryActual, ['pagina' => $p])) . '">' . $p . '</a></li>';
                                } elseif ($p === $pagina - $rango - 1 || $p === $pagina + $rango + 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                        ?>
                        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($queryActual, ['pagina' => $pagina + 1])) ?>">»</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </section>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectConcejal = document.getElementById('f_concejal');
    const selectUsuario = document.getElementById('f_usuario');
    const usuarioSeleccionado = selectUsuario ? selectUsuario.dataset.selected : '';
    const countdown = document.getElementById('countdown');
    let segundos = 30;
    let actualizando = false;
    let intervaloConteo = null;

    function actualizarConteo() {
        if (!countdown || actualizando) return;
        countdown.textContent = String(segundos);
        if (segundos <= 0) {
            actualizando = true;
            countdown.textContent = 'actualizando...';
            if (intervaloConteo) {
                window.clearInterval(intervaloConteo);
            }
            window.location.reload();
            return;
        }
        segundos -= 1;
    }

    function cargarUsuarios(idConcejal) {
        if (!selectUsuario) return;

        selectUsuario.innerHTML = '<option value="">Cargando...</option>';
        fetch(`ajax_usuarios_por_concejal.php?id_concejal=${idConcejal}`)
            .then(resp => resp.json())
            .then(data => {
                selectUsuario.innerHTML = '<option value="">-- Todos --</option>';
                data.forEach(usuario => {
                    const option = document.createElement('option');
                    option.value = usuario.id;
                    option.textContent = usuario.nombre;
                    if (usuarioSeleccionado && String(usuario.id) === String(usuarioSeleccionado)) {
                        option.selected = true;
                    }
                    selectUsuario.appendChild(option);
                });
            })
            .catch(() => {
                selectUsuario.innerHTML = '<option value="">Error al cargar</option>';
            });
    }

    if (selectConcejal) {
        selectConcejal.addEventListener('change', () => {
            if (selectConcejal.value) {
                cargarUsuarios(selectConcejal.value);
            } else if (selectUsuario) {
                selectUsuario.innerHTML = '<option value="">-- Todos --</option>';
            }
        });

        if (selectConcejal.value) {
            cargarUsuarios(selectConcejal.value);
        }
    }

    actualizarConteo();
    intervaloConteo = window.setInterval(actualizarConteo, 1000);
});
</script>
</body>
</html>

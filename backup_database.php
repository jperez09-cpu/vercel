<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este respaldo solo puede ejecutarse por consola.');
}

require_once __DIR__ . '/conexion.php';

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true)) {
    fwrite(STDERR, "No se pudo crear la carpeta de backups.\n");
    exit(1);
}

$backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.sql';
$handle = fopen($backupFile, 'wb');
if (!$handle) {
    fwrite(STDERR, "No se pudo crear el archivo de backup.\n");
    exit(1);
}

function escribirLinea($handle, string $linea = ''): void
{
    fwrite($handle, $linea . "\n");
}

function valorSql(mysqli $conn, $valor): string
{
    if ($valor === null) {
        return 'NULL';
    }

    return "'" . $conn->real_escape_string((string) $valor) . "'";
}

escribirLinea($handle, '-- Backup generado: ' . date('Y-m-d H:i:s'));
escribirLinea($handle, 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
escribirLinea($handle, 'SET time_zone = "+00:00";');
escribirLinea($handle, 'SET FOREIGN_KEY_CHECKS = 0;');
escribirLinea($handle);

$tablas = [];
$resultadoTablas = $conn->query('SHOW TABLES');
if (!$resultadoTablas) {
    fwrite(STDERR, "No se pudo listar tablas: {$conn->error}\n");
    exit(1);
}

while ($fila = $resultadoTablas->fetch_array(MYSQLI_NUM)) {
    $tablas[] = $fila[0];
}

foreach ($tablas as $tabla) {
    $tablaEscapada = '`' . str_replace('`', '``', $tabla) . '`';

    escribirLinea($handle, '-- --------------------------------------------------------');
    escribirLinea($handle, '-- Tabla ' . $tabla);
    escribirLinea($handle, 'DROP TABLE IF EXISTS ' . $tablaEscapada . ';');

    $resultadoCreate = $conn->query('SHOW CREATE TABLE ' . $tablaEscapada);
    if (!$resultadoCreate) {
        fwrite(STDERR, "No se pudo leer estructura de {$tabla}: {$conn->error}\n");
        exit(1);
    }

    $create = $resultadoCreate->fetch_assoc();
    escribirLinea($handle, $create['Create Table'] . ';');
    escribirLinea($handle);

    $resultadoDatos = $conn->query('SELECT * FROM ' . $tablaEscapada);
    if (!$resultadoDatos) {
        fwrite(STDERR, "No se pudo leer datos de {$tabla}: {$conn->error}\n");
        exit(1);
    }

    while ($fila = $resultadoDatos->fetch_assoc()) {
        $columnas = array_map(
            static fn($columna) => '`' . str_replace('`', '``', $columna) . '`',
            array_keys($fila)
        );
        $valores = array_map(fn($valor) => valorSql($conn, $valor), array_values($fila));

        escribirLinea(
            $handle,
            'INSERT INTO ' . $tablaEscapada .
            ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ');'
        );
    }

    escribirLinea($handle);
}

escribirLinea($handle, 'SET FOREIGN_KEY_CHECKS = 1;');
fclose($handle);

echo $backupFile . PHP_EOL;

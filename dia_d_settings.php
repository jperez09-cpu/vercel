<?php

function diaDAsegurarTablaConfig(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `dia_d_config` (
            `clave` VARCHAR(80) NOT NULL,
            `valor` VARCHAR(255) NOT NULL,
            `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`clave`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    @$conn->query($sql);
    @$conn->query("
        INSERT INTO `dia_d_config` (`clave`, `valor`)
        VALUES ('marcacion_habilitada', '1')
        ON DUPLICATE KEY UPDATE `clave` = `clave`
    ");
}

function diaDMarcacionHabilitada(mysqli $conn): bool
{
    diaDAsegurarTablaConfig($conn);

    $sql = "SELECT valor FROM `dia_d_config` WHERE clave = 'marcacion_habilitada' LIMIT 1";
    $resultado = $conn->query($sql);
    $fila = $resultado ? $resultado->fetch_assoc() : null;

    return !isset($fila['valor']) || $fila['valor'] === '1';
}

function diaDEstablecerMarcacion(mysqli $conn, bool $habilitada): bool
{
    diaDAsegurarTablaConfig($conn);

    $valor = $habilitada ? '1' : '0';
    $sql = "
        INSERT INTO `dia_d_config` (`clave`, `valor`)
        VALUES ('marcacion_habilitada', ?)
        ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $valor);
    return $stmt->execute();
}


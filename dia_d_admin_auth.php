<?php

if (!defined('DIA_D_ADMIN_PASSWORD')) {
    // Cambia esta clave por una propia antes de publicar en produccion.
    define('DIA_D_ADMIN_PASSWORD', 'CambiarDiaD2026');
}

function diaDAdminTieneAcceso(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (($_SESSION['rol'] ?? '') === 'admin') {
        return true;
    }

    return !empty($_SESSION['dia_d_admin_access']);
}

function diaDAdminIntentarAcceso(?string $password): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (!is_string($password)) {
        return false;
    }

    if (!hash_equals(DIA_D_ADMIN_PASSWORD, $password)) {
        return false;
    }

    $_SESSION['dia_d_admin_access'] = true;
    return true;
}

function diaDAdminCerrarAcceso(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['dia_d_admin_access']);
    }
}


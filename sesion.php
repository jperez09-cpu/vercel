<?php

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '0');

    session_start();
}

function borrarCookiesViejasSesion(): void
{
    $nombreSesion = session_name();
    $dominios = ['', $_SERVER['HTTP_HOST'] ?? '', '.padronjas.unaux.com', 'padronjas.unaux.com'];
    $rutas = ['/', dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/'];

    foreach (array_unique($dominios) as $dominio) {
        foreach (array_unique($rutas) as $ruta) {
            setcookie($nombreSesion, '', time() - 42000, $ruta, $dominio, false, true);
            setcookie($nombreSesion, '', time() - 42000, $ruta, $dominio, true, true);
        }
    }
}

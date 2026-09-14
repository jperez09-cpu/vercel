<?php

const AUTH_COOKIE_NAME = 'jas_auth';

function iniciarSesionSegura(): void
{
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', esHttps() ? '1' : '0');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    restaurarSesionDesdeCookie();
}

function iniciarSesionUsuario(array $usuario): void
{
    iniciarSesionSegura();

    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario'] = $usuario['nombre_usuario'];
    $_SESSION['nombre_usuario'] = $usuario['nombre_completo'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

    guardarCookieAutenticacion($usuario);
}

function cerrarSesionSegura(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    session_destroy();
    borrarCookie(AUTH_COOKIE_NAME);
}

function borrarCookiesViejasSesion(): void
{
    $nombreSesion = session_name();
    $dominios = ['', $_SERVER['HTTP_HOST'] ?? '', '.padronjas.unaux.com', 'padronjas.unaux.com', '.vercel.app', 'padronjas.vercel.app'];
    $rutas = ['/', dirname($_SERVER['SCRIPT_NAME'] ?? '/') ?: '/'];

    foreach (array_unique($dominios) as $dominio) {
        foreach (array_unique($rutas) as $ruta) {
            setcookie($nombreSesion, '', time() - 42000, $ruta, $dominio, false, true);
            setcookie($nombreSesion, '', time() - 42000, $ruta, $dominio, true, true);
            setcookie(AUTH_COOKIE_NAME, '', time() - 42000, $ruta, $dominio, false, true);
            setcookie(AUTH_COOKIE_NAME, '', time() - 42000, $ruta, $dominio, true, true);
        }
    }
}

function restaurarSesionDesdeCookie(): void
{
    if (!empty($_SESSION['usuario_id']) || empty($_COOKIE[AUTH_COOKIE_NAME])) {
        return;
    }

    $partes = explode('.', $_COOKIE[AUTH_COOKIE_NAME], 2);
    if (count($partes) !== 2) {
        borrarCookie(AUTH_COOKIE_NAME);
        return;
    }

    [$payloadBase64, $firma] = $partes;
    $firmaEsperada = hash_hmac('sha256', $payloadBase64, secretoAutenticacion());
    if (!hash_equals($firmaEsperada, $firma)) {
        borrarCookie(AUTH_COOKIE_NAME);
        return;
    }

    $payloadCodificado = strtr($payloadBase64, '-_', '+/');
    $payloadCodificado .= str_repeat('=', (4 - strlen($payloadCodificado) % 4) % 4);
    $payloadJson = base64_decode($payloadCodificado, true);
    $payload = json_decode((string) $payloadJson, true);
    if (!is_array($payload) || (($payload['exp'] ?? 0) < time())) {
        borrarCookie(AUTH_COOKIE_NAME);
        return;
    }

    $_SESSION['usuario_id'] = $payload['id'];
    $_SESSION['usuario'] = $payload['usuario'];
    $_SESSION['nombre_usuario'] = $payload['nombre'];
    $_SESSION['rol'] = $payload['rol'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function guardarCookieAutenticacion(array $usuario): void
{
    $payload = [
        'id' => $usuario['id'],
        'usuario' => $usuario['nombre_usuario'],
        'nombre' => $usuario['nombre_completo'],
        'rol' => $usuario['rol'],
        'exp' => time() + 86400,
    ];
    $payloadBase64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $firma = hash_hmac('sha256', $payloadBase64, secretoAutenticacion());

    setcookie(AUTH_COOKIE_NAME, $payloadBase64 . '.' . $firma, [
        'expires' => $payload['exp'],
        'path' => '/',
        'secure' => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function borrarCookie(string $nombre): void
{
    setcookie($nombre, '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function secretoAutenticacion(): string
{
    return getenv('AUTH_SECRET') ?: getenv('DB_PASS') ?: hash('sha256', __DIR__);
}

function esHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

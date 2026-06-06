<?php
header('Content-Type: application/manifest+json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
// Eliminamos cualquier buffer de salida previo para evitar espacios en blanco
ob_clean(); 
echo json_encode([
    "id" => "/index",
    "name" => "Padrón J. Augusto Saldívar",
    "short_name" => "VotantesJAS",
    "description" => "Sistema de gestión de votantes JAS",
    "start_url" => "/index.php",
    "display" => "standalone",
    "background_color" => "#ffffff",
    "theme_color" => "#0033cc",
    "orientation" => "portrait",
    "icons" => [
        [
            "src" => "icons/icon-192.png",
            "sizes" => "192x192",
            "type" => "image/png",
            "purpose" => "any"
        ],
        [
            "src" => "icons/logo_310x310.png",
            "sizes" => "310x310",
            "type" => "image/png",
            "purpose" => "maskable"
        ],
        [
            "src" => "icons/icon-512.png",
            "sizes" => "512x512",
            "type" => "image/png",
            "purpose" => "any"
        ]
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
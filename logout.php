<?php
// logout.php
require_once 'sesion.php';
cerrarSesionSegura();
header("Location: index");
exit;
?>

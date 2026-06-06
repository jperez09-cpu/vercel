<?php
$clave = '4370551';
$hash = password_hash($clave, PASSWORD_DEFAULT);
echo $hash;
?>
<?php
// Local equivalent of the Vercel route allowlist. Used by tests only.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~^/assets/(app\.css|theme\.css|app\.js|theme\.js|favicon\.svg|modelo-clientes\.csv)$~D',$path))return false;
require dirname(__DIR__).'/api/index.php';

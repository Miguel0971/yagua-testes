<?php
declare(strict_types=1);
// Single public PHP entrypoint. Never execute installation/import scripts via HTTP.
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(!in_array($path,['/','/index.php','/api/index.php'],true)){
    http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Página não encontrada.';exit;
}
$_SERVER['SCRIPT_NAME']='/index.php';
require dirname(__DIR__).'/index.php';

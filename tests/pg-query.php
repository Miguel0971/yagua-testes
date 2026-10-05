<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/core.php';
$data=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$s=sql($data['sql'],$data['params']??[]);
echo json_encode(($data['mode']??'scalar')==='rows'?$s->fetchAll():$s->fetchColumn(),JSON_THROW_ON_ERROR);

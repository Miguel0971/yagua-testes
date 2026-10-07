<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/cli.php';
try{
    $pdo=requireCloud();
    if(!cloudSchemaExists($pdo))throw new RuntimeException('Banco ainda não instalado.');
    $pdo->exec(file_get_contents(__DIR__.'/upgrade-list.sql'));
    echo "Banco atualizado. Clientes, contatos, usuários e histórico preservados.\n";
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}

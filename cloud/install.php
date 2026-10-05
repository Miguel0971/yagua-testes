<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/cli.php';
try{
    $pdo=requireCloud();$password=promptInitialPassword();
    transaction(function()use($pdo,$password){
        if(cloudSchemaExists($pdo))throw new RuntimeException('Banco já preparado. Instalação cancelada sem alterar dados.');
        createCloudSchema($pdo);
        sql("INSERT INTO users(nome,login,passwordHash,role,mustChangePassword) VALUES ('Administrador','admin',?,'ADMIN',1)",[password_hash($password,PASSWORD_BCRYPT)]);
    });
    fwrite(STDOUT,"PostgreSQL preparado. Login: admin. Altere a senha no primeiro acesso.\n");
}catch(Throwable $e){fwrite(STDERR,'Instalação não concluída: '.$e->getMessage()."\n");exit(1);}

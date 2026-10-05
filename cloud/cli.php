<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/core.php';
function requireCloud(): PDO {
    if(!isPostgres())throw new RuntimeException('Defina DATABASE_URL antes de executar.');
    return db();
}
function cloudSchemaExists(PDO $pdo): bool {
    return (bool)$pdo->query("SELECT to_regclass('public.schema_meta') IS NOT NULL")->fetchColumn();
}
function createCloudSchema(PDO $pdo): void {
    // Caller owns the transaction. A partial/unrelated schema is never overwritten.
    $pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
}
function promptInitialPassword(): string {
    $password=getenv('YAGUA_CS_INITIAL_PASSWORD');
    if(!$password){
        fwrite(STDOUT,'Senha inicial do admin (mínimo 8 caracteres; não será exibida): ');
        $mode=function_exists('shell_exec') && function_exists('stream_isatty') && stream_isatty(STDIN)?trim((string)shell_exec('stty -g')):'';
        if($mode)shell_exec('stty -echo');
        try{$password=rtrim((string)fgets(STDIN),"\r\n");}finally{if($mode)shell_exec('stty '.escapeshellarg($mode));}
        fwrite(STDOUT,"\n");
    }
    if(strlen($password)<8||strlen($password)>72)throw new RuntimeException('Use de 8 a 72 bytes para a senha inicial.');
    return $password;
}

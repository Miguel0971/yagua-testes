<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/core.php';
try {
    if(isPostgres() || getenv('VERCEL'))throw new RuntimeException('Para PostgreSQL, use cloud/install.php ou cloud/import-sqlite.php.');
    $path=dbPath();
    $public=realpath(dirname(__DIR__)); $parent=realpath(dirname($path));
    if(!$parent || !is_writable($parent)) throw new RuntimeException('Crie antes uma pasta privada gravável para o banco.');
    if($parent===$public || str_starts_with($parent,$public.DIRECTORY_SEPARATOR)) throw new RuntimeException('O banco precisa ficar fora da pasta pública do projeto.');
    if(is_file($path)) throw new RuntimeException('Banco já existe. A instalação nunca sobrescreve dados.');
    $password=getenv('YAGUA_CS_INITIAL_PASSWORD');
    if(!$password) {
        fwrite(STDOUT,"Senha inicial do admin (não será exibida): ");
        $hidden=function_exists('shell_exec') && DIRECTORY_SEPARATOR==='/' && function_exists('stream_isatty') && stream_isatty(STDIN);
        $mode=$hidden?trim((string)shell_exec('stty -g')):'';
        if($mode) shell_exec('stty -echo');
        try { $password=rtrim((string)fgets(STDIN),"\r\n"); } finally { if($mode) shell_exec('stty '.escapeshellarg($mode)); }
        fwrite(STDOUT,"\n");
    }
    if(strlen($password)<4 || strlen($password)>72) throw new RuntimeException('Informe uma senha inicial de 4 a 72 bytes.');
    umask(0077);
    $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(file_get_contents(__DIR__.'/schema.sql'));
    $s=$pdo->prepare("INSERT INTO users(nome,login,passwordHash,role,mustChangePassword) VALUES ('Administrador','admin',?,'ADMIN',1)");
    $s->execute([password_hash($password,PASSWORD_DEFAULT)]);
    chmod($path,0600);
    fwrite(STDOUT,"Instalação concluída. Login: admin. Troca de senha obrigatória no primeiro acesso.\n");
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }

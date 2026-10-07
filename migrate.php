<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
try{
    $path=dbPath();if(!is_file($path))throw new RuntimeException('Banco não encontrado. Para uma instalação nova, execute install.php.');
    $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout=10000;');
    $version=(int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if($version===3){fwrite(STDOUT,"Banco já atualizado. Nenhuma alteração necessária.\n");exit;}
    if(!in_array($version,[1,2],true))throw new RuntimeException('Versão de banco não reconhecida. Atualização interrompida sem alterar dados.');
    if(version_compare($pdo->query('SELECT sqlite_version()')->fetchColumn(),'3.27.0','<'))throw new RuntimeException('O backup automático exige SQLite 3.27+. Atualização interrompida sem alterar dados.');
    umask(0077);$backup=$path.'.backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    $pdo->exec('VACUUM INTO '.$pdo->quote($backup));chmod($backup,0600);
    $pdo->exec('BEGIN IMMEDIATE');
    try{
        $columns=array_column($pdo->query('PRAGMA table_info(technicians)')->fetchAll(PDO::FETCH_ASSOC),'name');
        foreach(['whatsapp','email'] as $column)if(!in_array($column,$columns,true))$pdo->exec("ALTER TABLE technicians ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
        $pdo->exec(file_get_contents(__DIR__.'/statuses.sql'));
        $pdo->exec("ALTER TABLE users ADD COLUMN colorTheme TEXT NOT NULL DEFAULT 'ocean'");
        $pdo->exec('ALTER TABLE clients ADD COLUMN boardStatus TEXT REFERENCES board_statuses(key)');
        $pdo->exec('PRAGMA user_version=3; COMMIT;');
    }catch(Throwable $e){$pdo->exec('ROLLBACK');throw $e;}
    fwrite(STDOUT,"Atualização concluída. Clientes, contas, contatos e tarefas preservados.\nBackup: $backup\n");
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}

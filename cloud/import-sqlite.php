<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/cli.php';
try{
    $path=$argv[1]??'';
    if(!$path || !is_file($path))throw new RuntimeException('Uso: php cloud/import-sqlite.php /caminho/privado/yagua.sqlite');
    $source=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $source->exec('PRAGMA query_only=ON; PRAGMA busy_timeout=5000; BEGIN');
    $version=(int)$source->query('PRAGMA user_version')->fetchColumn();
    if(!in_array($version,[1,2,3],true))throw new RuntimeException('Use o banco do Yágua CS (versão 1 ou 2).');
    if($source->query('PRAGMA integrity_check')->fetchColumn()!=='ok' || $source->query('PRAGMA foreign_key_check')->fetch())throw new RuntimeException('Banco de origem com inconsistências.');
    $pdo=requireCloud();$counts=[];
    transaction(function()use($source,$pdo,&$counts){
        if(cloudSchemaExists($pdo))throw new RuntimeException('O destino já está preparado. Use um banco PostgreSQL vazio; nenhum dado será sobrescrito.');
        createCloudSchema($pdo);
        if($source->query("SELECT name FROM sqlite_master WHERE type='table' AND name='board_statuses'")->fetchColumn()){
            $pdo->exec('DELETE FROM board_statuses');
            foreach($source->query('SELECT * FROM board_statuses') as $stage)sql('INSERT INTO board_statuses(key,label,color,sortOrder,isClosed,builtin) VALUES(?,?,?,?,?,?)',array_values($stage));
        }
        $tables=['users','clients','technicians','client_technicians','updates','tasks'];
        foreach($tables as $table){
            $columns=$source->query('PRAGMA table_info('.$table.')')->fetchAll();
            $names=array_column($columns,'name');
            if(!$names)throw new RuntimeException('Tabela ausente: '.$table);
            foreach($names as $name)if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$name))throw new RuntimeException('Coluna inválida.');
            $statement=$pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_map(fn($v)=>'"'.$v.'"',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')');
            $counts[$table]=0;
            foreach($source->query('SELECT * FROM '.$table) as $row){$statement->execute(array_values($row));$counts[$table]++;}
        }
        if(!$pdo->query("SELECT COUNT(*) FROM users WHERE role='ADMIN'")->fetchColumn())throw new RuntimeException('A origem não contém administrador.');
        foreach(['users','clients','technicians','updates','tasks','login_attempts'] as $table){
            $pdo->exec("SELECT setval(pg_get_serial_sequence('$table','id'), COALESCE((SELECT MAX(id) FROM $table),1), EXISTS(SELECT 1 FROM $table))");
        }
    });
    $source->exec('ROLLBACK');
    foreach($counts as $table=>$count)fwrite(STDOUT,$table.': '.$count." registros\n");
    fwrite(STDOUT,"Importação concluída. IDs, senhas com hash, autores e histórico preservados. Entre com seu login atual.\n");
}catch(Throwable $e){fwrite(STDERR,'Importação não concluída: '.$e->getMessage()."\n");exit(1);}

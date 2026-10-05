<?php
declare(strict_types=1);
function isPostgres(): bool {return (string)getenv('DATABASE_URL')!=='';}
function postgresConfig(string $connectionUrl): array {
    $url=parse_url($connectionUrl);
    if(!$url || !in_array($url['scheme']??'', ['postgres','postgresql'],true) || empty($url['host']) || empty($url['path']) || empty($url['user']))throw new RuntimeException('DATABASE_URL PostgreSQL inválida.');
    $host=$url['host'];$name=rawurldecode(ltrim($url['path'],'/'));
    if(preg_match('/[;\s]/',$host.$name))throw new RuntimeException('Host ou banco inválido.');
    parse_str($url['query']??'', $query);$ssl=$query['sslmode']??'require';
    if(!in_array($ssl,['require','verify-ca','verify-full','disable'],true) || (getenv('VERCEL') && $ssl==='disable'))throw new RuntimeException('Use sslmode=require para conexão externa.');
    // Older libpq builds in the Vercel runtime may not send TLS SNI.
    // Pass Neon's routing label explicitly; retain -pooler when present.
    $options=$query['options']??'';
    if(!is_string($options) || ($options!=='' && !preg_match('/^endpoint=[a-zA-Z0-9-]+$/D',$options)))throw new RuntimeException('Parâmetro options inválido: use somente endpoint=IDENTIFICADOR.');
    if($options==='' && preg_match('/^(ep-[a-z0-9-]+)\.(?:[a-z0-9-]+\.)*neon\.(?:tech|build)$/iD',$host,$match))$options='endpoint='.$match[1];
    $dsn='pgsql:host='.$host.';port='.(int)($url['port']??5432).';dbname='.$name.';sslmode='.$ssl.';connect_timeout=10';
    if($options!=='')$dsn.=';options='.$options;
    return [$dsn,rawurldecode($url['user']),rawurldecode($url['pass']??'')];
}
function postgresConnection(): PDO {
    [$dsn,$username,$password]=postgresConfig((string)getenv('DATABASE_URL'));
    $pdo=new PDO($dsn,$username,$password,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>true,PDO::ATTR_PERSISTENT=>false
    ]);
    $pdo->exec("SET TIME ZONE 'UTC'; SET search_path TO public; SET statement_timeout TO '20s'; SET lock_timeout TO '10s'");
    return $pdo;
}
/** Adapt only known SQL syntax, never interpolate user values. Keep string literals intact. */
function postgresSql(string $sql): string {
    $parts=preg_split("/('(?:''|[^'])*')/",$sql,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($parts as $i=>$part){
        if($i%2)continue;
        $part=preg_replace('/\blogin=\? COLLATE NOCASE\b/','lower(login)=lower(?)',$part);
        $parts[$i]=preg_replace_callback('/\b[A-Za-z_][A-Za-z_0-9]*\b/',fn($m)=>preg_match('/[a-z][A-Z]/',$m[0])?'"'.$m[0].'"':$m[0],$part);
    }
    return implode('',$parts);
}

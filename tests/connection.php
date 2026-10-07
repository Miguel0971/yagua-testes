<?php
require dirname(__DIR__).'/cloud/database.php';
function verify(bool $condition,string $description):void {if(!$condition)throw new RuntimeException($description);}
$cases=[
 ['ep-sample-123.us-east-2.aws.neon.tech','endpoint=ep-sample-123'],
 ['ep-sample-123-pooler.us-east-2.aws.neon.tech','endpoint=ep-sample-123-pooler'],
 ['ep-sample-123.c-2.us-east-2.aws.neon.tech','endpoint=ep-sample-123'],
 ['ep-sample-123.eu-central-1.aws.neon.build','endpoint=ep-sample-123'],
 ['localhost',null],['db.example.com',null],['ep-sample-123.neon.tech.attacker.example',null]
];
foreach($cases as [$host,$expected]){
 [$dsn,$user,$pass]=postgresConfig('postgresql://user:encoded%40pass@'.$host.'/app?sslmode=require');
 verify($expected===null?!str_contains($dsn,';options='):str_contains($dsn,';options='.$expected),'Neon routing: '.$host);
 verify($user==='user' && $pass==='encoded@pass','Credentials unchanged');
}
[$dsn]=postgresConfig('postgresql://user:pass@custom.example/app?sslmode=require&options=endpoint%3Dep-custom-123');
verify(str_contains($dsn,';options=endpoint=ep-custom-123'),'Explicit endpoint option');
foreach(['endpoint%3Dep-x%3Bsslmode%3Ddisable','endpoint%3Dep-x%20password%3Dhack','x','endpoint%3Dep-x%27'] as $value){
 try{postgresConfig('postgresql://u:p@localhost/db?options='.$value);throw new LogicException('Unsafe options accepted');}
 catch(RuntimeException $e){}
}
echo "Conexões Neon diretas, pooled, opções explícitas e validações: OK.\n";

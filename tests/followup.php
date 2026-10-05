<?php
require __DIR__.'/../core.php';
require __DIR__.'/../work.php';
function checkLevel(int $elapsed,int $cadence,string $expected,array $extra=[]):void {
    $at=(new DateTimeImmutable('today'))->modify('-'.$elapsed.' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $c=array_merge(['createdAt'=>$at,'contactAt'=>$at,'cadence'=>$cadence,'status'=>'active','deletedAt'=>null,'nextContact'=>null],$extra);
    $actual=followUpState($c)['level'];
    if($actual!==$expected)throw new RuntimeException("$elapsed/$cadence: esperado $expected, recebido $actual");
}
checkLevel(0,7,'normal');checkLevel(4,7,'normal');checkLevel(5,7,'attention');
checkLevel(6,7,'maximum');checkLevel(7,7,'maximum');checkLevel(8,7,'urgent');
checkLevel(0,1,'normal');checkLevel(1,1,'maximum');checkLevel(2,1,'urgent');
checkLevel(6,10,'attention');checkLevel(9,10,'maximum');
checkLevel(8,7,'urgent',['contactAt'=>null]);checkLevel(0,7,'normal',['contactAt'=>null]);
checkLevel(99,7,'inactive',['status'=>'done']);checkLevel(99,7,'inactive',['deletedAt'=>'2026-01-01']);
checkLevel(0,7,'maximum',['nextContact'=>date('Y-m-d')]);
checkLevel(0,7,'urgent',['nextContact'=>date('Y-m-d',strtotime('-1 day'))]);
checkLevel(8,7,'urgent',['nextContact'=>date('Y-m-d',strtotime('+20 days'))]);
echo "17 cenários de acompanhamento aprovados.\n";

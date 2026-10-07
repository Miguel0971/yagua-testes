<?php
declare(strict_types=1);
const PALETTES=['ocean'=>'Oceano','blue'=>'Azul','violet'=>'Violeta','rose'=>'Rosa','amber'=>'Âmbar','forest'=>'Verde','slate'=>'Grafite'];
function statusRows(): array {static $rows;return $rows??=sql('SELECT * FROM board_statuses ORDER BY sortOrder,key')->fetchAll();}
function statusLabels(): array {return array_column(statusRows(),'label','key');}
function clientStage(array $c): string {return $c['boardStatus']??$c['status'];}
function stageRow(string $key): array {
    $r=null;foreach(statusRows() as $candidate)if($candidate['key']===$key){$r=$candidate;break;}
    if(!$r)throw new DomainException('Status não encontrado. Recarregue a página.');return $r;
}
function stageClass(string $key): string {return 'stage-color-'.stageRow($key)['color'];}
function stageLifecycle(string $key): string {
    $s=stageRow($key);if((int)$s['isClosed'])return 'done';
    return in_array($key,['active','waiting','attention'],true)?$key:'active';
}
function listReturn(): string {
    $params=['layout'=>'list'];
    foreach(['q','filter','owner','sort','archived'] as $k)if(isset($_GET[$k])&&is_string($_GET[$k]))$params[$k]=$_GET[$k];
    return http_build_query($params);
}
function handleFeatureAction(string $action,array $user): void {
    if($action==='appearance'){
        $color=choose(PALETTES,'colorTheme');
        sql('UPDATE users SET colorTheme=? WHERE id=?',[$color,$user['id']]);
        finish('view=password','Cor da plataforma salva.');
    }
    if($action==='stage_save'){
        requireAdmin($user);$key=field($_POST,'key',40,false);$label=field($_POST,'label',60);$color=choose(PALETTES,'color');
        $order=filter_var($_POST['sortOrder']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>9999]]);
        if($order===false)throw new DomainException('Ordem deve ser de 0 a 9999.');
        $closed=isset($_POST['isClosed'])?1:0;
        transaction(function()use($key,$label,$color,$order,$closed){
            if(sql('SELECT key FROM board_statuses WHERE lower(label)=lower(?) AND key<>?',[$label,$key])->fetch())throw new DomainException('Já existe um status com esse nome.');
            if($key){
                $old=stageRow($key);
                // Built-in lifecycle semantics remain stable for old data and integrations.
                if($old['builtin'])$closed=(int)$old['isClosed'];
                if($closed!==(int)$old['isClosed'] && sql('SELECT id FROM clients WHERE boardStatus=? LIMIT 1',[$key])->fetch())throw new DomainException('Não altere o tipo de um status em uso. Mova seus clientes para outro status antes.');
                sql('UPDATE board_statuses SET label=?,color=?,sortOrder=?,isClosed=? WHERE key=?',[$label,$color,$order,$closed,$key]);
            }else sql('INSERT INTO board_statuses(key,label,color,sortOrder,isClosed,builtin) VALUES(?,?,?,?,?,0)',['custom_'.bin2hex(random_bytes(8)),$label,$color,$order,$closed]);
        });finish('view=statuses','Status salvo.');
    }
    if($action==='stage_delete'){
        requireAdmin($user);$key=field($_POST,'key',40);
        transaction(function()use($key){
            if(stageRow($key)['builtin'])throw new DomainException('Os status originais não podem ser removidos.');
            if(sql('SELECT id FROM clients WHERE boardStatus=? LIMIT 1',[$key])->fetch())throw new DomainException('Este status está em uso. Mova seus clientes, inclusive os arquivados, antes de removê-lo.');
            sql('DELETE FROM board_statuses WHERE key=?',[$key]);
        });finish('view=statuses','Status removido.');
    }
    if($action==='client_inline'){
        $id=positiveId($_POST['id']??null);$column=field($_POST,'column',20);
        if(!in_array($column,['status','ownerId','priority','nextContact'],true))throw new DomainException('Coluna não editável.');
        transaction(function()use($id,$column,$user){
            $c=activeClient($id);checkVersion($c);
            if($column==='status'){
                $value=choose(statusLabels(),'value');$description='Alterou o status para “'.statusLabels()[$value].'”.';
                sql('UPDATE clients SET status=?,boardStatus=?,version=version+1 WHERE id=?',[stageLifecycle($value),$value,$id]);
            }else{
                if($column==='ownerId'){$value=nullableUser('value');$description='Alterou o responsável do CS para '.($value?sql('SELECT nome FROM users WHERE id=?',[$value])->fetchColumn():'Sem responsável').'.';}
                elseif($column==='priority'){$value=choose(PRIORITIES,'value');$description='Alterou a prioridade para '.PRIORITIES[$value].'.';}
                else{$value=dateOnly('value');$description='Alterou o próximo contato para '.shortDate($value).'.';}
                // Column is selected exclusively from the fixed allowlist above.
                sql('UPDATE clients SET '.$column.'=?,version=version+1 WHERE id=?',[$value,$id]);
            }
            activity($id,$user,$description);
        });finish(listReturn(),'Atualização salva.');
    }
}

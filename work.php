<?php
declare(strict_types=1);
require_once __DIR__.'/features.php';
const PRIORITIES=['low'=>'Baixa','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'];
function choose(array $options, string $key): string {
    $value=field($_POST,$key,30);
    if(!isset($options[$value]))throw new DomainException('Seleção inválida.');
    return $value;
}
function nullableUser(string $key): ?int {
    if(empty($_POST[$key]))return null;
    $id=positiveId($_POST[$key]);
    if(!sql('SELECT id FROM users WHERE id=?',[$id])->fetch())throw new DomainException('Responsável não encontrado.');
    return $id;
}
function dateOnly(string $key): ?string {
    $value=field($_POST,$key,10,false);
    if(!$value)return null;
    $dt=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    if(!$dt || $dt->format('Y-m-d')!==$value)throw new DomainException('Informe uma data válida.');
    return $value;
}
function requestToken(): string {
    $token=field($_POST,'requestToken',64);
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new DomainException('Formulário inválido. Recarregue a página.');
    return $token;
}
function activeClient(int $id): array {
    $c=client($id);
    if($c['deletedAt'])throw new DomainException('Cliente arquivado. Restaure-o para alterar.');
    return $c;
}
function checkVersion(array $row): void {
    if(positiveId($_POST['version']??null)!==(int)$row['version'])throw new DomainException('Outra pessoa atualizou este item. Recarregue a página antes de salvar novamente.');
}
function activity(int $id, array $user, string $message): void {
    sql("INSERT INTO updates(clientId,kind,userId,userNameAtTime,mensagem,createdAt) VALUES (?,'system',?,?,?,?)",[$id,$user['id'],$user['nome'],$message,nowUtc()]);
    sql('UPDATE clients SET updatedAt=? WHERE id=?',[nowUtc(),$id]);
}
function finish(string $query,string $message): void {
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')) {
        header('Content-Type: application/json');echo json_encode(['ok'=>true,'message'=>$message]);exit;
    }
    $_SESSION['flash']=$message;redirect($query);
}
function technicianFields(array $data): array {
    $name=field($data,'nome',160);$whatsapp=field($data,'whatsapp',40,false);$email=field($data,'email',254,false);
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Informe um e-mail válido para o contato.');
    if($whatsapp!==''){
        if(!preg_match('/^\+?[0-9 ()-]+$/D',$whatsapp))throw new DomainException('WhatsApp: use somente números, espaços, parênteses, + e hífen.');
        $international=str_starts_with($whatsapp,'+');
        $whatsapp=preg_replace('/[^0-9]/','',$whatsapp);
        if(!$international && in_array(strlen($whatsapp),[10,11],true))$whatsapp='55'.$whatsapp;
        if(strlen($whatsapp)<10 || strlen($whatsapp)>15 || $whatsapp[0]==='0')throw new DomainException('WhatsApp: informe o código do país e DDD, por exemplo +55 11 99999-9999.');
    }
    return [$name,$whatsapp,$email];
}
function saveClientLinks(int $id,array $data): void {
    $ids=$data['technicians']??[];$new=$data['newTechnicians']??[];
    if(!is_array($ids)||!is_array($new))throw new DomainException('Seleção inválida de contatos.');
    if(isset($data['newTechnicianCount']) && (string)count($new)!==(string)$data['newTechnicianCount'])throw new DomainException('O servidor recebeu apenas parte dos contatos. Aumente max_input_vars e post_max_size no PHP ou salve em lotes menores. Nenhuma alteração foi salva.');
    if(isset($data['technicianCount']) && (string)count($ids)!==(string)$data['technicianCount'])throw new DomainException('Seleção incompleta de contatos recebida. Nenhuma alteração foi salva.');
    $ids=array_unique(array_map('positiveId',$ids));
    foreach($ids as $t)if(!sql('SELECT id FROM technicians WHERE id=? AND deletedAt IS NULL',[$t])->fetch())throw new DomainException('Contato não encontrado.');
    foreach($new as $entry){
        if(!is_array($entry))throw new DomainException('Cadastro de contato inválido.');
        if(field($entry,'nome',160,false)==='' && field($entry,'whatsapp',40,false)==='' && field($entry,'email',254,false)==='')continue;
        [$name,$phone,$email]=technicianFields($entry);
        sql('INSERT INTO technicians(nome,whatsapp,email) VALUES (?,?,?)',[$name,$phone,$email]);$ids[]=insertedId('technicians');
    }
    if(!$ids)throw new DomainException('Selecione um contato existente ou cadastre um novo contato do cliente.');
    sql('DELETE FROM client_technicians WHERE clientId=?',[$id]);
    foreach($ids as $t)sql('INSERT INTO client_technicians VALUES (?,?)',[$id,$t]);
}
function handleWorkAction(string $action,array $user): void {
    handleFeatureAction($action,$user);
    if($action==='client_save') {
        $id=empty($_POST['id'])?null:positiveId($_POST['id']);
        if(!$id)requireAdmin($user);
        $name=field($_POST,'nome',160);$description=field($_POST,'observacoes',15000,false);
        $owner=nullableUser('ownerId');$status=choose(statusLabels(),'status');$priority=choose(PRIORITIES,'priority');
        $next=dateOnly('nextContact');$cadence=positiveId($_POST['cadence']??7);
        if($cadence>365)throw new DomainException('A frequência deve ser de 1 a 365 dias.');
        $id=transaction(function()use($id,$name,$description,$owner,$status,$priority,$next,$cadence,$user){
            $lifecycle=stageLifecycle($status);
            if($id){$c=activeClient($id);checkVersion($c);
                if($user['role']!=='ADMIN')$name=$c['nome'];
                sql('UPDATE clients SET nome=?,observacoes=?,ownerId=?,status=?,boardStatus=?,priority=?,nextContact=?,cadence=?,version=version+1,updatedAt=? WHERE id=?',[$name,$description,$owner,$lifecycle,$status,$priority,$next,$cadence,nowUtc(),$id]);
                activity($id,$user,'Atualizou os dados do acompanhamento.');
            }else{
                sql('INSERT INTO clients(nome,observacoes,ownerId,status,boardStatus,priority,nextContact,cadence,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?)',[$name,$description,$owner,$lifecycle,$status,$priority,$next,$cadence,nowUtc(),nowUtc()]);
                $id=insertedId('clients');activity($id,$user,'Criou o acompanhamento do cliente.');
            }
            if($user['role']==='ADMIN')saveClientLinks($id,$_POST);
            return $id;
        });finish('client='.$id,'Acompanhamento salvo.');
    }
    if($action==='client_status') {
        $id=positiveId($_POST['id']??null);$status=choose(statusLabels(),'status');
        transaction(function()use($id,$status,$user){$c=activeClient($id);checkVersion($c);
            sql('UPDATE clients SET status=?,boardStatus=?,version=version+1 WHERE id=?',[stageLifecycle($status),$status,$id]);
            activity($id,$user,'Alterou o status para “'.statusLabels()[$status].'”.');
        });finish('client='.$id,'Status atualizado.');
    }
    if($action==='client_archive'||$action==='client_restore') {
        requireAdmin($user);$id=positiveId($_POST['id']??null);
        transaction(function()use($id,$action,$user){$c=client($id);checkVersion($c);
            sql('UPDATE clients SET deletedAt=?,version=version+1 WHERE id=?',[$action==='client_archive'?nowUtc():null,$id]);
            activity($id,$user,$action==='client_archive'?'Arquivou o cliente.':'Restaurou o cliente.');
        });finish('','Cliente '.($action==='client_archive'?'arquivado.':'restaurado.'));
    }
    if($action==='update') {
        $id=positiveId($_POST['clientId']??null);$kind=field($_POST,'kind',10);$message=field($_POST,'mensagem',15000);$token=requestToken();
        if(!in_array($kind,['contact','comment'],true))throw new DomainException('Tipo de atualização inválido.');
        $tech=null;$at=null;
        if($kind==='contact') {
            $tech=positiveId($_POST['technicianId']??null);$date=field($_POST,'contactAt',16);
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new DateTimeZone('America/Sao_Paulo'));
            if(!$dt||$dt->format('Y-m-d\TH:i')!==$date||$dt>new DateTimeImmutable('+5 minutes'))throw new DomainException('Informe a data do contato, sem data futura.');
            $at=$dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        transaction(function()use($id,$kind,$message,$token,$tech,$at,$user){
            activeClient($id);$techName=null;
            if($tech){$t=sql('SELECT t.nome FROM technicians t JOIN client_technicians ct ON ct.technicianId=t.id WHERE ct.clientId=? AND t.id=? AND t.deletedAt IS NULL',[$id,$tech])->fetch();if(!$t)throw new DomainException('Selecione um contato vinculado ao cliente.');$techName=$t['nome'];}
            $s=sql('INSERT INTO updates(clientId,kind,userId,technicianId,userNameAtTime,technicianNameAtTime,mensagem,contactAt,createdAt,requestToken) VALUES (?,?,?,?,?,?,?,?,?,?) ON CONFLICT(requestToken) DO NOTHING',[$id,$kind,$user['id'],$tech,$user['nome'],$techName,$message,$at,nowUtc(),$token]);
            if($s->rowCount())sql('UPDATE clients SET updatedAt=? WHERE id=?',[nowUtc(),$id]);
        });finish('client='.$id.'#activity',$kind==='contact'?'Contato registrado.':'Comentário publicado.');
    }
    if($action==='task_save') {
        $id=empty($_POST['id'])?null:positiveId($_POST['id']);$clientId=positiveId($_POST['clientId']??null);
        $title=field($_POST,'title',240);$description=field($_POST,'description',5000,false);$assignee=nullableUser('assigneeId');$date=dateOnly('dueDate');$priority=choose(PRIORITIES,'priority');
        $token=$id?null:requestToken();
        transaction(function()use($id,$clientId,$title,$description,$assignee,$date,$priority,$token,$user){
            activeClient($clientId);
            if($id){$task=sql('SELECT * FROM tasks WHERE id=? AND clientId=? AND deletedAt IS NULL',[$id,$clientId])->fetch();if(!$task)throw new DomainException('Subtarefa não encontrada.');checkVersion($task);
                sql('UPDATE tasks SET title=?,description=?,assigneeId=?,dueDate=?,priority=?,version=version+1,updatedAt=? WHERE id=?',[$title,$description,$assignee,$date,$priority,nowUtc(),$id]);
                activity($clientId,$user,'Editou a subtarefa “'.$title.'”.');
            }else{
                $s=sql('INSERT INTO tasks(clientId,title,description,assigneeId,dueDate,priority,createdBy,createdAt,updatedAt,requestToken) VALUES (?,?,?,?,?,?,?,?,?,?) ON CONFLICT(requestToken) DO NOTHING',[$clientId,$title,$description,$assignee,$date,$priority,$user['id'],nowUtc(),nowUtc(),$token]);
                if($s->rowCount())activity($clientId,$user,'Criou a subtarefa “'.$title.'”.');
            }
        });finish('client='.$clientId.'#subtasks','Subtarefa salva.');
    }
    if($action==='task_toggle'||$action==='task_delete') {
        $id=positiveId($_POST['id']??null);
        $clientId=transaction(function()use($id,$action,$user){
            $t=sql('SELECT * FROM tasks WHERE id=? AND deletedAt IS NULL',[$id])->fetch();if(!$t)throw new DomainException('Subtarefa não encontrada.');
            activeClient((int)$t['clientId']);checkVersion($t);
            if($action==='task_delete'){sql('UPDATE tasks SET deletedAt=?,version=version+1,updatedAt=? WHERE id=?',[nowUtc(),nowUtc(),$id]);$verb='Removeu';}
            else{
                $done=field($_POST,'completed',1)==='1';
                sql('UPDATE tasks SET completedAt=?,completedBy=?,version=version+1,updatedAt=? WHERE id=?',[$done?nowUtc():null,$done?$user['id']:null,nowUtc(),$id]);$verb=$done?'Concluiu':'Reabriu';
            }
            activity((int)$t['clientId'],$user,$verb.' a subtarefa “'.$t['title'].'”.');return (int)$t['clientId'];
        });finish('client='.$clientId.'#subtasks',$action==='task_delete'?'Subtarefa removida.':'Subtarefa atualizada.');
    }
}
function clientRows(bool $archived=false): array {
    return sql("SELECT c.*,u.nome AS ownerName,v.contactAt,v.technicianNameAtTime,v.mensagem AS lastMessage,
      a.userNameAtTime AS lastAuthor,a.mensagem AS lastActivity,a.createdAt AS lastUpdated,
      (SELECT COUNT(*) FROM tasks WHERE clientId=c.id AND deletedAt IS NULL) AS taskCount,
      (SELECT COUNT(*) FROM tasks WHERE clientId=c.id AND deletedAt IS NULL AND completedAt IS NOT NULL) AS tasksDone
      FROM clients c LEFT JOIN users u ON u.id=c.ownerId
      LEFT JOIN updates v ON v.id=(SELECT id FROM updates WHERE clientId=c.id AND kind='contact' ORDER BY contactAt DESC,id DESC LIMIT 1)
      LEFT JOIN updates a ON a.id=(SELECT id FROM updates WHERE clientId=c.id ORDER BY createdAt DESC,id DESC LIMIT 1)
      WHERE c.deletedAt IS ".($archived?'NOT NULL':'NULL')." ORDER BY (v.contactAt IS NOT NULL),v.contactAt ASC,c.id ASC")->fetchAll();
}
function daysSince(?string $at): ?int {
    if(!$at)return null;
    $date=(new DateTimeImmutable($at,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->setTime(0,0);
    return max(0,(int)$date->diff(new DateTimeImmutable('today'))->format('%r%a'));
}
function contactLabel(?string $at): string {
    $days=daysSince($at);return $days===null?'Sem contato':($days===0?'Contato hoje':($days===1?'Há 1 dia':'Há '.$days.' dias'));
}
function followUpState(array $c): array {
    if(!empty($c['deletedAt']) || $c['status']==='done')return ['level'=>'inactive','label'=>'ACOMPANHAMENTO ENCERRADO','message'=>'Sem alerta de prazo.'];
    $zone=new DateTimeZone('America/Sao_Paulo');
    $today=new DateTimeImmutable('today',$zone);
    $base=(new DateTimeImmutable($c['contactAt']??$c['createdAt'],new DateTimeZone('UTC')))->setTimezone($zone)->setTime(0,0);
    $cadence=max(1,(int)$c['cadence']);
    $due=$base->modify('+'.$cadence.' days');
    if(!empty($c['nextContact'])){
        $scheduled=new DateTimeImmutable($c['nextContact'],$zone);
        if($scheduled<$due)$due=$scheduled;
    }
    $remaining=(int)$today->diff($due)->format('%r%a');
    $window=max(1,min($cadence,(int)$base->diff($due)->format('%r%a')));
    $ratio=1-$remaining/$window;
    $level=$remaining<0?'urgent':($remaining===0||$ratio>=.85?'maximum':($ratio>=.6?'attention':'normal'));
    $labels=['urgent'=>'URGENTE','maximum'=>'ATENÇÃO MÁXIMA','attention'=>'ATENÇÃO','normal'=>'EM DIA'];
    $message=$remaining<0?'Prazo vencido há '.abs($remaining).' dia(s).':($remaining===0?'Contato vence hoje.':'Faltam '.$remaining.' dia(s) para o contato.');
    if(empty($c['contactAt']))$message='Sem contato registrado. '.$message;
    return ['level'=>$level,'label'=>$labels[$level],'message'=>$message.' Prazo: '.$due->format('d/m/Y').'.'];
}
function contactLate(array $c): bool {return in_array(followUpState($c)['level'],['attention','maximum','urgent'],true);}
function late(?string $date): bool{return $date!==null && $date<date('Y-m-d');}
function shortDate(?string $date): string {return $date?date('d/m/Y',strtotime($date)):'Sem prazo';}

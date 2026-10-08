<?php
declare(strict_types=1);
const IMPORT_COLUMNS=['nome_cliente','descricao','tec_responsavel','whatsapp_responsavel','email_responsavel','dias_de_contato','prox_contato'];
function importValue(mixed $value): string {
    $value=trim((string)($value??''));
    return in_array(strtolower($value),['null','nulo','nan'],true)?'':$value;
}
function importRows(string $file,string $extension): array {
    if($extension==='xlsx'){
        if(!function_exists('simplexml_load_string')||!function_exists('gzinflate'))throw new DomainException('O servidor precisa de SimpleXML e zlib para Excel. Você também pode salvar a planilha como CSV UTF-8.');
        require_once __DIR__.'/vendor/simplexlsx/SimpleXLSX.php';
        $book=\Shuchkin\SimpleXLSX::parseFile($file);
        if(!$book)throw new DomainException('Não foi possível ler o Excel. Salve novamente como .xlsx ou CSV UTF-8.');
        $book->setDateTimeFormat('Y-m-d');
        $dimension=$book->dimension(0);
        if($dimension[0]>64 || $dimension[1]>501)throw new DomainException('Use até 500 linhas de dados e 64 colunas. Remova linhas e colunas vazias formatadas no final da planilha.');
        return $book->rows(0,502);
    }
    if($extension!=='csv')throw new DomainException('Envie um arquivo .xlsx ou .csv.');
    $text=file_get_contents($file);
    if(str_starts_with($text,"\xEF\xBB\xBF"))$text=substr($text,3);
    if(!preg_match('//u',$text))throw new DomainException('Salve o CSV na opção UTF-8 para preservar os acentos.');
    $first=strtok($text,"\r\n")?:'';$delimiter=',';$best=0;
    foreach([',',';',"\t"] as $candidate){$count=count(str_getcsv($first,$candidate,'"',''));if($count>$best){$best=$count;$delimiter=$candidate;}}
    $stream=fopen('php://temp','w+');fwrite($stream,$text);rewind($stream);$rows=[];
    while(($row=fgetcsv($stream,0,$delimiter,'"',''))!==false){$rows[]=$row;if(count($rows)>501){fclose($stream);throw new DomainException('Importe no máximo 500 linhas por arquivo.');}}
    fclose($stream);return $rows;
}
function prepareImport(array $rows): array {
    if(!$rows)throw new DomainException('Planilha vazia.');
    $header=array_map(fn($x)=>strtolower(trim((string)$x)),array_shift($rows));
    if(count($header)!==count(array_unique($header)))throw new DomainException('O cabeçalho contém colunas repetidas ou vazias. Use as colunas do modelo.');
    if(!in_array('nome_cliente',$header,true))throw new DomainException('A primeira linha precisa conter a coluna nome_cliente.');
    $result=[];
    foreach($rows as $index=>$values){
        if(!array_filter($values,fn($v)=>importValue($v)!==''))continue;
        $raw=[];foreach(IMPORT_COLUMNS as $name){$pos=array_search($name,$header,true);$raw[$name]=$pos===false?'':importValue($values[$pos]??'');}
        $warnings=[];$skip='';$name=$raw['nome_cliente'];
        if($name==='')$skip='Sem nome de cliente; linha ignorada.';
        elseif(strlen($name)>160)$skip='Nome do cliente excede 160 bytes; linha ignorada.';
        $description=$raw['descricao'];if(strlen($description)>15000){$description='';$warnings[]='Descrição muito longa ignorada.';}
        $cadence=7;
        if($raw['dias_de_contato']!==''){
            $valid=filter_var($raw['dias_de_contato'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>365]]);
            if($valid===false)$warnings[]='Intervalo inválido; será usado 7 dias.';else $cadence=$valid;
        }
        $next=null;
        if($raw['prox_contato']!==''){
            foreach(['!Y-m-d','!d/m/Y'] as $format){$dt=DateTimeImmutable::createFromFormat($format,$raw['prox_contato']);if($dt&&$dt->format(substr($format,1))===$raw['prox_contato']){$next=$dt->format('Y-m-d');break;}}
            if(!$next)$warnings[]='Data inválida ignorada. Use dd/mm/aaaa ou aaaa-mm-dd.';
        }
        $contact=$raw['tec_responsavel'];$phone=$raw['whatsapp_responsavel'];$email=$raw['email_responsavel'];
        if(strlen($contact)>160){$contact='';$warnings[]='Nome do contato muito longo ignorado.';}
        if($contact===''){
            if($phone!==''||$email!=='')$warnings[]='WhatsApp/e-mail ignorados porque o nome do contato está vazio.';
            $phone=$email='';
        }else{
            try{[, $phone]=technicianFields(['nome'=>$contact,'whatsapp'=>$phone,'email'=>'']);}catch(DomainException $e){$phone='';$warnings[]='WhatsApp inválido ignorado.';}
            if($email!==''&&(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))){$email='';$warnings[]='E-mail inválido ignorado.';}
        }
        $duplicate=$name!==''&&(bool)sql('SELECT id FROM clients WHERE lower(nome)=lower(?) LIMIT 1',[$name])->fetch();
        $result[]=['line'=>$index+2,'name'=>$name,'description'=>$description,'contact'=>$contact,'phone'=>$phone,'email'=>$email,'cadence'=>$cadence,'next'=>$next,'warnings'=>$warnings,'skip'=>$skip,'duplicate'=>$duplicate];
    }
    if(!$result)throw new DomainException('A planilha não contém linhas preenchidas.');
    return $result;
}
function handleImportAction(string $action,array $user):void {
    if($action==='import_preview'){
        $upload=$_FILES['spreadsheet']??null;
        if(!$upload||!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException('Selecione uma planilha de até 1 MB.');
        if(!is_uploaded_file($upload['tmp_name'])||filesize($upload['tmp_name'])>1048576)throw new DomainException('O arquivo deve ter até 1 MB.');
        $extension=strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION));
        $rows=prepareImport(importRows($upload['tmp_name'],$extension));
        $_SESSION['client_import']=['rows'=>$rows,'token'=>bin2hex(random_bytes(32)),'expires'=>time()+1200,'skipDuplicates'=>isset($_POST['skipDuplicates'])];
        unset($_SESSION['import_result']);redirect('view=import');
    }
    if($action==='import_cancel'){unset($_SESSION['client_import']);finish('view=import','Prévia descartada.');}
    if($action==='import_confirm'){
        $batch=$_SESSION['client_import']??null;
        if(!$batch||$batch['expires']<time()||!hash_equals($batch['token'],requestToken()))throw new DomainException('Prévia expirada ou já concluída. Envie o arquivo novamente.');
        $report=transaction(function()use($batch,$user){
            $created=0;$skipped=0;$items=[];
            foreach($batch['rows'] as $row){
                $token=hash('sha256',$batch['token'].':'.$row['line']);
                $reason=$row['skip'];
                if(!$reason&&sql('SELECT id FROM updates WHERE requestToken=?',[$token])->fetch())$reason='Já importado nesta operação.';
                if(!$reason&&$batch['skipDuplicates']&&sql('SELECT id FROM clients WHERE lower(nome)=lower(?) LIMIT 1',[$row['name']])->fetch())$reason='Nome já cadastrado (incluindo arquivados).';
                if($reason){$skipped++;$items[]=['line'=>$row['line'],'name'=>$row['name'],'message'=>$reason];continue;}
                sql('INSERT INTO clients(nome,observacoes,status,boardStatus,priority,nextContact,cadence,createdAt,updatedAt) VALUES (?,?,?,?,?,?,?,?,?)',[$row['name'],$row['description'],'active','active','normal',$row['next'],$row['cadence'],nowUtc(),nowUtc()]);
                $id=insertedId('clients');
                if($row['contact']!==''){
                    // Each imported contact belongs to this client; names alone never merge people.
                    sql('INSERT INTO technicians(nome,whatsapp,email) VALUES (?,?,?)',[$row['contact'],$row['phone'],$row['email']]);
                    sql('INSERT INTO client_technicians(clientId,technicianId) VALUES (?,?)',[$id,insertedId('technicians')]);
                }
                sql("INSERT INTO updates(clientId,kind,userId,userNameAtTime,mensagem,createdAt,requestToken) VALUES (?,'system',?,?,?,?,?)",[$id,$user['id'],$user['nome'],'Criou o cliente por importação de planilha.',nowUtc(),$token]);
                $created++;$items[]=['line'=>$row['line'],'name'=>$row['name'],'message'=>'Criado como #'.$id.($row['warnings']?' · '.implode(' ',$row['warnings']):'')];
            }
            return ['created'=>$created,'skipped'=>$skipped,'items'=>$items];
        });
        unset($_SESSION['client_import']);$_SESSION['import_result']=$report;
        finish('view=import',$report['created'].' cliente(s) importado(s); '.$report['skipped'].' linha(s) ignorada(s).');
    }
}

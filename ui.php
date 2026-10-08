<?php
declare(strict_types=1);
function icon(string $name): string {
    $paths=[
      'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>','tasks'=>'<path d="m3 6 2 2 3-4m-5 9 2 2 3-4M12 6h9m-9 7h9M3 20h18"/>',
      'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 5h3"/>',
      'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v2"/>',
      'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'plus'=>'<path d="M12 5v14M5 12h14"/>','chat'=>'<path d="M21 11a8 8 0 0 1-8 8H6l-4 3V11a9 9 0 0 1 19 0Z"/><path d="M7 10h10M7 14h6"/>',
      'arrow'=>'<path d="m14 5-7 7 7 7"/>','chevron'=>'<path d="m9 5 7 7-7 7"/>','board'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18"/>',
      'archive'=>'<path d="M4 8v13h16V8M3 3h18v5H3zM9 12h6"/>','logout'=>'<path d="M9 3H3v18h6m6-14 5 5-5 5m-8-5h13"/>',
      'settings'=>'<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="9" cy="18" r="2"/>',
      'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>','drop'=>'<path d="M12 2C10 6 5 10 5 15a7 7 0 0 0 14 0c0-5-5-9-7-13Z"/><path d="M9 15a3 3 0 0 0 3 3"/>',
      'flag'=>'<path d="M4 22V3c5-4 10 4 16 0v11c-6 4-11-4-16 0"/>','edit'=>'<path d="m15 5 4 4M4 20l4-1L20 7a3 3 0 0 0-4-4L4 15z"/>'
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['grid']).'</svg>';
}
function initials(string $name): string {
    preg_match_all('/(?:^|\s)(\p{L})/u',$name,$m);return strtoupper(implode('',array_slice($m[1]??[],0,2)))?:'?';
}
function avatar(?string $name, int $id=0,string $class=''): string {return '<span class="avatar tone-'.($id%5).' '.h($class).'" title="'.h($name??'Sem responsável').'">'.h(initials($name??'?')).'</span>';}
function formAction(string $action,?array $row=null): void {
    csrfInput();echo '<input type="hidden" name="action" value="'.h($action).'">';
    if($row){echo '<input type="hidden" name="id" value="'.(int)$row['id'].'">';if(isset($row['version']))echo '<input type="hidden" name="version" value="'.(int)$row['version'].'">';}
}
function postVal(string $key,mixed $default=''): mixed {return $_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST[$key])&&is_string($_POST[$key])?$_POST[$key]:$default;}
function userOptions(array $users,mixed $selected=null): void {echo '<option value="">Sem responsável</option>';foreach($users as $u)echo '<option value="'.(int)$u['id'].'" '.((string)$selected===(string)$u['id']?'selected':'').'>'.h($u['nome']).'</option>';}
function options(array $values,mixed $selected):void {foreach($values as $key=>$label)echo '<option value="'.h($key).'" '.($selected===$key?'selected':'').'>'.h($label).'</option>';}
function techNames(int $id): string {return implode(', ',array_column(sql('SELECT t.nome FROM technicians t JOIN client_technicians ct ON ct.technicianId=t.id WHERE ct.clientId=? AND t.deletedAt IS NULL ORDER BY t.nome',[$id])->fetchAll(),'nome'))?:'Sem contato ativo';}
function clientCard(array $c,bool $board=false):void { $follow=followUpState($c); ?>
<article class="client-card follow-<?=h($follow['level'])?> <?=$board?'board-card':''?>" <?=$board?'draggable="true"':''?> data-client="<?=(int)$c['id']?>" data-version="<?=(int)$c['version']?>">
<a href="?client=<?=(int)$c['id']?>" class="card-link"><div class="card-top"><span class="client-symbol"><?=h(initials($c['nome']))?></span><span class="pill <?=h(stageClass(clientStage($c)))?>"><?=h(statusLabels()[clientStage($c)])?></span><span class="card-id">#<?=(int)$c['id']?></span></div><h3><?=h($c['nome'])?></h3><p class="card-tech"><?=icon('users')?><?=h(techNames((int)$c['id']))?></p>
<?php followUpBanner($c);?><div class="contact-meter"><span><?=icon('clock')?> Último contato</span><strong><?=h(contactLabel($c['contactAt']))?></strong><small><?=$c['contactAt']?h(stamp($c['contactAt'])):'Ainda não registrado'?></small></div>
<div class="card-update"><span class="label">ÚLTIMA ATUALIZAÇÃO</span><p><?=h($c['lastActivity']?:'Sem atualizações.')?></p><small><?=h($c['lastAuthor']?:'—')?> · <?=h(stamp($c['lastUpdated']))?></small></div>
<div class="card-bottom"><span class="owner-line"><?=avatar($c['ownerName'],(int)$c['ownerId'],'xs')?><span><?=h($c['ownerName']?:'Sem responsável')?></span></span><span class="task-count" title="Subtarefas concluídas"><?=icon('tasks')?><?=(int)$c['tasksDone']?>/<?=(int)$c['taskCount']?></span></div>
<?php if($c['nextContact']):?><div class="next-line <?=late($c['nextContact'])&&$c['status']!=='done'?'text-danger':''?>"><?=icon('calendar')?> Próximo contato: <?=h(shortDate($c['nextContact']))?></div><?php endif;?></a>
<?php if($board):?><form method="post" class="board-move"><?php formAction('client_status',$c);?><label class="sr-only" for="move-<?=(int)$c['id']?>">Mover <?=h($c['nome'])?></label><select id="move-<?=(int)$c['id']?>" name="status"><?php options(statusLabels(),clientStage($c));?></select><button class="icon-button" title="Aplicar status" aria-label="Aplicar status de <?=h($c['nome'])?>"><?=icon('check')?></button></form><?php endif;?><?php if(!$c['deletedAt']):?><form method="post" class="card-remove" data-confirm="Excluir este cliente da carteira? O card sairá do Quadro e ficará em Arquivados, com histórico e subtarefas preservados."><?php formAction('client_archive',$c);?><button class="text-button text-danger" aria-label="Excluir cliente <?=h($c['nome'])?>"><?=icon('archive')?> Excluir cliente</button></form><?php endif;?></article>
<?php }
function taskRow(array $t,bool $showClient=false,bool $readonly=false):void { ?>
<div class="task-row <?=$t['completedAt']?'completed':''?>"><form method="post" class="task-toggle"><?php formAction('task_toggle',$t);?><input type="hidden" name="completed" value="<?=$t['completedAt']?'0':'1'?>"><button class="check-button" <?=$readonly?'disabled':''?> aria-label="<?=$t['completedAt']?'Reabrir':'Concluir'?> <?=h($t['title'])?>"><?=$t['completedAt']?icon('check'):''?></button></form><div class="task-name"><a href="?view=task&amp;id=<?=(int)$t['id']?>"><?=h($t['title'])?></a><?php if($showClient):?><small><a href="?client=<?=(int)$t['clientId']?>"><?=h($t['clientName'])?></a></small><?php endif;?></div><span class="pill priority-<?=h($t['priority'])?>"><?=h(PRIORITIES[$t['priority']])?></span><span class="task-due <?=!$t['completedAt']&&late($t['dueDate'])?'text-danger':''?>"><?=h(shortDate($t['dueDate']))?></span><?=avatar($t['assigneeName'],(int)$t['assigneeId'],'xs')?></div>
<?php }

function technicianContacts(array $t): void {
    echo '<div class="technician-contacts">';
    if(!empty($t['whatsapp']))echo '<a href="https://wa.me/'.h($t['whatsapp']).'" target="_blank" rel="noopener noreferrer">WhatsApp · +'.h($t['whatsapp']).'</a>';
    if(!empty($t['email']))echo '<a href="mailto:'.h($t['email']).'">'.h($t['email']).'</a>';
    if(empty($t['whatsapp'])&&empty($t['email']))echo '<span>Contato não informado</span>';
    echo '</div>';
}
function newTechnicianRow(string $key,array $values=[]): void { ?>
<div class="new-technician-row"><div class="section-heading"><strong>Novo contato do cliente</strong><button type="button" class="text-button text-danger remove-technician">Remover</button></div><label>Nome do contato<input name="newTechnicians[<?=h($key)?>][nome]" maxlength="160" value="<?=h(is_string($values['nome']??null)?$values['nome']:'')?>" autocomplete="off"></label><div class="form-grid"><label>WhatsApp <small>(opcional)</small><input type="tel" name="newTechnicians[<?=h($key)?>][whatsapp]" maxlength="40" placeholder="(11) 99999-9999" value="<?=h(is_string($values['whatsapp']??null)?$values['whatsapp']:'')?>"></label><label>E-mail <small>(opcional)</small><input type="email" name="newTechnicians[<?=h($key)?>][email]" maxlength="254" value="<?=h(is_string($values['email']??null)?$values['email']:'')?>" placeholder="contato@cliente.com.br"></label></div></div>
<?php }

function followUpBanner(array $c):void { $state=followUpState($c); ?>
<div class="follow-banner follow-<?=h($state['level'])?>"><strong><?=icon('clock')?> <?=h($state['label'])?></strong><span><?=h($state['message'])?></span></div>
<?php } ?>

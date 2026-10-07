<?php
function listCell(array $c,string $column,string $display,array $choices=[],bool $readonly=false,string $badge=''):void {
    $value=$column==='status'?clientStage($c):($c[$column]??'');
    $caption=['status'=>'Status','ownerId'=>'Responsável','priority'=>'Prioridade','nextContact'=>'Próximo contato'][$column];
    if($readonly){echo '<span class="'.h($badge).'">'.h($display).'</span>';return;}
    ?>
    <details class="cell-editor"><summary title="<?=h($display)?>" aria-label="Editar <?=h($caption)?> de <?=h($c['nome'])?>"><span class="<?=h($badge)?>"><?=h($display)?></span><span class="cell-pencil" aria-hidden="true"><?=icon('edit')?></span></summary>
    <form method="post" class="inline-edit"><?php formAction('client_inline',$c);?><input type="hidden" name="column" value="<?=h($column)?>"><label><span class="sr-only"><?=h($caption)?></span><?php if($column==='nextContact'):?><input type="date" name="value" value="<?=h($value)?>" aria-label="Próximo contato de <?=h($c['nome'])?>"><?php else:?><select name="value" aria-label="<?=h($caption)?> de <?=h($c['nome'])?>"><?php foreach($choices as $k=>$label):?><option value="<?=h($k)?>" <?=(string)$value===(string)$k?'selected':''?>><?=h($label)?></option><?php endforeach;?></select><?php endif;?></label><div class="actions"><button class="button primary compact">Salvar</button><button type="button" class="text-button cancel-cell">Cancelar</button></div><span class="cell-error" role="alert"></span></form></details>
<?php }
$owners=[''=>'Sem responsável'];foreach($users as $u)$owners[(string)$u['id']]=$u['nome'];
?>
<p class="list-help">Clique no nome para abrir o histórico. <?=$archived?'Clientes arquivados são exibidos somente para consulta.':'Clique nas colunas Status, Responsável, Prioridade ou Próximo contato para editar.'?></p>
<div class="client-list-scroll" role="region" aria-label="Lista de clientes" tabindex="0"><table class="client-list"><colgroup><col style="width:260px"><col style="width:170px"><col style="width:145px"><col style="width:100px"><col style="width:130px"><col style="width:165px"><col style="width:160px"><col style="width:140px"><col style="width:245px"><col style="width:85px"></colgroup><thead><tr><th scope="col">Cliente</th><th scope="col">Status</th><th scope="col">Responsável</th><th scope="col">Prioridade</th><th scope="col">Próximo contato</th><th scope="col">Acompanhamento</th><th scope="col">Contatos</th><th scope="col">Último contato</th><th scope="col">Última atualização</th><th scope="col">Subtarefas</th></tr></thead><tbody>
<?php foreach($rows as $c):$state=followUpState($c);?><tr data-client="<?=(int)$c['id']?>"><th scope="row"><a class="list-client-link" href="?client=<?=(int)$c['id']?>"><span class="list-client-id">#<?=(int)$c['id']?></span><strong title="<?=h($c['nome'])?>"><?=h($c['nome'])?></strong><?=icon('chevron')?></a></th>
<td><?php listCell($c,'status',statusLabels()[clientStage($c)],statusLabels(),$archived,'pill '.stageClass(clientStage($c)));?></td>
<td><?php listCell($c,'ownerId',$c['ownerName']?:'Sem responsável',$owners,$archived);?></td>
<td><?php listCell($c,'priority',PRIORITIES[$c['priority']],PRIORITIES,$archived,'pill priority-'.$c['priority']);?></td>
<td><?php listCell($c,'nextContact',shortDate($c['nextContact']),[],$archived);?></td>
<td><span class="list-alert follow-<?=h($state['level'])?>" title="<?=h($state['message'])?>"><strong><?=h($state['label'])?></strong></span></td>
<td><span class="list-wrapped" title="<?=h(techNames((int)$c['id']))?>"><?=h(techNames((int)$c['id']))?></span></td>
<td><span class="list-wrapped" title="<?=h(stamp($c['contactAt']))?>"><?=h(contactLabel($c['contactAt']))?></span></td>
<td><span class="list-wrapped" title="<?=h(($c['lastAuthor']?:'—').' · '.stamp($c['lastUpdated']).' — '.($c['lastActivity']?:'Sem atualizações.'))?>"><?=h(($c['lastAuthor']?:'—').' · '.($c['lastActivity']?:'Sem atualizações.'))?></span></td>
<td><a href="?client=<?=(int)$c['id']?>#subtasks"><?=(int)$c['tasksDone']?> / <?=(int)$c['taskCount']?></a></td></tr><?php endforeach;?>
</tbody></table></div>

'use strict';
const dialog = document.querySelector('#confirmation');
let pendingForm = null;
document.addEventListener('submit', event => {
  const form = event.target;
  if (form.closest('dialog')) return;
  if (form.dataset.confirm && !form.dataset.confirmed) {
    event.preventDefault(); pendingForm = form;
    document.querySelector('#confirmation-message').textContent = form.dataset.confirm;
    dialog.showModal(); return;
  }
  form.querySelectorAll('button:not(:disabled)').forEach(button => { button.dataset.submitting = '1'; button.disabled = true; });
});
dialog.addEventListener('close', () => {
  if (dialog.returnValue === 'confirm' && pendingForm) {
    pendingForm.dataset.confirmed = 'true'; pendingForm.requestSubmit();
  }
  pendingForm = null;
});
window.addEventListener('pageshow', () => document.querySelectorAll('button[data-submitting]').forEach(b => {
  b.disabled=false; delete b.dataset.submitting;
}));
const menu = document.querySelector('#menu-toggle');
menu?.addEventListener('click', () => {
  const opened = document.body.classList.toggle('menu-open');
  menu.setAttribute('aria-expanded', String(opened));
});
document.addEventListener('keydown', e => {
  if(e.key==='Escape') {document.body.classList.remove('menu-open');menu?.setAttribute('aria-expanded','false');}
});
const kind = document.querySelector('#update-kind');
function updateComposer() {
  if (!kind) return;
  const contact = kind.value === 'contact';
  document.querySelector('#contact-fields').hidden = !contact;
  document.querySelectorAll('#contact-fields input, #contact-fields select').forEach(el => { el.disabled=!contact; el.required=contact; });
  document.querySelector('#composer-hint').textContent = contact ? 'Este registro atualiza o último contato do cliente. Horário de Brasília.' : 'Comentários internos não alteram a data do último contato.';
}
kind?.addEventListener('change', updateComposer); updateComposer();
let dragged = null;
document.querySelectorAll('.board-card').forEach(card => {
  card.addEventListener('dragstart', e => {
    if (e.target.closest('select,button,form')) {e.preventDefault();return;}
    dragged = card; e.dataTransfer.setData('text/plain', card.dataset.client); e.dataTransfer.effectAllowed='move'; card.classList.add('dragging');
  });
  card.addEventListener('dragend', () => {card.classList.remove('dragging');dragged=null;document.querySelectorAll('.drop-target').forEach(c=>c.classList.remove('drop-target'));});
});
document.querySelectorAll('.kanban-column').forEach(column => {
  column.addEventListener('dragover', e => { if(dragged){e.preventDefault();column.classList.add('drop-target');} });
  column.addEventListener('dragleave', e => {if(!column.contains(e.relatedTarget))column.classList.remove('drop-target');});
  column.addEventListener('drop', async e => {
    e.preventDefault(); column.classList.remove('drop-target');
    if(!dragged || dragged.closest('.kanban-column')===column)return;
    const card=dragged; const data=new FormData();
    data.set('csrf',document.querySelector('meta[name="csrf-token"]').content);
    data.set('action','client_status');data.set('id',card.dataset.client);data.set('version',card.dataset.version);data.set('status',column.dataset.status);
    card.classList.add('saving');
    try{
      const response=await fetch('index.php',{method:'POST',headers:{Accept:'application/json'},body:data});
      const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.message||'Não foi possível mover o cliente.');
      location.reload();
    }catch(error){const message=document.querySelector('#live-message');message.textContent=error.message;message.hidden=false;message.classList.add('error');card.classList.remove('saving');}
  });
});

// Os contatos novos são gravados na mesma transação do cliente.
const technicianSearch=document.querySelector('#technician-search');
const newTechnicians=document.querySelector('#new-technicians');
let technicianIndex=0;
if(newTechnicians){
  technicianIndex=newTechnicians.querySelectorAll('.new-technician-row').length;
  function counts(){
    document.querySelector('#new-technician-count').value=newTechnicians.querySelectorAll('.new-technician-row').length;
    const count=document.querySelectorAll('.technician-picker input:checked').length;
    document.querySelector('#technician-selected-count').textContent=count+' contato(s) existente(s) selecionado(s).';
    let expected=document.querySelector('#technician-count');
    if(!expected){expected=document.createElement('input');expected.type='hidden';expected.name='technicianCount';expected.id='technician-count';document.querySelector('#new-technician-count').after(expected);}
    expected.value=count;
  }
  const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR');
  technicianSearch.addEventListener('input',()=>{
    const query=normalize(technicianSearch.value.trim());let shown=0;
    document.querySelectorAll('.technician-option').forEach(row=>{row.hidden=!normalize(row.dataset.name).includes(query);if(!row.hidden)shown++;});
    document.querySelector('#technician-search-empty').hidden=shown>0;
  });
  document.querySelector('#add-technician').addEventListener('click',()=>{
    const template=document.querySelector('#technician-template');
    const fragment=template.content.cloneNode(true);
    fragment.querySelectorAll('[name]').forEach(input=>{input.name=input.name.replace('__KEY__',String(technicianIndex));});
    technicianIndex++;newTechnicians.append(fragment);counts();
    newTechnicians.lastElementChild.querySelector('input').focus();
  });
  newTechnicians.addEventListener('click',event=>{const remove=event.target.closest('.remove-technician');if(remove){remove.closest('.new-technician-row').remove();counts();}});
  document.querySelector('.technician-picker').addEventListener('change',counts);
  counts();
}

// Inline updates retain the current filters/order and refresh counters from server data.
document.addEventListener('click',event=>{
  const cancel=event.target.closest('.cancel-cell');
  if(cancel){const editor=cancel.closest('details');editor.open=false;editor.querySelector('form').reset();editor.querySelector('summary').focus();}
});
document.addEventListener('toggle',event=>{
  if(event.target.matches?.('.cell-editor') && event.target.open){
    document.querySelectorAll('.cell-editor[open]').forEach(d=>{if(d!==event.target)d.open=false;});
    positionCellEditor(event.target);
    event.target.querySelector('select,input[type=date]')?.focus({preventScroll:true});
  }
},true);
document.addEventListener('keydown',event=>{
  if(event.key==='Escape'){const editor=event.target.closest('.cell-editor');if(editor){editor.open=false;editor.querySelector('form').reset();editor.querySelector('summary').focus();}}
});
document.addEventListener('submit',async event=>{
  const form=event.target;
  if(!form.matches('.inline-edit,.palette-form'))return;
  event.preventDefault();
  const palette=form.matches('.palette-form');
  const data=new FormData(form);const editor=form.closest('.cell-editor');
  const caption=editor?.querySelector('summary').getAttribute('aria-label');
  const notice=document.querySelector('#live-message');
  try{
    const response=await fetch(location.href,{method:'POST',headers:{Accept:'application/json'},body:data});
    const result=await response.json();
    if(!response.ok||!result.ok)throw new Error(result.message||'Não foi possível salvar. Recarregue a página.');
    if(palette){document.documentElement.dataset.accent=data.get('colorTheme');}
    else{
      const page=await fetch(location.href,{headers:{Accept:'text/html'},cache:'no-store'});
      const doc=new DOMParser().parseFromString(await page.text(),'text/html');
      if(!page.ok||!doc.querySelector('#client-results'))throw new Error('Alteração salva. Recarregue a lista para consultar o resultado.');
      for(const selector of ['#client-results','.stats','.results-count']){
        const current=document.querySelector(selector),fresh=doc.querySelector(selector);
        if(current&&fresh)current.replaceWith(fresh);
      }
      Array.from(document.querySelectorAll('.cell-editor summary')).find(el=>el.getAttribute('aria-label')===caption)?.focus();
    }
    notice.textContent=result.message;notice.classList.remove('error');notice.hidden=false;
    setTimeout(()=>{notice.hidden=true;},3500);
  }catch(error){
    const target=form.querySelector('.cell-error')||notice;
    target.textContent=error.message;target.hidden=false;if(target===notice)target.classList.add('error');
  }finally{form.querySelectorAll('button[data-submitting]').forEach(b=>{b.disabled=false;delete b.dataset.submitting;});}
});

function positionCellEditor(editor){
  const form=editor.querySelector('.inline-edit'),anchor=editor.querySelector('summary');
  if(!form||!editor.open)return;
  form.classList.add('floating-edit');
  const box=anchor.getBoundingClientRect(),height=form.offsetHeight;
  const width=form.offsetWidth;
  form.style.left=Math.max(8,Math.min(box.left,window.innerWidth-width-8))+'px';
  form.style.top=Math.max(8,box.bottom+height+8>window.innerHeight?box.top-height-4:box.bottom+4)+'px';
}
document.addEventListener('click',event=>{
  document.querySelectorAll('.cell-editor[open]').forEach(editor=>{
    if(!editor.contains(event.target)){editor.open=false;editor.querySelector('form').reset();}
  });
});
window.addEventListener('resize',()=>document.querySelectorAll('.cell-editor[open]').forEach(positionCellEditor));
document.addEventListener('scroll',event=>{
  if(event.target.closest?.('.inline-edit'))return;
  document.querySelectorAll('.cell-editor[open]').forEach(editor=>{editor.open=false;});
},true);

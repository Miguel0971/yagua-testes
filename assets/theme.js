'use strict';
(() => {
  const system=window.matchMedia('(prefers-color-scheme: dark)');
  let preference;
  try { preference=localStorage.getItem('yagua-cs-theme'); } catch (_) {}
  function apply(theme) {
    document.documentElement.dataset.theme=theme;
    document.querySelectorAll('.theme-toggle').forEach(button=>{
      button.textContent=theme==='dark'?'Tema claro':'Tema escuro';
      button.setAttribute('aria-label',theme==='dark'?'Ativar tema claro':'Ativar tema escuro');
      button.setAttribute('aria-pressed',String(theme==='dark'));
    });
  }
  apply(['dark','light'].includes(preference)?preference:(system.matches?'dark':'light'));
  document.addEventListener('DOMContentLoaded',()=>{
    apply(document.documentElement.dataset.theme);
    document.querySelectorAll('.theme-toggle').forEach(button=>button.addEventListener('click',()=>{
      preference=document.documentElement.dataset.theme==='dark'?'light':'dark';apply(preference);
      try{localStorage.setItem('yagua-cs-theme',preference);}catch(_){}
    }));
  });
  system.addEventListener('change',()=>{if(!['dark','light'].includes(preference))apply(system.matches?'dark':'light');});
  window.addEventListener('storage',event=>{if(event.key==='yagua-cs-theme'){preference=event.newValue;apply(['dark','light'].includes(preference)?preference:(system.matches?'dark':'light'));}});
})();

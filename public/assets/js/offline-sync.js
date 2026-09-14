(function(){
'use strict';

const OLD_STORAGE_KEY='showdepremios_pending_sales';
try{localStorage.removeItem(OLD_STORAGE_KEY);}catch(e){}

function ensureBanner(){
  let banner=document.getElementById('connectivity-banner');
  if(!banner){
    banner=document.createElement('div');
    banner.id='connectivity-banner';
    banner.style.cssText='display:none;position:fixed;top:0;left:0;right:0;z-index:99999;padding:.65rem 1rem;text-align:center;font-weight:800;background:#f59e0b;color:#78350f;box-shadow:0 4px 12px rgba(0,0,0,.25)';
    document.body.appendChild(banner);
  }
  return banner;
}

function updateBanner(){
  const banner=ensureBanner();
  if(navigator.onLine){banner.style.display='none';return;}
  banner.style.display='block';
  banner.textContent='⚠️ SEM CONEXÃO: operações que alteram dados foram bloqueadas para evitar duplicidade ou perda. Reconecte antes de salvar.';
}

function protectMutationForms(){
  document.querySelectorAll('form').forEach(form=>{
    const method=(form.getAttribute('method')||'GET').toUpperCase();
    if(method!=='POST'||form.dataset.offlineGuard==='1')return;
    form.dataset.offlineGuard='1';
    form.addEventListener('submit',event=>{
      if(navigator.onLine)return;
      event.preventDefault();
      alert('Sem conexão. Esta operação não foi enviada nem armazenada localmente. Reconecte e tente novamente.');
      updateBanner();
    });
  });
}

window.addEventListener('online',()=>{updateBanner();});
window.addEventListener('offline',()=>{updateBanner();});
document.addEventListener('DOMContentLoaded',()=>{
  updateBanner();
  protectMutationForms();
  if('serviceWorker'in navigator){navigator.serviceWorker.register('/sw.js').catch(()=>{});}
});
})();

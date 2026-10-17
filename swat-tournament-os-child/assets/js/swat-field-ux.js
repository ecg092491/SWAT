/* Presentation only: no fetch, storage migrations or push subscriptions. */
(() => {
 'use strict';
 const app=document.querySelector('[data-swat-app]');
 if(!app) return;
 const appearanceButtons=app.querySelectorAll('[data-swat-appearance-choice]');
 const systemAppearance=window.matchMedia('(prefers-color-scheme: dark)');
 function updateAppearanceButtons(){
  const mode=document.body.dataset.swatAppearance || (systemAppearance.matches?'dark':'light');
  appearanceButtons.forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.swatAppearanceChoice===mode)));
 }
 appearanceButtons.forEach(button=>button.addEventListener('click',()=>{
  const mode=button.dataset.swatAppearanceChoice;
  document.body.dataset.swatAppearance=mode;
  try{localStorage.setItem('swatAppearanceV1',mode);}catch(_){}
  updateAppearanceButtons();
 }));
 if(systemAppearance.addEventListener)systemAppearance.addEventListener('change',updateAppearanceButtons);
 updateAppearanceButtons();
 const refresh=app.querySelector('[data-swat-refresh]');
 if(refresh){refresh.setAttribute('aria-label','Refresh tournament data');refresh.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M5.6 7a8 8 0 0 1 13-1L20 8M4 16l1.4 2a8 8 0 0 0 13-1"/></svg>';}
 const row=document.querySelector('[data-swat-smart-alert-row]');
 let dismissed=false;
 try{dismissed=sessionStorage.getItem('swatAlertsDismissedV1')==='1';}catch(_){}
 const shouldHideAlerts=()=>dismissed || !app.querySelector('[data-swat-my-weekend]')?.classList.contains('has-followed-teams') || (app.dataset.uxView && app.dataset.uxView !== 'today');
 function decorate(){
  if(row && shouldHideAlerts()) row.hidden=true;
  // Original push feedback remains responsible for platform-specific instructions/errors.
  countdown();
 }
 document.querySelector('[data-swat-dismiss-alerts]')?.addEventListener('click',()=>{
  dismissed=true;if(row)row.hidden=true;
  try{sessionStorage.setItem('swatAlertsDismissedV1','1');}catch(_){}
 });
 function countdown(){
  app.querySelectorAll('.swat-ux-countdown').forEach((el)=>{
   const status=el.dataset.status;
   if(status!=='UPCOMING'){el.textContent=status==='LIVE'?'Playing now':status;return;}
   const raw=el.dataset.start || '';
   // Only use source timestamps carrying an offset; never guess the visitor's timezone.
   if(!/(Z|[+-]\d\d:\d\d)$/.test(raw)) return;
   const time=Date.parse(raw);if(!Number.isFinite(time))return;
   const mins=Math.ceil((time-Date.now())/60000);
   el.textContent=mins>0 && mins<60 ? 'in '+mins+' min' : mins>=60 && mins<1440 ? 'in '+Math.floor(mins/60)+'h'+(mins%60?' '+mins%60+'m':'') : mins<=-90 ? 'Result pending' : mins<=0 ? 'Start time passed · Awaiting update' : 'Upcoming';
  });
 }
 document.addEventListener('swat:ux-rendered',decorate);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)decorate();});
 // Narrow observer only: push renderer may reset hidden when teams change.
 if(row)new MutationObserver(()=>{if(shouldHideAlerts() && !row.hidden)row.hidden=true;}).observe(row,{attributes:true,attributeFilter:['hidden']});
 setInterval(()=>{if(!document.hidden)countdown();},30000);
 decorate();
})();

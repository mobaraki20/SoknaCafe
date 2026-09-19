const RELEASE='1.36.4-dev.35';
const CACHE='cafe-staff-v1.36.4-dev.35';
const STATIC_BASE=['./offline.html','./assets/css/tokens.css','./assets/css/app.css','./assets/css/responsive.css','./assets/css/panel.css','./assets/css/public-page.css','./assets/css/guest-menu.css',

  './assets/css/panel-layout.css','./assets/css/panel-components.css','./assets/css/inventory.css','./assets/css/quick-order.css','./assets/css/staff-action-queue.css','./assets/css/operator-live.css','./assets/css/items-management.css','./assets/css/reorder.css','./assets/icons/ui-sprite.svg','./assets/js/pwa.js','./assets/js/horizontal-rail.js','./assets/js/category-navigation.js','./assets/js/guest-sheet.js','./assets/js/menu.js','./assets/js/interaction-modality.js','./assets/js/panel-shell.js','./assets/js/panel-validation.js','./assets/js/panel-core.js','./assets/js/panel-menus.js','./assets/js/panel-media.js','./assets/js/panel-jalali.js','./assets/js/panel-time-picker.js','./assets/js/panel-choice.js','./assets/js/inventory-form-flow.js','./assets/js/panel-conditions.js','./assets/js/panel-form-state.js','./assets/js/settings-sections.js','./assets/js/device-notifications.js','./assets/js/table-management.js','./assets/js/staff-quick-order.js','./assets/js/items-management.js','./assets/js/reorder-list.js','./assets/js/messages-admin.js','./assets/js/print-template-designer.js','./assets/icons/favicon-32.png','./assets/icons/favicon-180.png','./assets/icons/favicon-192.png','./assets/icons/favicon-512.png'];
const STATIC=STATIC_BASE.map(url=>url.startsWith('./assets/')?`${url}?v=${RELEASE}`:url);
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(async cache=>{await cache.add('./offline.html');await Promise.allSettled(STATIC.filter(url=>url!=='./offline.html').map(url=>cache.add(url)));})));
self.addEventListener('message',event=>{if(event.data?.type==='SKIP_WAITING')self.skipWaiting();});
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('cafe-staff-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{
  const req=event.request;if(req.method!=='GET')return;
  const url=new URL(req.url);if(url.origin!==location.origin)return;
    if(req.mode==='navigate'){event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>caches.match('./offline.html').then(cached=>cached||new Response('سامانه موقتاً در دسترس نیست',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8','Cache-Control':'no-store','X-Sokna-Fallback':'network-unavailable'}}))));return;}
  if(/\.(?:css|js|png|jpg|jpeg|webp|gif|svg|woff2)(?:\?|$)/i.test(url.pathname+url.search)){
    event.respondWith(caches.match(req).then(cached=>cached||fetch(req).then(res=>{if(res.ok&&res.type==='basic'){const copy=res.clone();caches.open(CACHE).then(c=>c.put(req,copy));}return res;})));
  }
});
self.addEventListener('push',event=>{
  let data={title:'اعلان کافه',body:'یک رویداد تازه ثبت شده.',url:'./operator/index.php',tag:'staff-event'};
  try{if(event.data)data={...data,...event.data.json()};}catch(_){if(event.data)data.body=event.data.text();}
  const actions=Array.isArray(data.actions)?data.actions.slice(0,2).map(row=>({action:String(row.action||''),title:String(row.title||'')})).filter(row=>row.action&&row.title):[];
  event.waitUntil(self.registration.showNotification(data.title,{body:data.body,icon:'./favicon.php?size=192',badge:'./favicon.php?size=192',tag:data.tag||'staff-event',renotify:true,actions,data:{url:data.url||'./operator/index.php',action_url:data.action_url||'',action_token:data.action_token||''}}));
});
const openNotificationTarget=target=>clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{for(const client of list){if(client.url===target&&'focus'in client)return client.focus();}for(const client of list){if('navigate'in client&&'focus'in client)return client.navigate(target).then(()=>client.focus());}return clients.openWindow(target);});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const data=event.notification.data||{};
  const target=new URL(data.url||'./operator/index.php',self.registration.scope).href;
  if(['accept_call','approve_order'].includes(event.action)&&data.action_url&&data.action_token){
    const actionUrl=new URL(data.action_url,self.registration.scope).href;
    event.waitUntil(fetch(actionUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({token:data.action_token}),cache:'no-store'}).then(async response=>{
      const result=await response.json().catch(()=>({}));const ok=response.ok&&result.success!==false;const resultTarget=new URL(result.review_url||data.url||'./operator/index.php',self.registration.scope).href;
      if(ok){const windows=await clients.matchAll({type:'window',includeUncontrolled:true});windows.forEach(client=>client.postMessage({type:'SOKNA_PUSH_ACTION_RESULT',success:true,action:event.action,message:result.message||'اقدام اعلان ثبت شد.',subject_url:resultTarget}));return;}
      return openNotificationTarget(resultTarget);
    }).catch(()=>openNotificationTarget(target)));
    return;
  }
  event.waitUntil(openNotificationTarget(target));
});

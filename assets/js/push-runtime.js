(() => {
  'use strict';
  let draining = false;
  let lastDrainAt = 0;

  const kick = (meta) => {
    if (!meta || !meta.url || !meta.queue_id || !meta.token || !navigator.onLine) return;
    fetch(String(meta.url), {
      method: 'POST',
      cache: 'no-store',
      credentials: 'same-origin',
      keepalive: true,
      headers: {'Content-Type': 'application/json', Accept: 'application/json'},
      body: JSON.stringify({queue_id: Number(meta.queue_id), token: String(meta.token)}),
    }).catch(() => {});
  };

  const inventoryKick = (meta) => {
    if (!meta || !meta.url || !meta.order_id || !navigator.onLine) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!csrf) return;
    fetch(String(meta.url), {
      method: 'POST',
      cache: 'no-store',
      credentials: 'same-origin',
      keepalive: true,
      headers: {'Content-Type': 'application/json', Accept: 'application/json'},
      body: JSON.stringify({order_id: Number(meta.order_id), csrf_token: csrf}),
    }).catch(() => {});
  };


  let printWakeInFlight = false;
  let pendingPrintWake = null;
  const mergeWake = (a,b) => {
    if(!a)return b;
    const map=new Map(); [...(a.jobs||[]),...(b.jobs||[])].forEach(j=>{if(Number(j?.job_id)>0)map.set(Number(j.job_id),j);});
    return {...b,jobs:[...map.values()],expires_at:String(b.expires_at||a.expires_at||'')};
  };
  const fetchWithDeadline = async (url, options={}, timeoutMs=1800) => {
    const controller=new AbortController(); const timer=setTimeout(()=>controller.abort(),timeoutMs);
    try{return await fetch(url,{...options,signal:controller.signal});}finally{clearTimeout(timer);}
  };
  const drainPrintWake = async () => {
    if(printWakeInFlight||!pendingPrintWake||!navigator.onLine)return;
    const meta=pendingPrintWake; pendingPrintWake=null;
    const jobs=(meta.jobs||[]).filter(j=>j&&Number(j.job_id)>0&&String(j.status||'')==='pending'); if(!jobs.length)return;
    const endpoint=String(window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL||'').trim(); if(!endpoint)return;
    const destinations=[...new Set(jobs.map(j=>String(j.destination_key||'')).filter(Boolean))]; if(!destinations.length)return;
    printWakeInFlight=true;
    try{
      const capabilityResponse=await fetchWithDeadline(`${endpoint}?destinations=${encodeURIComponent(destinations.join(','))}`,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}},1600);
      const capability=await capabilityResponse.json().catch(()=>({})); if(!capabilityResponse.ok||!capability.success||!Array.isArray(capability.bridges))return;
      const groups=new Map();
      for(const bridge of capability.bridges){
        const port=Number(bridge?.port||0),pairing=String(bridge?.pairing_id||''),agentId=Number(bridge?.agent_id||0); if(port<1024||port>65535||!pairing||agentId<1)continue;
        const key=`${agentId}:${port}:${pairing}`; if(!groups.has(key))groups.set(key,{bridge,ids:new Set()});
        jobs.filter(j=>String(j.destination_key)===String(bridge.destination_key)).forEach(j=>groups.get(key).ids.add(Number(j.job_id)));
      }
      const expiresAt=String(meta.expires_at||'');
      await Promise.allSettled([...groups.values()].map(async ({bridge,ids})=>{
        if(!ids.size)return;
        const options={method:'POST',mode:'cors',cache:'no-store',headers:{'Content-Type':'application/json','X-Sokna-Bridge-Pairing':String(bridge.pairing_id)},body:JSON.stringify({type:'print.wake',protocol_version:1,request_id:String(meta.request_id||`wake-${Date.now()}`),job_ids:[...ids],expires_at:expiresAt})}; options.targetAddressSpace='local';
        await fetchWithDeadline(`http://127.0.0.1:${Number(bridge.port)}/v1/wake`,options,1400);
      }));
    }catch(_){/* polling remains recovery truth */}
    finally{printWakeInFlight=false;if(pendingPrintWake)queueMicrotask(()=>void drainPrintWake());}
  };
  const printWake = async (meta) => {
    if(!meta||Number(meta.protocol_version)!==1||!Array.isArray(meta.jobs)||!navigator.onLine)return;
    pendingPrintWake=mergeWake(pendingPrintWake,meta); await drainPrintWake();
  };

  const handleResponse = (data) => {
    const pushMeta = data?._push?.kick;
    if (pushMeta) kick(pushMeta);
    const inventoryMeta = data?._inventory?.kick;
    if (inventoryMeta) inventoryKick(inventoryMeta);
    if (data?._print_wake) void printWake(data._print_wake);
    return data;
  };

  const drain = async ({force = false} = {}) => {
    const url = String(window.SOKNA_PUSH_DRAIN_URL || '').trim();
    if (!url || draining || !navigator.onLine || document.hidden) return;
    const now = Date.now();
    if (!force && now - lastDrainAt < 12000) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    if (!csrf) return;
    draining = true;
    lastDrainAt = now;
    try {
      await fetch(url, {
        method: 'POST',
        cache: 'no-store',
        credentials: 'same-origin',
        keepalive: true,
        headers: {'Content-Type': 'application/json', Accept: 'application/json'},
        body: JSON.stringify({csrf_token: csrf}),
      });
    } catch (_) {
      // Queue remains durable; another request or the optional CLI worker will retry.
    } finally {
      draining = false;
    }
  };

  window.SoknaPushRuntime = {handleResponse, kick, inventoryKick, printWake, drain};
  window.addEventListener('online', () => void drain({force: true}), {passive: true});
  document.addEventListener('visibilitychange', () => { if (!document.hidden) void drain({force: true}); });
  if (window.SOKNA_PUSH_DRAIN_URL) {
    window.setTimeout(() => void drain({force: true}), 800);
    window.setInterval(() => void drain(), 20000);
  }
})();

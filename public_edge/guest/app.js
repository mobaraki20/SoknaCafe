(()=>{
'use strict';
const cfg=window.SOKNA_GUEST||{};
const cart=new Map();
const q=(s)=>document.querySelector(s);
const qa=(s)=>[...document.querySelectorAll(s)];
const uuid=()=>crypto.randomUUID?.()||`${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
const deviceKey='sokna-public-device-v1';
let device='';try{device=localStorage.getItem(deviceKey)||uuid();localStorage.setItem(deviceKey,device)}catch(_){device=uuid()}
const status=q('#guestStatus'),cartBar=q('#cartBar'),cartList=q('#cartList'),cartTotal=q('#cartTotal'),orderBtn=q('#submitOrder');
const fa=(n)=>new Intl.NumberFormat('fa-IR').format(Number(n||0));
const setStatus=(t,bad=false)=>{if(!status)return;status.textContent=t;status.classList.toggle('bad',bad);status.classList.remove('hidden')};
const render=()=>{let total=0,count=0;cartList&&(cartList.innerHTML='');for(const [id,line] of cart){total+=line.price*line.qty;count+=line.qty;if(cartList){const row=document.createElement('div');row.className='cart-row';row.innerHTML=`<span><b>${line.name}</b><small>${fa(line.price)} تومان</small></span><span><button data-dec="${id}">−</button><b>${fa(line.qty)}</b><button data-inc="${id}">+</button></span>`;cartList.appendChild(row)}}if(cartTotal)cartTotal.textContent=`${fa(total)} تومان`;if(cartBar){cartBar.classList.toggle('hidden',count===0);q('#cartCount').textContent=fa(count)}};
qa('[data-add]').forEach(btn=>btn.addEventListener('click',()=>{const id=Number(btn.dataset.add),name=btn.dataset.name||'',price=Number(btn.dataset.price||0);const old=cart.get(id)||{id,name,price,qty:0};old.qty++;cart.set(id,old);render()}));
cartList?.addEventListener('click',(e)=>{const b=e.target.closest('button');if(!b)return;const id=Number(b.dataset.inc||b.dataset.dec||0),line=cart.get(id);if(!line)return;if(b.dataset.inc)line.qty++;else line.qty--;if(line.qty<=0)cart.delete(id);render()});
q('#openCart')?.addEventListener('click',()=>q('#cartPanel')?.classList.remove('hidden'));
q('#closeCart')?.addEventListener('click',()=>q('#cartPanel')?.classList.add('hidden'));

async function enqueue(kind,tableToken,tableRef,payload,clientToken){
  const res=await fetch(cfg.enqueueUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({installation_id:cfg.installationId,request_id:`${kind}:${uuid()}`,kind,client_token:clientToken,device_token:device,table_token:tableToken||'',table_ref:tableRef||'',payload})});
  const data=await res.json().catch(()=>({}));
  if(!res.ok)throw new Error(data.error==='local_unavailable'?'ارتباط با کافه موقتاً در دسترس نیست.':'درخواست ثبت نشد.');
  return {requestId:data.request_id||null,data};
}
async function submitAndWait(kind,tableToken,tableRef,payload,clientToken){
  const requestId=`${kind}:${uuid()}`;
  const res=await fetch(cfg.enqueueUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({installation_id:cfg.installationId,request_id:requestId,kind,client_token:clientToken,device_token:device,table_token:tableToken||'',table_ref:tableRef||'',payload})});
  const first=await res.json().catch(()=>({}));
  if(!res.ok)throw new Error(first.error==='local_unavailable'?'ارتباط با کافه موقتاً در دسترس نیست.':first.error==='ordering_paused'?'سفارش‌گیری فعلاً متوقف است.':first.error==='waiter_disabled'?'فراخوان گارسون فعلاً فعال نیست.':'درخواست ثبت نشد.');
  for(let i=0;i<65;i++){
    await new Promise(r=>setTimeout(r,650));
    const r=await fetch(`${cfg.resultUrl}?installation_id=${encodeURIComponent(cfg.installationId)}&request_id=${encodeURIComponent(requestId)}&client_token=${encodeURIComponent(clientToken)}`,{cache:'no-store'});
    const d=await r.json().catch(()=>({}));
    if(d.terminal){
      if(d.state==='committed')return d.result||{};
      throw new Error(d.result?.message||'درخواست توسط کافه تأیید نشد.');
    }
  }
  throw new Error('نتیجه درخواست مشخص نشد. دوباره وضعیت را بررسی کنید.');
}
orderBtn?.addEventListener('click',async()=>{
  if(!cfg.canOrder||cart.size===0)return;
  orderBtn.disabled=true;setStatus('در حال ثبت سفارش…');
  const client=uuid();
  try{
    const items=[...cart.values()].map(x=>({id:x.id,quantity:x.qty,unit_price:x.price,note:'',fulfillment_mode:'dine_in'}));
    const result=await submitAndWait('guest_order.submit',cfg.tableToken,'',{session_token:'',customer_note:'',items},client);
    cart.clear();render();q('#cartPanel')?.classList.add('hidden');setStatus(result.message||`سفارش ${result.order_number||''} ثبت شد.`);
  }catch(e){setStatus(e.message||'ثبت سفارش انجام نشد.',true)}
  finally{orderBtn.disabled=false}
});
q('#waiterButton')?.addEventListener('click',async()=>{
  const btn=q('#waiterButton');btn.disabled=true;setStatus('در حال ارسال فراخوان…');
  const ref=cfg.tableToken?'':String(q('#waiterTable')?.value||'');
  try{const result=await submitAndWait('waiter_call.create',cfg.tableToken,ref,{session_token:''},uuid());setStatus(result.shared?'فراخوان فعال این میز ثبت است.':'گارسون مطلع شد.')}
  catch(e){setStatus(e.message||'فراخوان انجام نشد.',true)}
  finally{btn.disabled=false}
});
render();
})();
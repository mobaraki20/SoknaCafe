(()=>{
'use strict';

const cfg=window.SOKNA_GUEST||{};
const cart=new Map();
const q=(selector)=>document.querySelector(selector);
const qa=(selector)=>[...document.querySelectorAll(selector)];
const uuid=()=>crypto.randomUUID?.()||`${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
const sleep=(ms)=>new Promise(resolve=>setTimeout(resolve,ms));
const fa=(value)=>new Intl.NumberFormat('fa-IR').format(Number(value||0));

const deviceKey='sokna-public-device-v1';
let device='';
try{
  device=localStorage.getItem(deviceKey)||uuid();
  localStorage.setItem(deviceKey,device);
}catch(_){
  device=uuid();
}

const status=q('#guestStatus');
const cartBar=q('#cartBar');
const cartList=q('#cartList');
const cartTotal=q('#cartTotal');
const orderBtn=q('#submitOrder');
const waiterBtn=q('#waiterButton');

const setStatus=(text,bad=false)=>{
  if(!status)return;
  status.textContent=String(text||'');
  status.classList.toggle('bad',Boolean(bad));
  status.classList.remove('hidden');
};

const makeButton=(label,attr,id)=>{
  const button=document.createElement('button');
  button.type='button';
  button.textContent=label;
  button.setAttribute(attr,String(id));
  return button;
};

const render=()=>{
  let total=0;
  let count=0;
  if(cartList)cartList.replaceChildren();
  for(const [id,line] of cart){
    total+=line.price*line.qty;
    count+=line.qty;
    if(!cartList)continue;
    const row=document.createElement('div');
    row.className='cart-row';

    const copy=document.createElement('span');
    const title=document.createElement('b');
    title.textContent=line.name;
    const price=document.createElement('small');
    price.textContent=`${fa(line.price)} تومان`;
    copy.append(title,price);

    const controls=document.createElement('span');
    const dec=makeButton('کم','data-dec',id);
    dec.setAttribute('aria-label','کم کردن');
    const qty=document.createElement('b');
    qty.textContent=fa(line.qty);
    const inc=makeButton('زیاد','data-inc',id);
    inc.setAttribute('aria-label','زیاد کردن');
    controls.append(dec,qty,inc);

    row.append(copy,controls);
    cartList.appendChild(row);
  }
  if(cartTotal)cartTotal.textContent=`${fa(total)} تومان`;
  if(cartBar){
    cartBar.classList.toggle('hidden',count===0);
    const countNode=q('#cartCount');
    if(countNode)countNode.textContent=fa(count);
  }
};

qa('[data-add]').forEach(button=>button.addEventListener('click',()=>{
  const id=Number(button.dataset.add);
  const name=String(button.dataset.name||'');
  const price=Number(button.dataset.price||0);
  const line=cart.get(id)||{id,name,price,qty:0};
  line.qty++;
  cart.set(id,line);
  render();
}));

cartList?.addEventListener('click',(event)=>{
  const button=event.target.closest('button');
  if(!button)return;
  const id=Number(button.dataset.inc||button.dataset.dec||0);
  const line=cart.get(id);
  if(!line)return;
  if(button.dataset.inc)line.qty++;
  else line.qty--;
  if(line.qty<=0)cart.delete(id);
  render();
});

q('#openCart')?.addEventListener('click',()=>q('#cartPanel')?.classList.remove('hidden'));
q('#closeCart')?.addEventListener('click',()=>q('#cartPanel')?.classList.add('hidden'));

const pendingStorageKey=(kind)=>`sokna-public-pending-v1:${cfg.installationId||'unknown'}:${kind}`;

const readPending=(kind)=>{
  try{
    const raw=localStorage.getItem(pendingStorageKey(kind));
    if(!raw)return null;
    const value=JSON.parse(raw);
    if(!value||value.kind!==kind||value.installationId!==cfg.installationId)return null;
    return value;
  }catch(_){
    return null;
  }
};

const writePending=(record)=>{
  try{localStorage.setItem(pendingStorageKey(record.kind),JSON.stringify(record))}catch(_){}
};

const clearPending=(kind)=>{
  try{localStorage.removeItem(pendingStorageKey(kind))}catch(_){}
};

const hasPending=(kind)=>Boolean(readPending(kind));

const syncActionLocks=()=>{
  if(orderBtn)orderBtn.disabled=!cfg.canOrder||hasPending('guest_order.submit');
  if(waiterBtn)waiterBtn.disabled=hasPending('waiter_call.create');
};

const publicErrorMessage=(code)=>({
  local_unavailable:'ارتباط با کافه موقتاً در دسترس نیست.',
  ordering_paused:'سفارش‌گیری فعلاً متوقف است.',
  waiter_disabled:'فراخوان گارسون فعلاً فعال نیست.',
  invalid_table:'این میز دیگر فعال نیست.',
  table_qr_required:'برای سفارش، QR همان میز را اسکن کنید.',
  request_id_conflict:'این درخواست با اطلاعات دیگری ثبت شده است.',
})[String(code||'')]||'درخواست ثبت نشد.';

async function resultLookup(record){
  const url=`${cfg.resultUrl}?installation_id=${encodeURIComponent(cfg.installationId)}&request_id=${encodeURIComponent(record.requestId)}&client_token=${encodeURIComponent(record.clientToken)}`;
  const response=await fetch(url,{cache:'no-store'});
  const data=await response.json().catch(()=>({}));
  if(response.status===404)return {missing:true};
  if(!response.ok)throw new Error('وضعیت درخواست قابل بررسی نیست.');
  return data;
}

async function enqueueRecord(record){
  const response=await fetch(cfg.enqueueUrl,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({
      installation_id:record.installationId,
      request_id:record.requestId,
      kind:record.kind,
      client_token:record.clientToken,
      device_token:record.deviceToken,
      table_token:record.tableToken||'',
      table_ref:record.tableRef||'',
      payload:record.payload,
    }),
  });
  const data=await response.json().catch(()=>({}));
  if(!response.ok){
    const error=new Error(publicErrorMessage(data.error));
    error.safeToRetry=response.status<500&&data.error!=='request_id_conflict';
    error.code=String(data.error||'');
    throw error;
  }
  return data;
}

function terminalResult(data,record){
  if(!data?.terminal)return null;
  clearPending(record.kind);
  syncActionLocks();
  if(data.state==='committed')return {ok:true,result:data.result||{}};
  const message=data.result?.message
    ||(data.state==='expired'?'زمان این درخواست تمام شد و عملیاتی ثبت نشد.':'درخواست توسط کافه تأیید نشد.');
  return {ok:false,error:new Error(message),state:data.state};
}

async function submitAndWait(record){
  writePending(record);
  syncActionLocks();

  // A transport failure after POST is ambiguous: first ask Public whether the
  // exact request_id exists, then retry the SAME logical request if needed.
  let accepted=false;
  for(let attempt=0;attempt<3&&!accepted;attempt++){
    try{
      await enqueueRecord(record);
      accepted=true;
    }catch(error){
      if(error?.safeToRetry){
        clearPending(record.kind);
        syncActionLocks();
        throw error;
      }
      try{
        const lookup=await resultLookup(record);
        if(!lookup.missing)accepted=true;
      }catch(_){}
      if(!accepted)await sleep(700);
    }
  }

  if(!accepted){
    const uncertain=new Error('ارتباط قطع شد؛ وضعیت همین درخواست ذخیره شده و پس از اتصال دوباره بررسی می‌شود.');
    uncertain.uncertain=true;
    throw uncertain;
  }

  const deadline=Date.now()+70000;
  while(Date.now()<deadline){
    await sleep(700);
    try{
      const data=await resultLookup(record);
      if(data.missing)continue;
      const terminal=terminalResult(data,record);
      if(terminal){
        if(terminal.ok)return terminal.result;
        throw terminal.error;
      }
    }catch(error){
      // Network/result lookup failures keep the exact request pending.
      if(error?.message&&error.message!=='وضعیت درخواست قابل بررسی نیست.')throw error;
    }
  }

  const uncertain=new Error('نتیجه هنوز قطعی نیست؛ درخواست جدید نفرستید. همین درخواست پس از اتصال دوباره بررسی می‌شود.');
  uncertain.uncertain=true;
  throw uncertain;
}

function makeRecord(kind,tableToken,tableRef,payload,clientToken=uuid()){
  return {
    version:1,
    installationId:String(cfg.installationId||''),
    requestId:`${kind}:${uuid()}`,
    kind,
    clientToken,
    deviceToken:device,
    tableToken:String(tableToken||''),
    tableRef:String(tableRef||''),
    payload,
    createdAt:new Date().toISOString(),
  };
}

async function resumePending(kind){
  const record=readPending(kind);
  if(!record)return;
  syncActionLocks();
  setStatus(kind==='guest_order.submit'
    ?'در حال بررسی نتیجه سفارش قبلی…'
    :'در حال بررسی فراخوان قبلی…');
  try{
    const result=await submitAndWait(record);
    if(kind==='guest_order.submit'){
      cart.clear();
      render();
      q('#cartPanel')?.classList.add('hidden');
      setStatus(result.message||`سفارش ${result.order_number||''} ثبت شد.`);
    }else{
      setStatus(result.shared?'فراخوان فعال این میز ثبت است.':'گارسون مطلع شد.');
    }
  }catch(error){
    setStatus(error?.message||'نتیجه درخواست قبلی هنوز مشخص نیست.',true);
  }finally{
    syncActionLocks();
  }
}

orderBtn?.addEventListener('click',async()=>{
  if(!cfg.canOrder||cart.size===0||hasPending('guest_order.submit'))return;
  const items=[...cart.values()].map(line=>({
    id:line.id,
    quantity:line.qty,
    unit_price:line.price,
    note:'',
    fulfillment_mode:'dine_in',
  }));
  const record=makeRecord('guest_order.submit',cfg.tableToken,'',{
    session_token:'',
    customer_note:'',
    items,
  });
  setStatus('در حال ثبت سفارش…');
  try{
    const result=await submitAndWait(record);
    cart.clear();
    render();
    q('#cartPanel')?.classList.add('hidden');
    setStatus(result.message||`سفارش ${result.order_number||''} ثبت شد.`);
  }catch(error){
    setStatus(error?.message||'ثبت سفارش انجام نشد.',true);
  }finally{
    syncActionLocks();
  }
});

waiterBtn?.addEventListener('click',async()=>{
  if(hasPending('waiter_call.create'))return;
  const tableRef=cfg.tableToken?'':String(q('#waiterTable')?.value||'');
  if(!cfg.tableToken&&!tableRef){
    setStatus('اول میز را انتخاب کنید.',true);
    return;
  }
  const record=makeRecord('waiter_call.create',cfg.tableToken,tableRef,{session_token:''});
  setStatus('در حال ارسال فراخوان…');
  try{
    const result=await submitAndWait(record);
    setStatus(result.shared?'فراخوان فعال این میز ثبت است.':'گارسون مطلع شد.');
  }catch(error){
    setStatus(error?.message||'فراخوان انجام نشد.',true);
  }finally{
    syncActionLocks();
  }
});

render();
syncActionLocks();
void resumePending('guest_order.submit');
void resumePending('waiter_call.create');

})();
(()=>{
'use strict';
const cfg=window.SOKNA_REMOTE||{};
const tokenKey='sokna-remote-token:'+String(cfg.installationId||'');
const $=s=>document.querySelector(s);
const loginCard=$('#loginCard'),workspace=$('#workspace'),models=$('#models'),connection=$('#connection'),who=$('#who'),err=$('#loginError');
const deferredSection=$('#deferredSection'),deferredForms=$('#deferredForms'),deferredItems=$('#deferredItems'),deferredNotice=$('#deferredNotice');
let token=sessionStorage.getItem(tokenKey)||'',currentUser=null,deferredContext=null;
const esc=v=>String(v??'');
const fa=n=>new Intl.NumberFormat('fa-IR').format(Number(n||0));
const money=n=>fa(n)+' تومان';
const when=v=>{if(!v)return '—';try{return new Date(v.replace(' ','T')+'Z').toLocaleString('fa-IR')}catch{return esc(v)}};
const hasCap=cap=>Array.isArray(currentUser?.capabilities)&&(currentUser.capabilities.includes('*')||currentUser.capabilities.includes(cap));
const localDateTimeValue=()=>{const d=new Date(),off=d.getTimezoneOffset();return new Date(d.getTime()-off*60000).toISOString().slice(0,16)};
const occurredIso=value=>{const d=new Date(value);return Number.isNaN(d.getTime())?new Date().toISOString():d.toISOString()};
const requestId=()=>{if(globalThis.crypto?.randomUUID)return 'd-'+crypto.randomUUID();return 'd-'+Date.now()+'-'+Math.random().toString(16).slice(2)};

async function api(path,options={}){
  const headers={Accept:'application/json',...(options.headers||{})};if(token)headers.Authorization='Bearer '+token;
  const res=await fetch(path,{...options,headers,cache:'no-store'});const data=await res.json().catch(()=>({}));
  if(res.status===401&&token){token='';sessionStorage.removeItem(tokenKey);showLogin();}
  if(!res.ok)throw Object.assign(new Error(data.error||'request_failed'),{status:res.status,data});return data;
}
function showLogin(){loginCard.classList.remove('hidden');workspace.classList.add('hidden')}
function showWorkspace(){loginCard.classList.add('hidden');workspace.classList.remove('hidden')}
function row(a,b){const d=document.createElement('div');d.className='remote-row';const x=document.createElement('span');x.textContent=a;const y=document.createElement('strong');y.textContent=b;d.append(x,y);return d}
function card(title,meta){const c=document.createElement('section');c.className='remote-card';const h=document.createElement('h2');h.textContent=title;c.append(h);if(meta){const m=document.createElement('p');m.className='muted';m.textContent=meta;c.append(m)}return c}
function renderModel(key,data){
  const p=data.payload||{};let c;
  if(key==='operations'){c=card('عملیات روز',data.stale?'داده ممکن است قدیمی باشد':'به‌روز');c.append(row('سفارش فعال',fa((p.orders||[]).length)),row('فراخوان فعال',fa((p.waiter_calls||[]).length)),row('میزها',fa((p.tables||[]).length)))}
  else if(key==='preparation'){c=card('آماده‌سازی',data.stale?'داده قدیمی/خواندنی':'خواندنی');c.append(row('کارهای آماده‌سازی',fa((p.tasks||[]).length)),row('اصلاح‌های باز',fa((p.adjustments||[]).length)))}
  else if(key==='inventory'){c=card('انبار',data.stale?'داده ممکن است قدیمی باشد':'به‌روز');c.append(row('اقلام',fa((p.items||[]).length)),row('کم‌موجودی',fa(p.low_stock_count||0)))}
  else if(key==='inventory_cost'){c=card('ارزش انبار',data.stale?'داده ممکن است قدیمی باشد':'به‌روز');c.append(row('ارزش تقریبی موجودی',money(p.total_stock_value||0)))}
  else if(key==='reports'){c=card('گزارش مدیریتی','نسخه کش‌شده');c.append(row('فروش امروز',money(p.today_summary?.revenue||0)),row('رسید امروز',fa(p.today_summary?.receipts||0)),row('فروش ۳۰ روز',money(p.summary_30?.revenue||0)))}
  if(c){const m=document.createElement('small');m.className='muted';m.textContent='آخرین همگام‌سازی: '+when(data.last_sync_at);c.append(m);models.append(c)}
}

function control(tag,label,name,type='text'){
  const wrap=document.createElement('label'),caption=document.createElement('span'),el=document.createElement(tag);
  caption.textContent=label;el.name=name;el.className='form-control';if(tag==='input')el.type=type;wrap.append(caption,el);return {wrap,el};
}
function option(value,label){const o=document.createElement('option');o.value=String(value);o.textContent=label;return o}
function occurredControl(){
  const x=control('input','زمان وقوع','occurred_at','datetime-local');x.el.value=localDateTimeValue();return x;
}
function deferredCard(title,kind,build){
  const c=card(title,'در صف امن ثبت می‌شود و فقط پس از تأیید Local نهایی است.'),form=document.createElement('form');form.className='remote-form';
  const fields=build(form)||{};const occurred=occurredControl();form.append(occurred.wrap);
  const status=document.createElement('small');status.className='muted';const btn=document.createElement('button');btn.className='btn btn-primary';btn.type='submit';btn.textContent='ثبت برای همگام‌سازی';
  form.append(btn,status);form.addEventListener('submit',async e=>{
    e.preventDefault();btn.disabled=true;status.textContent='در حال ثبت…';
    try{
      const built=fields.payload();const body={request_id:requestId(),kind,created_at:new Date().toISOString(),occurred_at:occurredIso(occurred.el.value),payload:built.payload||built};
      if(built.expected_version!==undefined&&built.expected_version!==null&&String(built.expected_version)!=='')body.expected_version=String(built.expected_version);
      if(built.expected_state!==undefined)body.expected_state=String(built.expected_state);
      const res=await api('/api/v1/deferred/enqueue.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      status.textContent=res.state==='pending_sync'?'ثبت شد؛ منتظر Local است.':'وضعیت: '+esc(res.state);await refreshDeferredList();
    }catch(e){status.textContent=e.status===403?'این عملیات در مسئولیت فعلی شما نیست.':'ثبت انجام نشد؛ اطلاعات را بررسی کنید.'}
    finally{btn.disabled=false}
  });c.append(form);deferredForms.append(c);return c;
}
function renderDeferredForms(ctx,stale){
  deferredForms.replaceChildren();deferredContext=ctx||{};deferredSection.classList.remove('hidden');
  deferredNotice.textContent=stale?'Local در دسترس نیست؛ اطلاعات مرجع ممکن است قدیمی باشد. عملیات ثبت‌شده بعداً در Local دوباره اعتبارسنجی می‌شود.':'Local در دسترس است؛ Deferred همچنان فقط پس از commit Local نهایی می‌شود.';
  const items=Array.isArray(ctx.inventory_items)?ctx.inventory_items:[],groups=Array.isArray(ctx.supply_groups)?ctx.supply_groups:[],counts=Array.isArray(ctx.count_drafts)?ctx.count_drafts:[],subs=Array.isArray(ctx.subscribers)?ctx.subscribers:[],cats=Array.isArray(ctx.expense_categories)?ctx.expense_categories:[];

  if(hasCap('supply.need.defer')){
    deferredCard('درخواست خرید','supply.need.create',form=>{
      const item=control('select','کالای انبار','item'),free=control('input','اگر خارج از فهرست است، نام مورد','free_name'),qty=control('input','مقدار موردنیاز','quantity','number'),dept=control('select','بخش','department'),unit=control('select','واحد مورد خارج فهرست','base_unit'),note=control('input','توضیح','note');
      item.el.append(option(0,'مورد خارج از فهرست'));items.forEach(x=>item.el.append(option(x.id,x.name)));qty.el.min='0';qty.el.step='0.001';[['kitchen','آشپزخانه'],['bar','بار'],['shared','مشترک']].forEach(x=>dept.el.append(option(x[0],x[1])));[['count','عدد'],['g','کیلوگرم'],['ml','لیتر']].forEach(x=>unit.el.append(option(x[0],x[1])));
      form.append(item.wrap,free.wrap,qty.wrap,dept.wrap,unit.wrap,note.wrap);
      return {payload:()=>({inventory_item_id:Number(item.el.value||0),free_name:free.el.value,quantity_major:qty.el.value,department:dept.el.value,base_unit:unit.el.value,note:note.el.value})};
    });
  }

  if(hasCap('supply.manage.defer')&&groups.some(g=>Number(g.uncommitted_quantity_base||0)>0)){
    deferredCard('شروع خرید','supply.status.prepare',form=>{
      const group=control('select','قلم خرید','group');groups.filter(g=>Number(g.uncommitted_quantity_base||0)>0).forEach(g=>group.el.append(option(g.group_key,g.name+' · '+fa(g.uncommitted_quantity_base))));
      form.append(group.wrap);return {payload:()=>{const g=groups.find(x=>x.group_key===group.el.value)||{};return {group_key:group.el.value,expected_quantity_base:Number(g.uncommitted_quantity_base||0)}}};
    });
  }
  if(hasCap('supply.manage.defer')&&groups.some(g=>Number(g.preparing_quantity_base||0)>0)){
    deferredCard('برگرداندن خرید به انتظار','supply.status.return',form=>{
      const group=control('select','قلم در حال خرید','group');groups.filter(g=>Number(g.preparing_quantity_base||0)>0).forEach(g=>group.el.append(option(g.group_key,g.name+' · '+fa(g.preparing_quantity_base))));
      form.append(group.wrap);return {payload:()=>{const g=groups.find(x=>x.group_key===group.el.value)||{};return {group_key:group.el.value,expected_preparing_quantity_base:Number(g.preparing_quantity_base||0),outcome:'returned'}}};
    });
    deferredCard('ثبت تحویل خرید','supply.receipt',form=>{
      const group=control('select','قلم در حال خرید','group'),qty=control('input','مقدار تحویل','quantity','number'),cost=control('input','هزینه کل، اختیاری','total_cost','number'),supplier=control('input','تأمین‌کننده، اختیاری','supplier'),note=control('input','توضیح','note');
      groups.filter(g=>Number(g.preparing_quantity_base||0)>0).forEach(g=>group.el.append(option(g.group_key,g.name)));qty.el.min='0';qty.el.step='0.001';cost.el.min='0';cost.el.step='1';form.append(group.wrap,qty.wrap,cost.wrap,supplier.wrap,note.wrap);
      return {payload:()=>{const g=groups.find(x=>x.group_key===group.el.value)||{};return {group_key:group.el.value,expected_preparing_quantity_base:Number(g.preparing_quantity_base||0),purchase_unit_id:0,unit_count:qty.el.value,total_cost:cost.el.value,supplier:supplier.el.value,note:note.el.value}}};
    });
  }

  if(hasCap('inventory.waste.defer')&&items.length){
    deferredCard('ثبت ضایعات','inventory.waste',form=>{
      const item=control('select','کالا','item'),qty=control('input','مقدار ضایعات','quantity','number'),note=control('input','علت/توضیح','note');items.forEach(x=>item.el.append(option(x.id,x.name)));qty.el.min='0';qty.el.step='0.001';form.append(item.wrap,qty.wrap,note.wrap);
      return {payload:()=>{const x=items.find(v=>String(v.id)===item.el.value)||{};return {payload:{inventory_item_id:Number(item.el.value),quantity_major:qty.el.value,department:x.default_department||'shared',note:note.el.value},expected_version:x.balance_version||''}}};
    });
  }

  const countLines=[];counts.forEach(s=>(s.lines||[]).forEach(l=>countLines.push({...l,session_id:s.id,session_title:s.title})));
  if(hasCap('inventory.count_draft.defer')&&countLines.length){
    deferredCard('ثبت پیشرفت شمارش','inventory.count_draft',form=>{
      const line=control('select','قلم شمارش','line'),actual=control('input','مقدار واقعی','actual','number'),note=control('input','توضیح','note');countLines.forEach(x=>line.el.append(option(x.id,x.session_title+' · '+x.name)));actual.el.min='0';actual.el.step='0.001';form.append(line.wrap,actual.wrap,note.wrap);
      return {payload:()=>{const x=countLines.find(v=>String(v.id)===line.el.value)||{};return {payload:{session_id:Number(x.session_id),line_id:Number(x.id),actual_major:actual.el.value,note:note.el.value},expected_version:x.updated_at||''}}};
    });
  }

  if(hasCap('subscriber.payment.defer')&&subs.length){
    deferredCard('ثبت پرداخت مشترک','subscriber.payment',form=>{
      const sub=control('select','مشترک','subscriber'),amount=control('input','مبلغ پرداخت','amount','number'),reference=control('input','مرجع/یادداشت','reference');subs.forEach(x=>sub.el.append(option(x.id,x.name+' · مانده '+money(x.balance))));amount.el.min='1';amount.el.step='1';form.append(sub.wrap,amount.wrap,reference.wrap);
      return {payload:()=>{const x=subs.find(v=>String(v.id)===sub.el.value)||{};return {subscriber_id:Number(sub.el.value),amount:Number(amount.el.value||0),expected_balance:Number(x.balance||0),reference:reference.el.value}}};
    });
  }

  if(hasCap('expense.create.defer')&&cats.length){
    deferredCard('ثبت هزینه عمومی','expense.create',form=>{
      const cat=control('select','دسته هزینه','category'),amount=control('input','مبلغ','amount','number'),desc=control('input','توضیح','description');cats.forEach(x=>cat.el.append(option(x.category_key,x.name)));amount.el.min='1';amount.el.step='1';form.append(cat.wrap,amount.wrap,desc.wrap);
      return {payload:()=>({category_key:cat.el.value,amount:Number(amount.el.value||0),description:desc.el.value})};
    });
  }
  if(!deferredForms.children.length){deferredSection.classList.add('hidden')}
}

async function refreshDeferredList(){
  if(!token||deferredSection.classList.contains('hidden'))return;
  try{
    const data=await api('/api/v1/deferred/list.php');deferredItems.replaceChildren();
    const labels={pending_sync:'در انتظار همگام‌سازی',needs_review:'نیاز به بررسی مدیر',committed:'ثبت نهایی',rejected:'رد شده'};
    if(!(data.items||[]).length){const p=document.createElement('p');p.className='muted';p.textContent='هنوز کاری در صف راه‌دور ثبت نشده است.';deferredItems.append(p);return}
    (data.items||[]).forEach(x=>{const d=row(x.kind,labels[x.state]||x.state);d.querySelector('strong').className='remote-state '+x.state;const small=document.createElement('small');small.className='muted';small.textContent=' · '+when(x.occurred_at);d.querySelector('span').append(small);deferredItems.append(d)});
  }catch(_){}
}

async function load(){
  if(!token){showLogin();return}
  try{
    const me=await api('/api/v1/remote/me.php');currentUser=me.user||{};showWorkspace();who.textContent=currentUser.display_name||currentUser.projection_id||'';
    connection.className=me.connectivity?.local_fresh?'remote-ok':'remote-stale';
    connection.textContent=me.connectivity?.local_fresh?'اتصال Local برقرار است.':'Local در دسترس نیست؛ داده‌های خواندنی کش‌شده‌اند و کارهای Deferred بعداً بررسی می‌شوند.';
    models.replaceChildren();deferredSection.classList.add('hidden');
    for(const m of me.models||[]){
      try{
        const data=await api('/api/v1/remote/read.php?model='+encodeURIComponent(m.model_key));
        if(m.model_key==='deferred_context')renderDeferredForms(data.payload||{},Boolean(data.stale));
        else renderModel(m.model_key,data);
      }catch(_){}
    }
    await refreshDeferredList();
  }catch(e){if(e.status!==401){err.textContent='دریافت اطلاعات راه‌دور انجام نشد.';showLogin()}}
}
$('#loginBtn').addEventListener('click',async()=>{
  err.textContent='';try{
    const data=await api('/api/v1/auth/login.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({installation_id:String(cfg.installationId||''),username:$('#username').value,password:$('#password').value})});
    token=data.token;sessionStorage.setItem(tokenKey,token);await load();
  }catch(_){err.textContent='نام کاربری یا رمز عبور معتبر نیست.'}
});
$('#logoutBtn').addEventListener('click',async()=>{try{await api('/api/v1/auth/logout.php',{method:'POST'})}catch(_){}token='';currentUser=null;sessionStorage.removeItem(tokenKey);showLogin()});
setInterval(()=>{void refreshDeferredList()},10000);
void load();
})();

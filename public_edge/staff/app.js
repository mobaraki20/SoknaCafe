(()=>{
'use strict';
const cfg=window.SOKNA_REMOTE||{};
const tokenKey='sokna-remote-token:'+String(cfg.installationId||'');
const $=s=>document.querySelector(s);
const loginCard=$('#loginCard'),workspace=$('#workspace'),models=$('#models'),connection=$('#connection'),who=$('#who'),err=$('#loginError');
let token=sessionStorage.getItem(tokenKey)||'';
const esc=v=>String(v??'');
const fa=n=>new Intl.NumberFormat('fa-IR').format(Number(n||0));
const money=n=>fa(n)+' تومان';
const when=v=>{if(!v)return '—';try{return new Date(v.replace(' ','T')+'Z').toLocaleString('fa-IR')}catch{return esc(v)}};
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
  else if(key==='reports'){c=card('گزارش مدیریتی',data.stale?'نسخه کش‌شده':'نسخه کش‌شده');c.append(row('فروش امروز',money(p.today_summary?.revenue||0)),row('رسید امروز',fa(p.today_summary?.receipts||0)),row('فروش ۳۰ روز',money(p.summary_30?.revenue||0)))}
  if(c){const m=document.createElement('small');m.className='muted';m.textContent='آخرین همگام‌سازی: '+when(data.last_sync_at);c.append(m);models.append(c)}
}
async function load(){
  if(!token){showLogin();return}
  try{
    const me=await api('/api/v1/remote/me.php');showWorkspace();who.textContent=me.user?.display_name||me.user?.projection_id||'';
    connection.className=me.connectivity?.local_fresh?'remote-ok':'remote-stale';
    connection.textContent=me.connectivity?.local_fresh?'اتصال Local برقرار است.':'Local در دسترس نیست؛ داده‌های زیر فقط نسخه کش‌شده‌اند.';
    models.replaceChildren();
    for(const m of me.models||[]){try{renderModel(m.model_key,await api('/api/v1/remote/read.php?model='+encodeURIComponent(m.model_key)))}catch(_){}}
  }catch(e){if(e.status!==401){err.textContent='دریافت اطلاعات راه‌دور انجام نشد.';showLogin()}}
}
$('#loginBtn').addEventListener('click',async()=>{
  err.textContent='';try{
    const data=await api('/api/v1/auth/login.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({installation_id:String(cfg.installationId||''),username:$('#username').value,password:$('#password').value})});
    token=data.token;sessionStorage.setItem(tokenKey,token);await load();
  }catch(_){err.textContent='نام کاربری یا رمز عبور معتبر نیست.'}
});
$('#logoutBtn').addEventListener('click',async()=>{try{await api('/api/v1/auth/logout.php',{method:'POST'})}catch(_){}token='';sessionStorage.removeItem(tokenKey);showLogin()});
void load();
})();

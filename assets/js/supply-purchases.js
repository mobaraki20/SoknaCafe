(()=>{
'use strict';
const needs=Array.isArray(window.SOKNA_PURCHASE_NEEDS)?window.SOKNA_PURCHASE_NEEDS:[];
const items=Array.isArray(window.SOKNA_PURCHASE_ITEMS)?window.SOKNA_PURCHASE_ITEMS:[];
const layer=document.getElementById('purchaseReceiveLayer'),form=document.getElementById('purchaseReceiveForm'),groupKey=document.getElementById('purchaseGroupKey'),expectedPreparing=document.getElementById('purchaseExpectedPreparing'),title=document.getElementById('purchaseReceiveTitle'),summary=document.getElementById('purchaseReceiveSummary'),unit=document.getElementById('purchaseUnit'),unitCount=document.getElementById('purchaseUnitCount'),unitCountLabel=document.getElementById('purchaseUnitCountLabel'),unitLabel=document.getElementById('purchaseUnitLabel'),actualGroup=document.getElementById('purchaseActualGroup'),actual=document.getElementById('purchaseActual'),actualLabel=document.getElementById('purchaseActualLabel'),targetGroup=document.getElementById('purchaseTargetGroup'),target=document.getElementById('purchaseTargetItem'),token=document.getElementById('purchaseRequestToken'),submit=document.getElementById('purchaseReceiveSubmit'),fillPrepared=document.getElementById('purchaseFillPrepared');
let active=null;
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const makeToken=()=>{const b=new Uint8Array(16);crypto.getRandomValues(b);return [...b].map(x=>x.toString(16).padStart(2,'0')).join('')};
const selectedItem=()=>{if(!active)return null;const tid=Number(target?.value||0);return items.find(x=>Number(x.id)===(tid||Number(active.item_id)))||null};
const clearQuantities=()=>{if(unitCount)unitCount.value='';if(actual)actual.value=''};
const syncUnit=(clear=true)=>{
  const opt=unit?.selectedOptions?.[0];const mode=opt?.dataset.mode||'base';const it=selectedItem();const baseLabel=it?.unit_label||active?.unit_label||'';
  actualGroup?.classList.toggle('hidden',mode!=='actual_quantity');if(actual)actual.required=mode==='actual_quantity';
  if(unitCountLabel)unitCountLabel.textContent=mode==='base'?'مقدار واقعی تحویل‌شده':'تعداد بسته/واحد خرید';
  if(unitLabel)unitLabel.textContent=mode==='base'?baseLabel:(opt?.textContent||'');
  if(actualLabel)actualLabel.textContent=baseLabel;
  fillPrepared?.classList.toggle('hidden',mode!=='base');
  if(clear)clearQuantities();
};
const fillUnits=()=>{
  if(!unit||!active)return;
  const it=selectedItem();const baseLabel=it?.unit_label||active.unit_label||'';
  const units=it?.units||active.units||[];
  unit.innerHTML=`<option value="0" data-mode="base">مستقیم · ${esc(baseLabel)}</option>`+units.map(u=>`<option value="${Number(u.id)}" data-mode="${esc(u.mode)}">${esc(u.name)}</option>`).join('');
  syncUnit(true);
};
const open=(key,trigger)=>{
  active=needs.find(n=>String(n.group_key)===String(key));if(!active||!layer||!form)return;
  groupKey.value=active.group_key;if(expectedPreparing)expectedPreparing.value=String(active.preparing_quantity_base??'');token.value=makeToken();
  title.textContent=active.unknown?'ثبت تحویل و اتصال به انبار':'ثبت تحویل';
  summary.textContent=`${active.name} · در حال خرید ${active.preparing_label} · مقدار واقعی تحویل را وارد کن.`;
  targetGroup?.classList.toggle('hidden',!active.unknown);
  if(active.unknown&&target){
    target.innerHTML='<option value="0">ایجاد کالای جدید با همین نام</option>'+items.filter(i=>i.base_unit===active.base_unit).map(i=>`<option value="${Number(i.id)}">${esc(i.name)}</option>`).join('');
  }
  fillUnits();
  form.querySelector('[name="total_cost"]').value='';form.querySelector('[name="supplier"]').value='';form.querySelector('[name="occurred_date_j"]').value='';form.querySelector('[name="occurred_time"]').value='';form.querySelector('[name="note"]').value='';
  submit.disabled=false;submit.textContent='ثبت تحویل و ورود انبار';
  window.CafeUI?.dialog?.open(layer,trigger);if(!window.CafeUI?.dialog){layer.classList.remove('hidden');layer.setAttribute('aria-hidden','false')}
  // Mobile-first: do not summon the keyboard before the buyer has reviewed unit and context.
  unitCount?.blur?.();
};
document.querySelectorAll('[data-open-purchase-receive]').forEach(b=>b.addEventListener('click',()=>open(b.dataset.openPurchaseReceive,b)));
document.querySelectorAll('[data-open-purchase-more]').forEach(b=>b.addEventListener('click',()=>{const box=document.querySelector(`[data-purchase-more="${CSS.escape(b.dataset.openPurchaseMore)}"]`);if(!box)return;const show=box.classList.toggle('hidden')===false;b.setAttribute('aria-expanded',show?'true':'false')}));
unit?.addEventListener('change',()=>syncUnit(true));
unit?.addEventListener('panel:choice-commit',()=>{syncUnit(false);if(!(window.CafeUI?.keyboard?.isTouchContext?.() ?? window.matchMedia('(pointer: coarse)').matches)){requestAnimationFrame(()=>{unitCount?.focus();unitCount?.select?.();});}});
target?.addEventListener('change',()=>fillUnits());
fillPrepared?.addEventListener('click',()=>{const mode=unit?.selectedOptions?.[0]?.dataset.mode||'base';if(mode!=='base'||!active)return;unitCount.value=String(active.preparing_major??'');if(!(window.CafeUI?.keyboard?.isTouchContext?.() ?? window.matchMedia('(pointer: coarse)').matches)){unitCount.focus();unitCount.select?.()}});
form?.addEventListener('submit',()=>{submit.disabled=true;submit.textContent='در حال ثبت…'});
document.getElementById('sharePurchaseList')?.addEventListener('click',async()=>{const lines=[...document.querySelectorAll('[data-purchase-share]')].map(x=>'• '+x.dataset.purchaseShare);if(!lines.length){window.CafeUI?.toast?.('موردی در حال خرید نیست.');return}const text='در حال خرید — سکنا\n'+lines.join('\n');try{if(navigator.share)await navigator.share({title:'فهرست در حال خرید سکنا',text});else{await navigator.clipboard.writeText(text);window.CafeUI?.toast?.('فهرست در حال خرید کپی شد.')}}catch(err){if(err?.name!=='AbortError')window.CafeUI?.toast?.('اشتراک لیست انجام نشد.')}});
})();

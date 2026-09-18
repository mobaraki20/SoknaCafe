(()=>{
  'use strict';
  const catalog=Array.isArray(window.SOKNA_SUPPLY_CATALOG)?window.SOKNA_SUPPLY_CATALOG:[];
  const openNeeds=Array.isArray(window.SOKNA_SUPPLY_OPEN)?window.SOKNA_SUPPLY_OPEN:[];
  const restoredDraft=Array.isArray(window.SOKNA_SUPPLY_DRAFT)?window.SOKNA_SUPPLY_DRAFT:[];
  const search=document.getElementById('supplyNeedSearch'),results=document.getElementById('supplyNeedResults'),freeAdd=document.getElementById('supplyFreeAdd'),list=document.getElementById('supplyDraftList'),empty=document.getElementById('supplyDraftEmpty'),submit=document.getElementById('supplySubmit'),submitBar=submit?.closest('.supply-submit-bar'),count=document.getElementById('supplyDraftCount'),clear=document.getElementById('supplyDraftClear');
  if(!search||!results||!list)return;
  const rows=new Map();
  const MAX_ROWS=20;
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const norm=v=>String(v??'').trim().toLocaleLowerCase('fa');
  const fa=n=>Number(n||0).toLocaleString('fa-IR');
  const icon=name=>window.SoknaIcons?.markup(name)||'';
  const unitOptions=(selected='count')=>[['g','کیلوگرم'],['ml','لیتر'],['count','عدد / بسته']].map(([v,l])=>`<option value="${v}"${v===selected?' selected':''}>${l}</option>`).join('');
  const sync=()=>{const n=rows.size;empty?.classList.toggle('hidden',n>0);if(submit){submit.disabled=n<1;submit.textContent=n?`ثبت ${fa(n)} درخواست خرید`:'ثبت درخواست خرید';}submitBar?.classList.toggle('hidden',n<1);count.textContent=n?`${fa(n)} قلم آماده ثبت`:'هنوز موردی اضافه نشده';clear?.classList.toggle('hidden',n<1);};
  const touchContext=()=>window.CafeUI?.keyboard?.isTouchContext?.() ?? window.matchMedia('(pointer: coarse)').matches;
  const focusQty=key=>{if(touchContext())return;requestAnimationFrame(()=>{const el=list.querySelector(`[data-supply-row="${CSS.escape(key)}"] .supply-need-qty`);el?.focus();el?.select?.();});};
  const highlightExisting=key=>{const row=rows.get(key);if(!row)return;row.classList.remove('is-duplicate');void row.offsetWidth;row.classList.add('is-duplicate');row.scrollIntoView({block:'nearest'});setTimeout(()=>row.classList.remove('is-duplicate'),1300);focusQty(key);};
  const canAdd=()=>{if(rows.size<MAX_ROWS)return true;window.CafeUI?.toast?.('در هر ثبت حداکثر ۲۰ قلم می‌توانی اضافه کنی.','warning');return false;};
  const qtyChips=()=>'<span class="supply-qty-chips" aria-label="مقدارهای سریع"><button type="button" data-supply-qty-chip="1">۱</button><button type="button" data-supply-qty-chip="2">۲</button><button type="button" data-supply-qty-chip="5">۵</button></span>';
  const noteMarkup=value=>`<details class="supply-line-note"><summary>+ توضیح، در صورت نیاز</summary><input class="form-control" name="line_note[]" maxlength="500" autocomplete="off" value="${esc(value||'')}" placeholder="مثلاً برند، اندازه یا فوریت"></details>`;
  const knownCopy=item=>{
    if(item.preparing_quantity_label&&item.open_quantity_label)return `در حال خرید: ${esc(item.preparing_quantity_label)} · نیاز اضافه: ${esc(item.open_quantity_label)}`;
    if(item.preparing_quantity_label)return `در حال خرید: ${esc(item.preparing_quantity_label)} · مقدار جدید جداگانه به فهرست خرید اضافه می‌شود`;
    if(item.open_quantity_label)return `در انتظار خرید: ${esc(item.open_quantity_label)} · مقدار جدید همین درخواست را به‌روزرسانی می‌کند`;
    return `موجودی سیستم: ${esc(item.current)}`;
  };
  const addItem=(item,qty=null,note=null)=>{
    const key=`i:${item.id}`;
    if(rows.has(key)){highlightExisting(key);return;}if(!canAdd())return;
    const initialQty=qty!==null?qty:(item.open_quantity||'');
    const initialNote=note!==null?note:(item.open_note||'');
    const row=document.createElement('div');row.className='supply-draft-row';row.dataset.supplyRow=key;
    row.innerHTML=`<div class="supply-draft-copy"><strong>${esc(item.name)}</strong><small>${knownCopy(item)}</small>${noteMarkup(initialNote)}</div><label class="supply-qty-field"><span>مقدار موردنیاز</span><span class="supply-qty-control"><input class="form-control supply-need-qty" name="quantity_major[]" inputmode="decimal" enterkeyhint="next" autocomplete="off" value="${esc(initialQty)}" required><em>${esc(item.unit_label)}</em></span>${qtyChips()}</label><button class="supply-row-remove" type="button" aria-label="حذف ${esc(item.name)}">${icon('trash')}</button><input type="hidden" name="inventory_item_id[]" value="${Number(item.id)}"><input type="hidden" name="free_name[]" value=""><input type="hidden" name="base_unit[]" value="${esc(item.base_unit)}">`;
    row.querySelector('.supply-row-remove')?.addEventListener('click',()=>{rows.delete(key);row.remove();sync();});
    list.appendChild(row);rows.set(key,row);sync();focusQty(key);
  };
  const addFree=(name,baseUnit='count',qty='',note='',preparingLabel='')=>{
    name=String(name||'').trim();if(!name)return;
    const key=`f:${norm(name)}`;if(rows.has(key)){highlightExisting(key);return;}if(!canAdd())return;
    const copy=preparingLabel?`خارج از فهرست · ${esc(preparingLabel)} در حال خرید است؛ مقدار جدید جداگانه اضافه می‌شود`:'خارج از فهرست · فقط اگر خرید شد به انبار متصل یا ساخته می‌شود';
    const row=document.createElement('div');row.className='supply-draft-row is-free';row.dataset.supplyRow=key;
    row.innerHTML=`<div class="supply-draft-copy"><strong>${esc(name)}</strong><small>${copy}</small>${noteMarkup(note)}</div><label class="supply-qty-field"><span>مقدار موردنیاز</span><span class="supply-qty-control"><input class="form-control supply-need-qty" name="quantity_major[]" inputmode="decimal" enterkeyhint="next" autocomplete="off" value="${esc(qty)}" required><select class="form-control supply-unit-select" name="base_unit[]">${unitOptions(baseUnit)}</select></span>${qtyChips()}</label><button class="supply-row-remove" type="button" aria-label="حذف ${esc(name)}">${icon('trash')}</button><input type="hidden" name="inventory_item_id[]" value="0"><input type="hidden" name="free_name[]" value="${esc(name)}">`;
    row.querySelector('.supply-row-remove')?.addEventListener('click',()=>{rows.delete(key);row.remove();sync();});
    list.appendChild(row);rows.set(key,row);sync();focusQty(key);
  };
  const renderResults=()=>{
    const q=norm(search.value);const matches=catalog.filter(x=>!q||norm(x.name).includes(q)).slice(0,q?8:5);
    results.innerHTML=matches.map(x=>`<button type="button" data-supply-item="${Number(x.id)}"><span><strong>${esc(x.name)}</strong><small>${x.preparing_quantity_label?`در حال خرید ${esc(x.preparing_quantity_label)}${x.open_quantity_label?` · نیاز اضافه ${esc(x.open_quantity_label)}`:''}`:(x.open_need_id?`در انتظار خرید ${esc(x.open_quantity_label)}`:esc(x.current))}</small></span><b>افزودن</b></button>`).join('');
    results.querySelectorAll('[data-supply-item]').forEach(btn=>btn.addEventListener('click',()=>{const item=catalog.find(x=>Number(x.id)===Number(btn.dataset.supplyItem));if(item){addItem(item);search.value='';renderResults();if(!touchContext())search.focus();}}));
    const exact=catalog.some(x=>norm(x.name)===q);freeAdd?.classList.toggle('hidden',!q||exact);if(freeAdd&&!freeAdd.classList.contains('hidden'))freeAdd.querySelector('span').textContent=search.value.trim();
  };
  search.addEventListener('input',renderResults);search.addEventListener('focus',renderResults);
  freeAdd?.addEventListener('click',()=>{addFree(search.value);search.value='';renderResults();if(!touchContext())search.focus();});
  clear?.addEventListener('click',()=>{rows.clear();list.querySelectorAll('.supply-draft-row').forEach(x=>x.remove());sync();if(!touchContext())search.focus();});
  list.addEventListener('click',event=>{const chip=event.target.closest('[data-supply-qty-chip]');if(!chip)return;const input=chip.closest('.supply-qty-field')?.querySelector('.supply-need-qty');if(input){input.value=chip.dataset.supplyQtyChip||'';input.dispatchEvent(new Event('input',{bubbles:true}));}});
  list.addEventListener('keydown',event=>{if(event.key!=='Enter'||event.isComposing||!event.target.matches('.supply-need-qty'))return;event.preventDefault();const inputs=[...list.querySelectorAll('.supply-need-qty')],index=inputs.indexOf(event.target),next=inputs.slice(index+1).find(input=>!String(input.value).trim())||inputs[index+1];if(next){next.focus();next.select?.();}else submit?.focus();});
  document.querySelectorAll('[data-edit-supply-need]').forEach(btn=>btn.addEventListener('click',()=>{
    const need=openNeeds.find(x=>Number(x.id)===Number(btn.dataset.editSupplyNeed));if(!need)return;
    if(Number(need.item_id)>0){const item=catalog.find(x=>Number(x.id)===Number(need.item_id));if(item)addItem(item,need.uncommitted_major||'',need.note||'');}
    else addFree(need.name,need.base_unit,need.uncommitted_major||'',need.note||'',need.preparing_label||'');
    document.getElementById('supplyNeedForm')?.scrollIntoView({behavior:'smooth',block:'start'});
  }));
  document.getElementById('supplyNeedForm')?.addEventListener('submit',()=>{if(submit.disabled)return;submit.disabled=true;submit.textContent='در حال ثبت…';});
  document.addEventListener('click',e=>{if(!e.target.closest('.supply-search')&&!e.target.closest('.supply-search-results')&&!e.target.closest('#supplyFreeAdd'))results.innerHTML='';});
  restoredDraft.slice(0,MAX_ROWS).forEach(entry=>{const item=catalog.find(row=>Number(row.id)===Number(entry.item_id));if(item)addItem(item,entry.quantity??'',entry.note??'');else if(entry.name)addFree(entry.name,entry.base_unit||'count',entry.quantity??'',entry.note??'');});
  sync();
})();

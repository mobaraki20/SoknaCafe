(() => {
  'use strict';
  const form = document.querySelector('[data-print-template-form]');
  const preview = document.querySelector('[data-thermal-preview]');
  const samplesNode = document.getElementById('print-template-samples');
  const destinationsNode = document.getElementById('print-template-preview-destinations');
  const modeNode = document.querySelector('[data-preview-mode]');
  if (!form || !preview || !samplesNode) return;
  let samples = {}; let destinations = [];
  try { samples = JSON.parse(samplesNode.textContent || '{}'); destinations = JSON.parse(destinationsNode?.textContent || '[]'); } catch { return; }
  const scenario = document.querySelector('[data-preview-scenario]');
  const widthSelect = document.querySelector('[data-preview-width]');
  const destinationSelect = document.querySelector('[data-preview-destination]');
  const fa = value => String(value ?? '').replace(/\d/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const money = value => fa(Number(value || 0).toLocaleString('en-US')).replace(/,/g,'٬');
  const enNumber = input => Number(String(input?.value || '').replace(/[۰-۹]/g,d=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[^0-9.-]/g,'')) || 0;
  const field = name => form.elements.namedItem(name);
  const checked = name => Boolean(field(name)?.checked);
  const value = (name,fallback='') => String(field(name)?.value ?? fallback);
  const currentOrder = () => { try { const v=JSON.parse(String(form.querySelector('[data-reorder-output]')?.value||'[]')); return Array.isArray(v)?v:[]; } catch { return []; } };
  const labels = () => {
    const out={}; form.querySelectorAll('[name^="labels["]').forEach(el=>{ const m=el.name.match(/^labels\[([^\]]+)\]$/); if(m)out[m[1]]=el.value; }); return out;
  };
  const el = (tag, cls, text) => { const node=document.createElement(tag); if(cls)node.className=cls; if(text!==undefined)node.textContent=text; return node; };
  const separator = design => { const node=el('div','tp-separator'); node.dataset.style=design.separator; return node; };
  const addMeta = (root,data,isPrep) => {
    const meta=el('div','tp-meta');
    if (isPrep) {
      meta.append(el('strong','',data.table_name || '—'));
      if (checked('show_order_number')) meta.append(el('strong','',`سفارش ${data.order_number || '—'}`));
    } else {
      meta.append(el('strong','',data.invoice_number || data.badge || 'فاکتور'));
      meta.append(el('span','',data.table_name || '—'));
    }
    if (checked('show_time')) meta.append(el('span','',data.display_date || ''));
    if (checked('show_actor') && data.actor_name) meta.append(el('span','',`ثبت‌کننده: ${data.actor_name}`));
    root.append(meta);
  };
  const renderCustomerItems = (root,data,width,layout) => {
    const items=(data.sections?.[0]?.items)||[];
    let effective=layout;
    if(effective==='responsive-receipt') effective=width===58?'two-line':'columnar';
    if(width===58 && effective==='columnar') effective='columnar-compact';
    const table=el('div',`tp-items tp-items-${effective} tp-width-${width}`);
    if(effective==='columnar' || effective==='columnar-compact'){
      const header=el('div','tp-column-head');
      if(effective==='columnar') header.append(el('span','', 'شرح'),el('span','', 'تعداد'),el('span','', 'فی'),el('span','', 'مبلغ'));
      else header.append(el('span','', 'شرح'),el('span','', 'تعداد × فی'),el('span','', 'مبلغ'));
      table.append(header);
      items.forEach(item=>{
        const row=el('div','tp-item is-columnar');
        row.append(el('strong','tp-item-name',item.name||'—'));
        if(effective==='columnar'){
          row.append(el('span','tp-qty-cell',fa(item.quantity)),el('span','tp-unit-price',money(item.unit_price)),el('b','tp-line-total',money(item.line_total)));
        }else{
          row.append(el('span','tp-calc-cell',`${fa(item.quantity)} × ${money(item.unit_price)}`),el('b','tp-line-total',money(item.line_total)));
        }
        table.append(row);
      });
    }else{
      items.forEach(item=>{
        const row=el('div','tp-item');
        const head=el('div','tp-item-head'); head.append(el('strong','tp-item-name',item.name||'—'),el('b','tp-line-total',money(item.line_total)));
        row.append(head,el('div','tp-item-calc',`${fa(item.quantity)} × ${money(item.unit_price)}`));table.append(row);
      });
    }
    root.append(table);
  };
  const renderSummary = (root,data,labelMap) => {
    const box=el('div','tp-summary');
    if (Number(data.discount||0)>0) {
      const s=el('div','');s.append(el('span','',labelMap.subtotal||'جمع اقلام'),el('strong','',`${money(data.subtotal)} تومان`));box.append(s);
      const d=el('div','');d.append(el('span','',labelMap.discount||'تخفیف'),el('strong','',`− ${money(data.discount)} تومان`));box.append(d);
    }
    const total=el('div','tp-total');total.append(el('span','',labelMap.total||'جمع نهایی'),el('strong','',`${money(data.total)} تومان`));box.append(total);root.append(box);
  };
  const renderPrepItems = (root,data,labelMap) => {
    (data.sections||[]).forEach(section=>{
      if (checked('show_section_titles')) root.append(el('div','tp-section-title',section.title||''));
      const items=el('div','tp-prep-items');
      (section.items||[]).forEach(item=>{
        const row=el('div','tp-prep-item');
        const main=el('div','tp-prep-main'); main.append(el('b','tp-qty',`${fa(item.quantity)} ×`),el('strong','',item.name||'—')); row.append(main);
        if (item.previous_quantity !== undefined && item.previous_quantity !== null) row.append(el('div','tp-adjust',`قبلی ${fa(item.previous_quantity)} ← جدید ${fa(item.quantity)}`));
        if (item.fulfillment_mode==='takeaway' || String(item.name||'').includes('بیرون‌بر')) row.append(el('div','tp-takeaway',labelMap.takeaway||'بیرون‌بر'));
        if (item.note) row.append(el('div','tp-note',`${labelMap.note||'یادداشت'}: ${item.note}`));
        items.append(row);
      });root.append(items);
    });
  };
  let exactTimer=0; let exactRevision=0; let exactController=null;
  const draftTemplate = width => ({
    format:'sokna-print-template-v1',package_format:'sokna-print-template-package-v2',template_key:value('template_key','customer'),
    name:value('name','پیش‌نویس'),version:'draft-preview',paper_width_mm:width,
    base_font_size:Math.max(18,enNumber(field('base_font_size'))),title_font_size:Math.max(22,enNumber(field('title_font_size'))),
    table_font_size:Math.max(24,enNumber(field('table_font_size'))),line_spacing:Math.max(2,enNumber(field('line_spacing'))),
    margin:Math.max(4,enNumber(field('margin'))),show_actor:checked('show_actor'),show_time:checked('show_time'),
    show_order_number:checked('show_order_number'),show_section_titles:checked('show_section_titles'),
    show_prices:value('template_key','customer')==='customer',footer:value('footer',''),
    design:{format:'sokna-print-design-v2',density:value('design[density]','compact'),header_alignment:value('design[header_alignment]','center'),
      separator_style:value('design[separator_style]','solid'),item_layout:value('design[item_layout]','responsive-receipt'),
      section_order:currentOrder(),labels:labels()}
  });
  const exactSessionId = `preview-${crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;
  const fetchWithDeadline = async (url, options={}, timeoutMs=2200) => {
    const controller=new AbortController();
    const outer=options.signal; const abort=()=>controller.abort();
    if(outer?.aborted)controller.abort(); else outer?.addEventListener('abort',abort,{once:true});
    const timer=setTimeout(()=>controller.abort(),timeoutMs);
    try{return await fetch(url,{...options,signal:controller.signal});}
    finally{clearTimeout(timer);outer?.removeEventListener?.('abort',abort);}
  };
  const requestExact = () => {
    // Invalidate the previous response immediately when the form changes; debounce only delays I/O.
    const revision=++exactRevision;
    exactController?.abort(); exactController=new AbortController();
    clearTimeout(exactTimer);
    exactTimer=window.setTimeout(async()=>{
      const width=Number(widthSelect?.value || value('paper_width_mm','80'))===58?58:80;
      const selectedKey=String(destinationSelect?.value||'');
      const candidates=destinations.filter(d=>Number(d.paper_width_mm)===width && (!selectedKey||String(d.destination_key)===selectedKey));
      if(!selectedKey){ if(modeNode)modeNode.textContent='پیش‌نمایش تقریبی — برای حالت دقیق مقصد واقعی را انتخاب کنید'; return; }
      if(!candidates.length){ if(modeNode)modeNode.textContent='پیش‌نمایش تقریبی — مقصد منتخب با این عرض سازگار نیست'; return; }
      try{
        const endpoint=String(window.SOKNA_PRINT_BRIDGE_CAPABILITY_URL||'');
        if(!endpoint)throw new Error('bridge_unavailable');
        const capResponse=await fetchWithDeadline(`${endpoint}?destinations=${encodeURIComponent(selectedKey)}`,{credentials:'same-origin',cache:'no-store',signal:exactController.signal,headers:{Accept:'application/json'}},1800);
        const cap=await capResponse.json(); if(!capResponse.ok||!cap.success||!Array.isArray(cap.bridges)||!cap.bridges.length)throw new Error('bridge_unavailable');
        const bridge=cap.bridges.find(b=>String(b.destination_key)===selectedKey); if(!bridge)throw new Error('bridge_unavailable');
        const profile=bridge.render_profile;
        if(!bridge.exact_preview_ready||!profile||!Number.isFinite(Number(profile.dpi_x))||!Number.isFinite(Number(profile.dpi_y))){
          if(modeNode)modeNode.textContent='پیش‌نمایش تقریبی — Agent هنوز RenderProfile تأییدشدهٔ این مقصد را ارائه نمی‌کند'; return;
        }
        const data=structuredClone(samples[scenario?.value || 'default'] || Object.values(samples)[0] || {}); data.template=draftTemplate(width);
        const body={type:'print.preview',protocol_version:1,session_id:exactSessionId,revision,payload_json:JSON.stringify(data),paper_width_mm:Number(bridge.paper_width_mm),printable_width_mm:Number(bridge.printable_width_mm),dpi_x:Number(profile.dpi_x),dpi_y:Number(profile.dpi_y)};
        const options={method:'POST',mode:'cors',cache:'no-store',signal:exactController.signal,headers:{'Content-Type':'application/json','X-Sokna-Bridge-Pairing':String(bridge.pairing_id)},body:JSON.stringify(body)}; options.targetAddressSpace='local';
        const response=await fetchWithDeadline(`http://127.0.0.1:${Number(bridge.port)}/v1/preview`,options,3500);
        const result=await response.json();
        if(revision!==exactRevision||!response.ok||!result.success||Number(result.revision)!==revision||String(result.session_id)!==exactSessionId)return;
        const base64=String(result.image_base64||''); if(base64.length<16||base64.length>8_000_000)throw new Error('preview_image_invalid');
        const binary=atob(base64.slice(0,32)); if(!binary.startsWith('\x89PNG\r\n\x1a\n'))throw new Error('preview_image_invalid');
        const img=new Image();img.alt='پیش‌نمایش دقیق چاپ';img.src=`data:image/png;base64,${base64}`;img.style.maxWidth='100%';img.style.height='auto';
        preview.replaceChildren(img);preview.className=`thermal-preview is-${width} is-exact`;
        if(modeNode)modeNode.textContent=`پیش‌نمایش دقیق · ${bridge.destination_label||bridge.destination_key} · ${result.width}×${result.height}px · ${result.dpi_x}×${result.dpi_y} DPI`;
      }catch(error){if(error?.name==='AbortError')return;if(revision===exactRevision&&modeNode)modeNode.textContent='پیش‌نمایش تقریبی — Renderer محلی در دسترس یا آماده نیست';}
    },280);
  };

  const render = () => {
    const data=structuredClone(samples[scenario?.value || 'default'] || Object.values(samples)[0] || {});
    const width=Number(widthSelect?.value || value('paper_width_mm','80'))===58?58:80;
    const design={density:value('design[density]','compact'),layout:value('design[item_layout]','responsive-receipt'),separator:value('design[separator_style]','solid')};
    const labelMap=labels();
    preview.replaceChildren(); preview.className=`thermal-preview is-${width} density-${design.density}`;
    preview.style.setProperty('--tp-base',`${Math.max(18,enNumber(field('base_font_size')))}px`);
    preview.style.setProperty('--tp-title',`${Math.max(22,enNumber(field('title_font_size')))}px`);
    preview.style.setProperty('--tp-table',`${Math.max(24,enNumber(field('table_font_size')))}px`);
    preview.style.setProperty('--tp-gap',`${Math.max(2,enNumber(field('line_spacing')))}px`);
    preview.style.setProperty('--tp-margin',`${Math.max(4,enNumber(field('margin')))}px`);
    const brand=el('div','tp-brand'); brand.style.textAlign=value('design[header_alignment]','center')==='right'?'right':'center'; brand.append(el('strong','',data.title||'کافه سکنا')); preview.append(brand);
    const order=currentOrder();
    if(modeNode)modeNode.textContent='پیش‌نمایش تقریبی مرورگر — در انتظار Renderer ویندوز';
    order.forEach(section=>{
      if (section==='brand') return;
      if (section==='status') { const h=el('div','tp-status');h.append(el('strong','',data.badge||'فیش آماده‌سازی'),el('b','',data.status_label||''));preview.append(h); }
      if (section==='meta') addMeta(preview,data,data.document_kind==='preparation');
      if (section==='items') { preview.append(separator(design)); data.document_kind==='preparation'?renderPrepItems(preview,data,labelMap):renderCustomerItems(preview,data,width,design.layout); }
      if (section==='notes' && data.customer_note) preview.append(el('div','tp-customer-note',`${labelMap.note||'یادداشت'}: ${data.customer_note}`));
      if (section==='summary') { preview.append(separator(design));renderSummary(preview,data,labelMap); }
      if (section==='settlement' && data.settlement_label) preview.append(el('div','tp-settlement',`${labelMap.settlement||'نحوه ثبت'}: ${data.settlement_label}`));
      if (section==='footer') { const f=value('footer',data.footer||'').trim(); if(f){preview.append(separator(design));preview.append(el('div','tp-footer',f));} }
    });
    requestExact();
  };
  form.addEventListener('input',render); form.addEventListener('change',render); form.addEventListener('reorder:change',render); scenario?.addEventListener('change',render); widthSelect?.addEventListener('change',render); destinationSelect?.addEventListener('change',render);
  // Generic reorder initializes after this script may run; render once again on next frame.
  requestAnimationFrame(()=>requestAnimationFrame(render));
})();

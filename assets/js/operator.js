(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const digits = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
  const moneyNumber = (value) => new Intl.NumberFormat('fa-IR').format(Number(value || 0));
  const money = (value) => `${moneyNumber(value)} ${window.CAFE_CURRENCY || 'تومان'}`;
  const faTextDigits = (value) => String(value ?? '').replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const normalizeNumericInput = (value) => String(value ?? '').replace(/[۰-۹]/g,(ch)=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(ch))).replace(/[٠-٩]/g,(ch)=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(ch))).replace(/[^0-9]/g,'');
  const discountInputDisplay = (value,type) => { const n=Math.max(0,Number(normalizeNumericInput(value)||0)); if(!n)return ''; return type==='fixed'?new Intl.NumberFormat('fa-IR').format(n):faTextDigits(String(n)); };
  const formatDiscountInput = (input,type) => { const raw=normalizeNumericInput(input.value); input.value=raw?discountInputDisplay(raw,type):''; };
  const sprite = String(window.SOKNA_ICON_SPRITE || '');
  const spriteIcon = (name) => `<svg class="ui-icon" aria-hidden="true"><use href="${esc(sprite)}#icon-${esc(name)}"></use></svg>`;
  const refreshIcon = spriteIcon('refresh');
  const editIcon = spriteIcon('adjust');
  const sessionState = {
    get(key){ try { return window.sessionStorage?.getItem(key) ?? null; } catch (_) { return null; } },
    set(key,value){ try { window.sessionStorage?.setItem(key,value); } catch (_) {} },
  };
  const requestId = (prefix='op') => `${prefix}-${crypto.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`}`.slice(0, 96);
  const settlementTouchContext = () => window.matchMedia?.('(pointer: coarse)')?.matches || (navigator.maxTouchPoints||0)>0;
  const focusSettlementControl = (control) => { if(control&&!settlementTouchContext()) requestAnimationFrame(()=>control.focus()); };
  const permissions = window.OPERATOR_PERMISSIONS || {};
  const canHandleOrders = Boolean(permissions.orders_floor);
  const canHandleAccounts = Boolean(permissions.cashier_accounts);
  const canSuperviseShift = Boolean(permissions.shift_supervision);
  const startupParams = new URLSearchParams(window.location.search);
  const startupOpenTable = Math.max(0,Number(startupParams.get('open_table')||0));
  const startupOpenOrder = Math.max(0,Number(startupParams.get('open_order')||0));
  const startupWork = String(startupParams.get('work')||'');
  const startupTableFilter = ['all','open','free'].includes(String(startupParams.get('table_filter'))) ? String(startupParams.get('table_filter')) : 'all';
  const startupAttentionFilter = ['all','orders','calls'].includes(String(startupParams.get('attention_filter'))) ? String(startupParams.get('attention_filter')) : 'all';
  const startupQuickOrderSuccess = String(startupParams.get('quick_order_success')||'').trim();
  const startupQuickOrderTable = Math.max(0,Number(startupParams.get('quick_order_table')||0));
  const startupQuickOrderOrder = Math.max(0,Number(startupParams.get('quick_order_order')||0));
  const startupQuickOrderNumber = Math.max(0,Number(startupParams.get('quick_order_number')||0));
  const startupQuickOrderMode = String(startupParams.get('quick_order_mode')||'normal') === 'late_accounting' ? 'late_accounting' : 'normal';
  const startupResumeSettlement = String(startupParams.get('resume_settlement')||'');
  const tableToken = (value) => {
    const table = value && typeof value === 'object' ? value : null;
    const canonical = Number(table?.table_number || 0);
    if (canonical > 0) return canonical.toLocaleString('fa-IR');
    const text = String(table?.name ?? value ?? '').trim();
    const number = text.match(/[0-9۰-۹٠-٩]+/u);
    if (number) return number[0];
    return [...text.replace(/^(?:میز|table)\s*/iu, '').trim()].slice(0, 2).join('');
  };
  const simpleTableName = (table) => {
    const canonical = Number(table?.table_number || 0);
    if (canonical < 1) return false;
    const normalized = String(table?.name || '').trim().replace(/[۰-۹]/g, d => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٠-٩]/g, d => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
    return new RegExp(`^(?:میز|table)\\s*${canonical}$`, 'iu').test(normalized);
  };

  const state = {
    tables: [], orders: [], calls: [], itemTotals: [], snapshot: '', revision: '', selectedTableId: 0,
    initialized: false, loading: false, failures: 0, timer: null,
    selectedMoveTarget: 0, selectedSubscriber: null, subscriberResults: [], selectedReservation: null,
    knownOrders: new Set(), knownCalls: new Set(), tableFilter: startupTableFilter, tableSort: ['layout','oldest','newest'].includes(sessionState.get('sokna.operator.tableSort')) ? sessionState.get('sokna.operator.tableSort') : 'layout', attentionFilter: startupAttentionFilter, subscriberAbort: null, accommodationAbort: null,
    loadAbort: null, loadSequence: 0, appliedLoadSequence: 0, acceptanceRevision: 0, acceptanceMutation: false,
    printing: {}, accommodation: null, orderAcceptance:{cafe:true,kitchen:true,bar:true}, stationStates:{}, waiterEnabled:true,
    followupsOpen: sessionState.get('sokna.operator.followupsOpen') === '1', reviewOrderId: 0, billSourceRows: new Map(), itemizedSelection:new Map(), itemizedReview:null, itemizedReviewTimer:null, itemizedReviewSeq:0, itemizedNoticeTimer:null,
    quickOrderSuccessVisible: Boolean(startupQuickOrderSuccess && startupQuickOrderTable), tableListScrollY: 0,
  };

  const els = {
    attentionBoard: $('attentionBoard'), attentionCount: $('attentionCount'), attentionTabCount: $('attentionTabCount'),
    tablesBoard: $('tablesBoard'), itemTotalsBoard: $('itemTotalsBoard'), tableDetailShell: $('tableDetailShell'),
    tableDetailBody: $('tableDetailBody'), tableDetailTitle: $('tableDetailTitle'), tableDetailMeta: $('tableDetailMeta'), tableDetailFooter: $('tableDetailFooter'), liveTablesLayout: $('liveTablesLayout'),
    tableAllCount: $('tableAllCount'), tableOpenCount: $('tableOpenCount'), tableFreeCount: $('tableFreeCount'), liveIndicator: $('liveIndicator'),
    liveStatus: $('liveStatus'), lastSync: $('lastSync'),
    checkoutModal: $('checkoutModal'), directSettlementModal: $('directSettlementModal'), itemizedSettlementModal: $('itemizedSettlementModal'), moveTableModal: $('moveTableModal'),
    subscriberModal: $('subscriberModal'), accommodationModal: $('accommodationModal'), billItemModal: $('billItemModal'), billSourceModal: $('billSourceModal'), orderReviewModal: $('orderReviewModal'),
    todaySettlementsModal: $('todaySettlementsModal'), settlementVoidModal: $('settlementVoidModal'),
    waiterToggle: $('waiterToggle'), stationControls: $('stationControls'),
    operationsSummaryText: $('operationsSummaryText'), operationsSummaryState: $('operationsSummaryState'), operatorLiveRetry: $('operatorLiveRetry'),
  };

  function modalOpen(modal, trigger=document.activeElement) {
    if (!modal) return;
    if (window.CafeUI?.dialog) CafeUI.dialog.open(modal, trigger);
    else { modal.classList.remove('hidden'); document.body.classList.add('no-scroll'); }
  }
  function modalClose(modal) {
    if (!modal) return;
    if (window.CafeUI?.dialog) CafeUI.dialog.close(modal);
    else { modal.classList.add('hidden'); document.body.classList.remove('no-scroll'); }
  }

  async function readJsonResponse(response) {
    const raw=await response.text();
    let data={};
    try{data=raw?JSON.parse(raw):{};}catch(_){
      let message='پاسخ سرور قابل خواندن نبود.';
      if(response.status===401)message='نشست شما منقضی شده است. اطلاعات صفحه حفظ شده؛ برای ادامه دوباره وارد شوید.';
      else if(response.status===403)message='این عملیات برای حساب فعلی مجاز نیست.';
      else if(response.status===419)message='اعتبار امنیتی صفحه منقضی شده است؛ صفحه را تازه کنید.';
      const error=new Error(message);error.httpStatus=response.status;error.raw=raw.slice(0,240);throw error;
    }
    if(response.status===401&&!data.message)data.message='نشست شما منقضی شده است. اطلاعات صفحه حفظ شده؛ برای ادامه دوباره وارد شوید.';
    if(response.status===403&&!data.message)data.message='این عملیات برای حساب فعلی مجاز نیست.';
    return data;
  }
  async function post(url, payload, options={}) {
    const response = await fetch(url, {
      method:'POST',cache:'no-store',credentials:'same-origin',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({...payload,csrf_token:csrf}),
      signal:options.signal,
    });
    const data=await readJsonResponse(response);
    window.SoknaPushRuntime?.handleResponse?.(data);
    if(!response.ok||!data.success){
      let message=data.message||`این کار انجام نشد (HTTP ${response.status}).`;
      const diagnostics=[];
      if(data.code&&!message.includes(String(data.code)))diagnostics.push(`خطا: ${data.code}`);
      if(data.request_id&&!message.includes(String(data.request_id)))diagnostics.push(`پیگیری: ${data.request_id}`);
      if(diagnostics.length)message+=` — ${diagnostics.join(' · ')}`;
      const error=new Error(message);error.httpStatus=response.status;error.data=data;throw error;
    }
    return data;
  }

  function syncTablesWorkspaceHeight(){
    if(!els?.liveTablesLayout||window.matchMedia('(max-width:900px)').matches){els?.liveTablesLayout?.style.removeProperty('--tables-workspace-height');return;}
    const top=els.liveTablesLayout.getBoundingClientRect().top;const available=Math.max(420,window.innerHeight-top-16);els.liveTablesLayout.style.setProperty('--tables-workspace-height',`${Math.floor(available)}px`);
  }

  function setTab(name, focus = false) {
    document.querySelectorAll('[data-work-tab]').forEach((button) => {
      const active = button.dataset.workTab === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.tabIndex = active ? 0 : -1;
      if (active && focus) button.focus();
    });
    document.querySelectorAll('[data-work-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.workPanel !== name));
    const url=new URL(window.location.href);url.searchParams.set('work',name);url.hash=name;history.replaceState(history.state,'',url);if(name==='tables')requestAnimationFrame(syncTablesWorkspaceHeight);
  }

  function setConnection(ok,message='') {
    els.liveIndicator?.classList.remove('is-online','is-warning','is-offline');
    const tone=ok?'is-online':state.failures<3?'is-warning':'is-offline';
    els.liveIndicator?.classList.add(tone);
    if(els.liveStatus)els.liveStatus.textContent=ok?'اطلاعات به‌روز است':(message||'دریافت اطلاعات کار روزانه ناموفق بود');
    if(els.lastSync)els.lastSync.textContent=ok?'':`آخرین تلاش ${new Intl.DateTimeFormat('fa-IR',{hour:'2-digit',minute:'2-digit'}).format(new Date())}`;
    els.operatorLiveRetry?.classList.toggle('hidden',ok);
  }

  function activeCalls(tableId) { return state.calls.filter((call) => Number(call.table_id) === Number(tableId)); }
  function unresolvedAccommodation(table) {
    const transfer = table?.accommodation_transfer || null;
    return transfer && Boolean(transfer.needs_action) ? transfer : null;
  }
  function tableStatus(table) {
    const calls = activeCalls(table.id);
    const pending = Number(table.unconfirmed_order_count || 0);
    const transfer = unresolvedAccommodation(table);
    if (transfer && ['failed','void_pending','void_failed'].includes(String(transfer.status))) return {key:'critical',label:'خطای حساب اقامت'};
    if (calls.length) return {key:'call',label:'فراخوان مهمان'};
    if (pending > 0) return {key:'pending',label:'سفارش منتظر تأیید'};
    if (Number(table.bill_subtotal || 0) > 0) return {key:'open',label:'حساب باز'};
    return table.active ? {key:'active',label:'نشست فعال'} : {key:'free',label:'آزاد'};
  }

  function orderPrimaryAction(order) {
    if (!canHandleOrders) return '';
    if (!['pending_approval','new'].includes(String(order.status))) return '';
    if (!(order.allowed_statuses || []).includes('accounted')) return '';
    return `<button class="btn btn-primary order-status-action" data-id="${Number(order.id)}" data-status="accounted">تأیید سفارش</button>`;
  }
  function orderRejectAction(order) {
    if (!canHandleOrders) return '';
    if (!(order.allowed_statuses || []).includes('cancelled')) return '';
    return `<button class="btn btn-light order-status-action" data-id="${Number(order.id)}" data-status="cancelled">رد سفارش</button>`;
  }

  function renderAttention() {
    const allOrders=canHandleOrders?[...state.orders].sort((a,b)=>Number(b.waiting_minutes||0)-Number(a.waiting_minutes||0)):[];
    const allCalls=canHandleOrders?[...state.calls].sort((a,b)=>Number(b.waiting_minutes||0)-Number(a.waiting_minutes||0)):[];
    const orders=state.attentionFilter==='calls'?[]:allOrders;
    const calls=state.attentionFilter==='orders'?[]:allCalls;
    const followups=[];
    const printing=state.printing||{};
    const printPending=Number(printing.pending||0),printProblem=Number(printing.problem||0),printOnline=Boolean(printing.agent_online);
    if(!printOnline||printPending||printProblem)followups.push({kind:'printing',printing});
    if(canHandleAccounts){
      const unresolved=Array.isArray(state.accommodation?.unresolved)?state.accommodation.unresolved:[];
      unresolved.forEach((transfer)=>followups.push({kind:'accommodation',transfer}));
    }
    (state.tables||[]).filter((table)=>Boolean(table.active)&&Boolean(table.carryover)).forEach((table)=>followups.push({kind:'carryover_session',table}));
    if(state.attentionFilter!=='all')followups.length=0;
    const currentCount=orders.length+calls.length,total=currentCount+followups.length;
    if(els.attentionCount){els.attentionCount.textContent=`${digits(currentCount)} فوری · ${digits(followups.length)} پیگیری`;els.attentionCount.classList.toggle('hidden',total===0);}
    if(els.attentionTabCount){els.attentionTabCount.textContent=digits(total);els.attentionTabCount.classList.toggle('hidden',total===0);}

    const orderCards=orders.map((o)=>{
      const qty=(o.items||[]).reduce((sum,line)=>sum+Number(line.quantity||0),0);
      const preview=(o.items||[]).slice(0,3).map((line)=>`<span>${digits(line.quantity)} × ${esc(line.item_name)}</span>`).join('');
      const more=(o.items||[]).length>3?`<small>+ ${digits((o.items||[]).length-3)} مورد دیگر</small>`:'';
      return `<article class="current-order-card" data-order-id="${Number(o.id)}"><header><div><strong>${esc(faTextDigits(o.table_name))}</strong><span>سفارش ${digits(o.order_number||o.id)} · ${esc(o.time_ago)}</span></div><time>${digits(o.waiting_minutes||0)} دقیقه</time></header><div class="current-order-preview">${preview}${more}${o.customer_note?`<em>${esc(o.customer_note)}</em>`:''}</div><footer><span><b>${digits(qty)} آیتم</b><small>جمع اقلام: ${moneyNumber(o.total_amount)}</small></span><div><button class="btn btn-primary" data-review-order="${Number(o.id)}">بررسی و تأیید</button>${(o.allowed_statuses||[]).includes('cancelled')?`<button class="btn btn-light order-status-action" data-id="${Number(o.id)}" data-status="cancelled">رد</button>`:''}</div></footer></article>`;
    }).join('');
    const callRows=calls.map((c)=>`<article class="current-call-row ${Number(c.waiting_minutes||0)>=15?'is-overdue':''}"><div><strong>${esc(faTextDigits(c.table_name))}</strong><span>${esc(c.time_ago)}${c.accepted_by?` · مسئول: ${esc(c.accepted_by)}`:''}</span></div><div><button class="btn btn-primary btn-sm waiter-action" data-id="${Number(c.id)}" data-status="done">رسیدگی شد</button>${c.status==='new'?`<button class="btn btn-light btn-sm waiter-action" data-id="${Number(c.id)}" data-status="accepted">پذیرفتم</button>`:''}</div></article>`).join('');

    const renderFollowupCard=(item)=>{
      if(item.kind==='carryover_session'){
        const table=item.table||{};
        const started=table.business_date_display?`روز کاری شروع: ${esc(table.business_date_display)}`:'نشست از روز کاری قبل';
        const duration=table.duration?` · حضور ${esc(table.duration)}`:'';
        return `<article class="followup-card is-critical"><div><strong>${esc(faTextDigits(table.name||'میز'))} از روز کاری قبل باز مانده است</strong><span>${started}${duration}</span><small>سامانه این نشست را خودکار نمی‌بندد؛ وضعیت میز را بررسی کنید.</small></div><button class="btn btn-light btn-sm" type="button" data-select-table="${Number(table.id)}" data-open-tables>مشاهده میز</button></article>`;
      }
      if(item.kind==='printing'){
        const p=item.printing||{},pending=Number(p.pending||0),problem=Number(p.problem||0),online=Boolean(p.agent_online);
        const title=!online?'سرویس چاپ در دسترس نیست':problem?'چاپ نیازمند رسیدگی است':'فیش در صف چاپ است';
        const text=[pending?`${digits(pending)} کار در انتظار`:'',problem?`${digits(problem)} کار ناموفق یا نامشخص`:'',!online?'رایانه صندوق و سرویس چاپ را بررسی کنید':''].filter(Boolean).join(' · ');
        return `<article class="followup-card is-critical"><div><strong>${esc(title)}</strong><span>${esc(text)}</span></div>${permissions.admin?`<a class="btn btn-light btn-sm" href="${esc(window.PRINTING_SETTINGS_URL||'../admin/printing.php')}">بررسی چاپ</a>`:''}</article>`;
      }
      const tr=item.transfer||{};
      const retry=Boolean(state.accommodation?.enabled)&&tr.retry_allowed!==false?`<button class="icon-action-button accommodation-retry-action" type="button" data-transfer="${Number(tr.id)}" aria-label="تلاش مجدد برای ثبت حساب اقامتگاه" title="تلاش مجدد">${refreshIcon}</button>`:'';
      const finalize=Boolean(tr.can_finalize_local)?`<button class="btn btn-primary btn-sm accommodation-finalize-action" type="button" data-transfer="${Number(tr.id)}">تکمیل تسویه کافه</button>`:'';
      const detach=Boolean(state.accommodation?.can_manage)&&Boolean(tr.can_detach)?`<button class="btn btn-light btn-sm accommodation-detach-action" type="button" data-transfer="${Number(tr.id)}">آزادسازی میز و انتقال به پیگیری</button>`:'';
      const recoverLink=Boolean(tr.local_reversal_pending)&&permissions.admin?`<a class="btn btn-light btn-sm" href="${esc(window.ACCOMMODATION_ADMIN_URL||'../admin/accommodation.php?status=needs_action')}">تکمیل سند برگشتی</a>`:'';
      return `<article class="followup-card is-critical"><div><strong>${esc(faTextDigits(tr.detached?'حساب جداشده از '+(tr.table_name||'میز'):tr.table_name||'حساب اقامتگاه'))}</strong><span>${esc(tr.status_label||tr.status||'نیازمند پیگیری')} · ${esc(tr.guest_name_snapshot||'')}</span>${tr.last_error?`<small>${esc(tr.last_error)}</small>`:''}${!state.accommodation?.enabled&&tr.retry_allowed?'<small>ارتباط زنده اقامتگاه خاموش است؛ بازیابی محلی همچنان قابل انجام است.</small>':''}</div><div>${finalize}${retry}${detach}${recoverLink}</div></article>`;
    };
    const followupGroups=[
      {key:'carryover',label:'نشست‌های بازمانده',items:followups.filter((item)=>item.kind==='carryover_session')},
      {key:'accommodation',label:'ثبت در حساب اقامتگاه',items:followups.filter((item)=>item.kind==='accommodation')},
      {key:'printing',label:'چاپ و پرینتر',items:followups.filter((item)=>item.kind==='printing')},
    ].filter((group)=>group.items.length);
    const followupMarkup=followupGroups.map((group)=>`<section class="attention-followup-group-v1301"><header><strong>${group.label}</strong><span>${digits(group.items.length)} مورد</span></header><div>${group.items.map(renderFollowupCard).join('')}</div></section>`).join('');
    const currentSection=`<section class="attention-current-v1301 rc3-current-work"><header><div><small>اولویت عملیاتی</small><h3>کار جاری</h3></div><span>${digits(currentCount)} مورد</span></header>${currentCount?`<div class="current-work-layout"><section class="current-orders-column"><div class="current-column-head"><strong>سفارش‌های منتظر تأیید</strong><span>${digits(orders.length)}</span></div><div class="current-order-grid">${orderCards||'<div class="empty-state compact">سفارش منتظر ندارید.</div>'}</div></section><aside class="current-calls-column"><div class="current-column-head"><strong>فراخوان‌های مهمان</strong><span>${digits(calls.length)}</span></div><div class="current-call-list">${callRows||'<div class="empty-state compact">فراخوان بازی وجود ندارد.</div>'}</div></aside></div>`:'<div class="operator-live-clear-v1190 is-compact"><div><strong>کار فوری باز ندارید.</strong><span>سفارش یا فراخوان تازه در همین بخش ظاهر می‌شود.</span></div></div>'}</section>`;
    const followupSection=followups.length?`<details class="attention-followups-v1301" data-followups-manual ${state.followupsOpen?'open':''}><summary><span><small>موارد ماندگار</small><strong>پیگیری‌های باز</strong></span><b>${digits(followups.length)} مورد</b></summary><div class="attention-followup-list-v1301">${followupMarkup}</div></details>`:'';
    els.attentionBoard.innerHTML=currentSection+followupSection;
  }
  function renderItemTotals() {
    const rows=Array.isArray(state.itemTotals)?state.itemTotals:[];
    if (!rows.length) {
      els.itemTotalsBoard.innerHTML='<div class="card empty-state">در حساب‌های باز، آیتم تأییدشده یا منتظر تأییدی وجود ندارد.</div>';
      return;
    }
    const groups=new Map();
    rows.forEach((row)=>{const key=row.station_label||'سایر';if(!groups.has(key))groups.set(key,[]);groups.get(key).push(row);});
    els.itemTotalsBoard.innerHTML=[...groups.entries()].map(([label,items])=>`<section class="item-total-group-v1280"><header><h3>${esc(label)}</h3><span>${digits(items.length)} آیتم</span></header><div>${items.map((row)=>`<article><strong>${esc(row.item_name)}</strong><div><span><b>${digits(row.confirmed)}</b> تأییدشده</span>${Number(row.pending)>0?`<span class="is-pending"><b>${digits(row.pending)}</b> منتظر تأیید</span>`:''}</div></article>`).join('')}</div></section>`).join('');
  }

  function renderTableCards() {
    const tables=state.tables;
    const open=tables.filter((t)=>t.active).length,free=tables.length-open,counters={all:tables.length,open,free};
    document.querySelectorAll('[data-table-filter]').forEach((button)=>{const key=button.dataset.tableFilter,active=key===state.tableFilter;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',active?'true':'false');const label=key==='all'?'همه':key==='open'?'باز':'آزاد';button.textContent=`${label} ${digits(counters[key]||0)}`;});
    document.querySelectorAll('[data-table-sort]').forEach((button)=>{const active=button.dataset.tableSort===state.tableSort;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',active?'true':'false');});
    const tableSortSelect=$('tableSortSelect');if(tableSortSelect&&tableSortSelect.value!==state.tableSort)tableSortSelect.value=state.tableSort;
    const visible=tables.filter((table)=>state.tableFilter==='all'||(state.tableFilter==='open'?Boolean(table.active):!table.active));
    if(!tables.length){els.tablesBoard.innerHTML='<div class="card empty-state">میز فعالی تعریف نشده است.</div>';return;}
    if(!visible.length){els.tablesBoard.innerHTML='<div class="card empty-state">میزی برای نمایش وجود ندارد.</div>';return;}
    const renderCards=(rows,timeMode=false)=>rows.map((table)=>{
      const status=tableStatus(table),selected=Number(table.id)===state.selectedTableId,pending=Number(table.unconfirmed_order_count||0),calls=activeCalls(table.id).length;
      const pendingOrder=(table.bill_orders||[]).find((o)=>['pending_approval','new'].includes(String(o.status)));
      const presenceMeta=table.active?(table.carryover?`از روز کاری قبل${table.duration?` · حضور ${esc(table.duration)}`:''}`:(table.duration?`حضور ${esc(table.duration)}`:'نشست فعال')):'آماده پذیرش';
      const orderAgeMeta=table.active&&table.last_order_ago?`آخرین سفارش ${esc(table.last_order_ago)}`:'';
      const operationalMeta=[presenceMeta,orderAgeMeta].filter(Boolean).join(' · ');
      const meta=timeMode&&table.zone_label?`${esc(faTextDigits(table.zone_label))} · ${operationalMeta}`:operationalMeta;
      const justOrdered=Number(table.id)===startupQuickOrderTable;
      return `<article class="table-card-v1280 tone-${esc(status.key)} ${pending||calls||unresolvedAccommodation(table)||table.carryover?'has-attention':''} ${selected?'is-selected':''} ${justOrdered?'is-quick-order-success':''}"><button type="button" class="table-card-main" data-select-table="${Number(table.id)}" aria-pressed="${selected?'true':'false'}"><span class="table-card-number-v1280"><small>میز</small><strong>${esc(tableToken(table))}</strong></span><span class="table-card-copy-v1280">${simpleTableName(table)?'':`<b>${esc(faTextDigits(table.name))}</b>`}<small>${meta}</small></span><span class="table-card-status-v1280">${esc(status.label)}</span><span class="table-card-money-v1280">${Number(table.bill_subtotal||0)>0?(Number(table.bill_paid_total||0)>0?`${moneyNumber(table.bill_remaining_total)} مانده`:moneyNumber(table.bill_final_total)):table.active?'بدون فاکتور':''}</span>${calls?`<span class="table-card-call-badge">${digits(calls)} فراخوان</span>`:''}</button>${pending&&pendingOrder?`<button class="table-card-review" type="button" data-review-order="${Number(pendingOrder.id)}" data-review-table="${Number(table.id)}"><span>${digits(pending)} سفارش منتظر تأیید</span><b>بررسی سفارش</b></button>`:''}</article>`;
    }).join('');
    if(state.tableSort==='layout'){
      const groups=new Map();visible.forEach((table)=>{const zone=String(table.zone_label||'').trim()||'بدون دسته‌بندی';if(!groups.has(zone))groups.set(zone,[]);groups.get(zone).push(table);});
      els.tablesBoard.innerHTML=[...groups.entries()].map(([zone,zoneTables])=>`<section class="table-zone-v1280"><header><h3>${esc(zone)}</h3><span>${digits(zoneTables.length)} میز</span></header><div class="table-zone-grid-v1280">${renderCards(zoneTables,false)}</div></section>`).join('');
      return;
    }
    const activeRows=visible.filter((table)=>table.active).sort((a,b)=>{const av=String(a.started_at||''),bv=String(b.started_at||'');if(!av&&!bv)return Number(a.sort_order||0)-Number(b.sort_order||0)||Number(a.id)-Number(b.id);if(!av)return 1;if(!bv)return -1;const byTime=av===bv?0:(av<bv?-1:1);return (state.tableSort==='oldest'?byTime:-byTime)||Number(a.sort_order||0)-Number(b.sort_order||0)||Number(a.id)-Number(b.id);});
    const freeRows=visible.filter((table)=>!table.active).sort((a,b)=>Number(a.sort_order||0)-Number(b.sort_order||0)||Number(a.id)-Number(b.id));
    const sections=[];
    if(activeRows.length)sections.push(`<section class="table-zone-v1280 is-time-sorted"><header><h3>${state.tableSort==='oldest'?'قدیمی‌ترین حضورها':'تازه‌ترین حضورها'}</h3><span>${digits(activeRows.length)} میز</span></header><div class="table-zone-grid-v1280">${renderCards(activeRows,true)}</div></section>`);
    if(freeRows.length)sections.push(`<section class="table-zone-v1280 is-time-sorted"><header><h3>میزهای آزاد</h3><span>${digits(freeRows.length)} میز</span></header><div class="table-zone-grid-v1280">${renderCards(freeRows,true)}</div></section>`);
    els.tablesBoard.innerHTML=sections.join('');
  }
  function billEditButton(line, locked=false) {
    if(!canHandleAccounts||!line||locked)return '';
    return `<button class="bill-item-edit icon-action-button" type="button" aria-label="اصلاح ${esc(line.item_name||'این ردیف')}" title="اصلاح" data-item-id="${Number(line.id)}" data-item-name="${esc(line.item_name)}" data-ordered="${Number(line.ordered_quantity)}" data-quantity="${Number(line.quantity)}" data-unit-price="${Number(line.unit_price||0)}" data-current-price="${line.current_menu_price===null||line.current_menu_price===undefined?'':Number(line.current_menu_price)}" data-reason="${esc(line.adjustment_reason||'')}" data-requires-preparation="${String(line.preparation_station||'none')==='none'?'0':'1'}">${editIcon}</button>`;
  }

  function invoiceTable(table, locked) {
    state.billSourceRows = new Map();
    const sourceRows=(table.bill_items||[]).map((line)=>({line,sources:Array.isArray(line.source_lines)?line.source_lines.filter(source=>Number(source.id)>0):[]}));
    const hasActions=canHandleAccounts&&!locked&&sourceRows.some(({sources})=>sources.length>0);
    const rows=sourceRows.map(({line,sources},index)=>{
      let control='';
      if(hasActions&&sources.length===1)control=billEditButton({...sources[0],item_name:line.item_name},locked);
      else if(hasActions&&sources.length>1){
        const key=`${Number(table.id)}-${index}`;state.billSourceRows.set(key,{item_name:line.item_name,sources});
        control=`<button class="bill-item-source-picker icon-action-button" type="button" aria-label="انتخاب نوبت برای اصلاح ${esc(line.item_name)}" title="اصلاح" data-source-key="${esc(key)}">${editIcon}</button>`;
      }
      const ordered=Math.max(0,Number(line.quantity||0));
      const paid=Math.max(0,Math.min(ordered,Number(line.paid_quantity||0)));
      const remaining=Math.max(0,ordered-paid);
      const takeawayQty=Math.max(0,Math.min(ordered,Number(line.takeaway_quantity||0)));
      const takeawayMeta=takeawayQty>0?`<small class="bill-takeaway-meta">${digits(takeawayQty)} بیرون‌بر</small>`:'';
      const paidMeta=paid>0?`<small class="bill-paid-meta">${remaining>0?`${digits(paid)} از ${digits(ordered)} پرداخت شده · ${digits(remaining)} مانده`:`${digits(ordered)} از ${digits(ordered)} پرداخت شده`}</small>`:'';
      return `<tr><td>${digits(index+1)}</td><td><strong>${esc(line.item_name)}</strong>${takeawayMeta}${line.item_note?`<small>${esc(line.item_note)}</small>`:''}${paidMeta}<small class="bill-unit-mobile">هر عدد ${moneyNumber(line.unit_price)}</small></td><td>${digits(line.quantity)}</td><td>${moneyNumber(line.unit_price)}</td><td>${moneyNumber(line.line_total)}</td>${hasActions?`<td>${control}</td>`:''}</tr>`;
    }).join('');
    return `<div class="bill-table-wrap"><table class="bill-table bill-table-current" dir="rtl"><thead><tr><th>ردیف</th><th>شرح آیتم</th><th>تعداد</th><th>قیمت واحد</th><th>مبلغ</th>${hasActions?'<th>اصلاح</th>':''}</tr></thead><tbody>${rows}</tbody></table></div>`;
  }

  function findOrder(orderId) {
    const direct=state.orders.find((order)=>Number(order.id)===Number(orderId));
    if(direct)return direct;
    for(const table of state.tables){const order=(table.bill_orders||[]).find((row)=>Number(row.id)===Number(orderId));if(order)return {...order,table_name:table.name,zone_label:table.zone_label};}
    return null;
  }
  function openOrderReview(orderId) {
    const order=findOrder(orderId);if(!order)return CafeUI.toast('سفارش برای بررسی پیدا نشد.','warning');
    state.reviewOrderId=Number(order.id);
    $('orderReviewTitle').textContent=`سفارش ${digits(order.order_number||order.id)} · ${order.table_name||'میز'}`;
    $('orderReviewMeta').textContent=[order.time_ago||order.created_time,order.guest_label].filter(Boolean).join(' · ');
    $('orderReviewBody').innerHTML=orderReviewMarkup(order,false);
    const confirm=$('confirmReviewedOrder'),reject=$('rejectReviewedOrder');
    confirm.dataset.id=String(order.id);reject.dataset.id=String(order.id);
    confirm.disabled=!(order.allowed_statuses||[]).includes('accounted');reject.disabled=!(order.allowed_statuses||[]).includes('cancelled');
    modalOpen(els.orderReviewModal);
  }

  function orderReviewMarkup(order, inline=false) {
    const lines=(order.items||[]).map((line)=>{const qty=Number(line.quantity||0),lineTotal=Number(line.line_total||Number(line.unit_price||0)*qty),takeaway=String(line.fulfillment_mode||'')==='takeaway'?'<em class="order-line-takeaway">بیرون‌بر</em>':'';return `<div class="order-review-line"><span class="order-review-line-main"><strong>${esc(line.item_name)}</strong>${takeaway}${line.item_note?`<small>${esc(line.item_note)}</small>`:''}</span><span class="order-review-line-meta"><b>${digits(qty)} عدد</b><strong>${moneyNumber(lineTotal)}</strong></span></div>`;}).join('');
    const actions=inline?`<div class="pending-order-inline-actions">${orderPrimaryAction(order)}${orderRejectAction(order)}</div>`:'';
    return `<div class="order-review-lines">${lines}</div>${order.customer_note?`<div class="order-note-box order-review-note"><strong>یادداشت مهمان</strong><span>${esc(order.customer_note)}</span></div>`:''}<div class="order-review-total"><span>جمع اقلام</span><strong>${money(order.total_amount)}</strong></div>${actions}`;
  }
  function pendingOrdersMarkup(table) {
    const pending=(table.bill_orders||[]).filter((o)=>o.status!=='accounted');
    if(!pending.length)return '';
    return `<section class="table-account-section-v1280 is-attention"><div class="table-account-section-title-v1280"><div><small>نیازمند اقدام</small><h4>${digits(pending.length)} سفارش منتظر تأیید</h4></div></div>${pending.map((o)=>`<article class="pending-order-card-v1280" data-order-id="${Number(o.id)}"><div><strong>سفارش ${digits(o.order_number||o.id)}</strong><span>${esc(o.created_time)} · ${esc(o.guest_label||'مهمان')}</span></div><div class="pending-order-inline-review">${orderReviewMarkup(o,true)}</div></article>`).join('')}</section>`;
  }
  function batchesMarkup(table, locked) {
    const confirmed=(table.bill_orders||[]).filter((o)=>o.status==='accounted');
    if(confirmed.length<=1)return '';
    const focusFreshOrder=state.quickOrderSuccessVisible&&Number(table.id)===startupQuickOrderTable&&startupQuickOrderOrder>0;
    return `<details class="table-account-disclosure-v1280 order-batches-disclosure-v13219" ${focusFreshOrder?'open':''}><summary><span>سفارش‌های این میز</span><b>${digits(confirmed.length)}</b></summary><div class="bill-batches-v1280">${confirmed.map((o,index)=>{
      const hasActions=canHandleAccounts&&!locked&&(o.items||[]).some((line)=>Number(line.id)>0);
      const isFresh=focusFreshOrder&&Number(o.id)===startupQuickOrderOrder;
      const rows=(o.items||[]).map((line,i)=>`<tr><td>${digits(i+1)}</td><td><strong>${esc(line.item_name)}</strong>${line.item_note?`<small>${esc(line.item_note)}</small>`:''}</td><td>${digits(line.quantity)}</td><td>${moneyNumber(line.line_total)}</td>${hasActions?`<td>${billEditButton(line,locked)}</td>`:''}</tr>`).join('');
      return `<details class="bill-batch-v1280 ${isFresh?'is-quick-order-success':''}" data-bill-order="${Number(o.id)}" ${isFresh?'open':''}><summary><span>نوبت ${digits(index+1)} · سفارش ${digits(o.order_number||o.id)}</span><small>${esc(o.created_time)} · ${esc(o.guest_label||'مهمان')}</small></summary><div class="bill-table-wrap"><table class="bill-table"><thead><tr><th>ردیف</th><th>شرح آیتم</th><th>تعداد</th><th>مبلغ</th>${hasActions?'<th>اصلاح</th>':''}</tr></thead><tbody>${rows}</tbody></table></div>${o.customer_note?`<div class="order-note-box"><strong>یادداشت نوبت:</strong> ${esc(o.customer_note)}</div>`:''}${canHandleAccounts?`<div class="bill-batch-menu-v1280"><div class="row-action-menu" data-action-menu><button type="button" class="bill-batch-action-trigger" data-action-menu-trigger aria-label="عملیات سفارش" aria-haspopup="menu" aria-expanded="false">${spriteIcon('more')}</button><div class="row-action-popover" data-action-menu-popover role="menu"><button role="menuitem" type="button" class="prep-reprint-action" data-order="${Number(o.id)}" ${window.PREP_PRINT_CONFIGURED?'':'disabled'}>${window.PREP_PRINT_CONFIGURED?'چاپ مجدد نسخه آماده‌سازی':'پرینتر آماده‌سازی فعال نیست'}</button></div></div></div>`:''}</details>`;
    }).join('')}</div></details>`;
  }

  function quickOrderUrl(tableId, origin='global', mode='normal') {
    const returnUrl=new URL(window.location.href);
    if(origin==='table-panel')returnUrl.searchParams.set('open_table',String(Number(tableId)));
    else returnUrl.searchParams.delete('open_table');
    returnUrl.searchParams.delete('quick_order_success');
    returnUrl.searchParams.delete('quick_order_table');
    returnUrl.searchParams.delete('quick_order_order');
    returnUrl.searchParams.delete('quick_order_number');
    returnUrl.searchParams.delete('quick_order_mode');
    returnUrl.searchParams.delete('resume_settlement');
    if(mode==='late_accounting')returnUrl.searchParams.set('resume_settlement','itemized');
    returnUrl.searchParams.set('work','tables');
    returnUrl.hash='tables';
    const targetPath=String(window.STAFF_QUICK_ORDER_PAGE||'../staff/quick-order.php');
    let target;
    try{target=new URL(targetPath,window.location.href);}catch(_){target=new URL(targetPath,'https://sokna.local/operator/');}
    target.searchParams.set('table_id',String(Number(tableId)));
    target.searchParams.set('origin',origin);
    if(mode==='late_accounting')target.searchParams.set('mode','late_accounting');
    target.searchParams.set('return',`${returnUrl.pathname}${returnUrl.search}${returnUrl.hash}`);
    return `${target.pathname}${target.search}`;
  }

  function renderTableDetail(table) {
    if(!table)return;
    const transfer=unresolvedAccommodation(table);
    const locked=Boolean(transfer&&transfer.needs_action);
    const itemizedLocked=Boolean(table.bill_itemized_active);
    const accountEditLocked=locked||itemizedLocked;
    const pending=Number(table.unconfirmed_order_count||0);
    const confirmed=(table.bill_orders||[]).filter((o)=>o.status==='accounted');
    const subtotal=Number(table.bill_subtotal||0), discount=Number(table.bill_discount||0), net=Number(table.bill_net??Math.max(0,subtotal-discount)), tax=Number(table.bill_tax||0), total=Number(table.bill_final_total||0);
    const paidTotal=Math.max(0,Number(table.bill_paid_total||0)),remainingTotal=Math.max(0,Number(table.bill_remaining_total??total));
    const status=tableStatus(table);
    els.tableDetailTitle.textContent=faTextDigits(table.name);
    els.tableDetailTitle.dataset.tableId=String(table.id);
    if(els.tableDetailMeta)els.tableDetailMeta.textContent=table.active?[status.label,table.last_order_ago?`آخرین سفارش ${table.last_order_ago}`:''].filter(Boolean).join(' · '):'آماده پذیرش';
    const canMove=canHandleOrders&&table.active&&!accountEditLocked;
    els.tableDetailTitle.disabled=!canMove;
    els.tableDetailTitle.classList.toggle('is-clickable',canMove);

    const callCards=canHandleOrders?activeCalls(table.id).map((c)=>`<div class="table-account-alert-v1280"><div><strong>فراخوان مهمان</strong><span>${esc(c.time_ago)}</span></div><button class="btn btn-primary btn-sm waiter-action" data-id="${Number(c.id)}" data-status="done">رسیدگی شد</button></div>`).join(''):'';
    const retryTransfer=canHandleAccounts&&Boolean(state.accommodation?.enabled)&&['pending','failed'].includes(String(transfer?.status))&&transfer?.retry_allowed!==false;
    const finalizeTransfer=canHandleAccounts&&Boolean(transfer?.can_finalize_local);
    const transferCard=transfer?`<div class="table-account-alert-v1280 is-critical"><div><strong>حساب اقامت · ${esc(transfer.status_label||transfer.status)}</strong><span>${esc(transfer.last_error||(transfer.local_finalize_pending?'انتقال اقامتگاه قطعی است؛ تسویه محلی کافه باید تکمیل شود.':'این انتقال باید تعیین تکلیف شود.'))}</span></div><div class="table-account-alert-actions">${finalizeTransfer?`<button class="btn btn-primary accommodation-finalize-action" type="button" data-transfer="${Number(transfer.id)}">تکمیل تسویه کافه</button>`:''}${retryTransfer?`<button class="icon-action-button accommodation-retry-action" type="button" data-transfer="${Number(transfer.id)}" aria-label="تلاش مجدد برای ثبت حساب اقامتگاه" title="تلاش مجدد">${refreshIcon}</button>`:''}${Boolean(state.accommodation?.can_manage)&&transfer.can_detach?`<button class="btn btn-light accommodation-detach-action" type="button" data-transfer="${Number(transfer.id)}">آزادسازی میز و انتقال به پیگیری</button>`:''}</div></div>`:'';
    const discountType=table.discount_type==='fixed'?'fixed':'percent';
    const discountLabel=discount?(table.discount_type==='fixed'?`${moneyNumber(table.discount_value)} تومان`:`${digits(table.discount_value)}٪`):'بدون تخفیف';
    const discountPreview=tax>0?`پس از تخفیف: <strong>${money(net)}</strong> · مالیات: <strong>${money(tax)}</strong> · نهایی: <strong>${money(total)}</strong>`:`مبلغ پس از تخفیف: <strong>${money(total)}</strong>`;
    const discountEditor=canHandleAccounts?`<details class="table-account-disclosure-v1280 discount-disclosure-v1301"><summary><span>تخفیف</span><b>${esc(discountLabel)}</b>${editIcon}</summary><div class="inline-discount-panel" data-discount-table="${Number(table.id)}" data-discount-type="${discountType}"><div class="discount-editor-row"><div class="segmented-control compact" role="radiogroup" aria-label="نوع تخفیف"><button type="button" class="discount-type-choice ${discountType==='percent'?'is-active':''}" data-discount-type-choice="percent" role="radio" aria-checked="${discountType==='percent'?'true':'false'}" ${accountEditLocked||pending?'disabled':''}>درصد</button><button type="button" class="discount-type-choice ${discountType==='fixed'?'is-active':''}" data-discount-type-choice="fixed" role="radio" aria-checked="${discountType==='fixed'?'true':'false'}" ${accountEditLocked||pending?'disabled':''}>مبلغ</button></div><label class="discount-value-field"><span class="sr-only">مقدار تخفیف</span><span class="discount-value-control"><input class="form-control bill-discount-value" type="text" inputmode="numeric" enterkeyhint="done" value="${discountInputDisplay(table.discount_value||0,discountType)}" ${accountEditLocked||pending?'disabled':''}><em data-discount-suffix>${discountType==='fixed'?'تومان':'٪'}</em></span></label></div><div class="discount-preview-v1301">${discountPreview}</div><p class="inline-form-error hidden discount-inline-error" role="alert"></p><div class="discount-editor-actions">${discount?`<button class="btn btn-light btn-sm bill-discount-clear" ${accountEditLocked||pending?'disabled':''}>حذف تخفیف</button>`:''}<button class="btn btn-primary btn-sm bill-discount-save" ${accountEditLocked||pending?'disabled':''}>اعمال تخفیف</button></div>${pending?'<small>ابتدا سفارش تازه را تأیید کنید.</small>':locked?'<small>فاکتور در وضعیت قفل‌شده است.</small>':''}</div></details>`:'';
    const invoiceHeading=confirmed.length>1?`<div><h4>فاکتور جاری</h4><small>${digits(confirmed.length)} نوبت سفارش</small></div>`:`<div><h4>فاکتور جاری</h4></div>`;
    const invoiceTotals=(discount||tax)?`<div class="invoice-totals"><div class="invoice-total-row"><span>جمع اقلام</span><strong>${moneyNumber(subtotal)}</strong></div>${discount?`<div class="invoice-total-row is-discount"><span>تخفیف${table.discount_by?` · ${esc(table.discount_by)}`:''}</span><strong>− ${moneyNumber(discount)}</strong></div>`:''}${tax?`<div class="invoice-total-row"><span>پس از تخفیف</span><strong>${moneyNumber(net)}</strong></div><div class="invoice-total-row"><span>مالیات</span><strong>+ ${moneyNumber(tax)}</strong></div><div class="invoice-total-row"><span>مبلغ قابل پرداخت</span><strong>${moneyNumber(total)}</strong></div>`:''}</div>`:'';
    const mobileAccountView=window.matchMedia('(max-width:900px)').matches;
    const settlementProgress=paidTotal>0?(mobileAccountView
      ?`<div class="table-account-settlement-progress is-mobile-compact"><span>پرداخت‌شده</span><strong>${money(paidTotal)}</strong><small>از ${money(total)}</small></div>${itemizedLocked?'<div class="table-account-settlement-state">تسویه جداگانه فعال</div>':''}`
      :`<div class="table-account-settlement-progress"><div><span>جمع حساب</span><strong>${money(total)}</strong></div><div><span>پرداخت‌شده</span><strong>${money(paidTotal)}</strong></div><div><span>مانده حساب</span><strong>${money(remainingTotal)}</strong></div></div>${itemizedLocked?'<div class="table-account-settlement-state">تسویه جداگانه فعال</div>':''}`):'';
    const freshOrderVisible=state.quickOrderSuccessVisible&&Number(table.id)===startupQuickOrderTable;
    const freshOrderLabel=startupQuickOrderNumber||startupQuickOrderOrder;
    const freshOrderIsLate=startupQuickOrderMode==='late_accounting';
    const freshOrderNotice=freshOrderVisible&&!freshOrderIsLate?`<div class="table-account-order-success" role="status"><span>${spriteIcon('check')}<strong>سفارش ${digits(freshOrderLabel)} ثبت شد</strong></span><small>فاکتور همین میز با اطلاعات تازه بازخوانی شده است.</small></div>`:'';
    const invoice=confirmed.length?`${freshOrderNotice}<section class="table-account-section-v1280"><div class="table-account-section-title-v1280">${invoiceHeading}</div>${settlementProgress}${invoiceTable(table,accountEditLocked)}${invoiceTotals}${discountEditor}${batchesMarkup(table,accountEditLocked)}</section>`:`${freshOrderNotice}<section class="table-account-empty-v1280"><strong>${table.active?'هنوز فاکتوری ثبت نشده است.':'این میز آزاد است.'}</strong><span>${table.active?'برای این میز سفارش ثبت کنید.':'ثبت اولین سفارش، نشست میز را ایجاد می‌کند.'}</span></section>`;

    const quickHref=quickOrderUrl(table.id,'table-panel');
    const missedHref=quickOrderUrl(table.id,'table-panel','late_accounting');
    const quickOrder=canHandleOrders&&!accountEditLocked
      ?`<a class="btn btn-light" href="${esc(quickHref)}">${table.active?'افزودن سفارش':'ثبت سفارش برای این میز'}</a>`
      :canHandleOrders&&itemizedLocked&&canHandleAccounts
        ?`<a class="btn btn-light late-accounting-action" href="${esc(missedHref)}">${spriteIcon('plus')}<span>افزودن قلم جاافتاده</span></a>`
        :canHandleOrders?`<button class="btn btn-light" type="button" disabled>${table.active?'افزودن سفارش':'ثبت سفارش برای این میز'}</button>`:'';
    const closeEmpty=canHandleOrders&&table.active&&!confirmed.length&&!pending?`<button class="btn btn-outline session-action" data-action="close" data-table="${Number(table.id)}">آزادکردن میز خالی</button>`:'';
    const prebill=canHandleAccounts&&confirmed.length&&!pending&&!transfer&&!itemizedLocked&&window.CUSTOMER_PRINT_CONFIGURED?`<button class="btn btn-light session-action" data-action="print_prebill" data-table="${Number(table.id)}">${spriteIcon('print')}<span>چاپ صورتحساب</span></button>`:'';
    const printStatus=canHandleAccounts&&confirmed.length&&!pending&&!transfer&&!itemizedLocked&&!window.CUSTOMER_PRINT_CONFIGURED?`<span class="table-account-print-status-v13219">${spriteIcon('print')}<span>چاپ غیرفعال</span></span>`:'';
    const checkoutLabel=itemizedLocked?`ادامه تسویه جداگانه · ${money(remainingTotal)}`:(paidTotal>0?`پرداخت مانده ${money(remainingTotal)}`:(mobileAccountView?`تسویه حساب · ${money(remainingTotal)}`:'تسویه حساب'));
    const checkout=canHandleAccounts&&confirmed.length&&!pending&&!transfer&&remainingTotal>0?`<button class="btn btn-primary open-settlement-action" data-table="${Number(table.id)}" data-amount="${remainingTotal}">${checkoutLabel}</button>`:'';
    const previousScroll=els.tableDetailBody?.scrollTop||0;
    const previousDiscountOpen=Boolean(els.tableDetailBody?.querySelector('.discount-disclosure-v1301')?.open);
    els.tableDetailBody.innerHTML=`${callCards}${transferCard}${pendingOrdersMarkup(table)}${invoice}`;
    const discountDisclosure=els.tableDetailBody?.querySelector('.discount-disclosure-v1301');
    if(discountDisclosure&&(previousDiscountOpen||window.matchMedia('(min-width:901px) and (min-height:840px)').matches))discountDisclosure.open=true;
    if(previousScroll>0)requestAnimationFrame(()=>{if(els.tableDetailBody)els.tableDetailBody.scrollTop=Math.min(previousScroll,Math.max(0,els.tableDetailBody.scrollHeight-els.tableDetailBody.clientHeight));});
    if(els.tableDetailFooter){
      const secondaryActions=[quickOrder,prebill,closeEmpty].filter(Boolean).join('');
      const checkoutGroup=confirmed.length?`<div class="table-account-checkout"><div class="table-account-payable-v1300"><span>${paidTotal>0?'مانده قابل پرداخت':'مبلغ قابل پرداخت'}</span><strong>${money(remainingTotal)}</strong></div>${checkout}</div>`:'';
      els.tableDetailFooter.innerHTML=`<div class="table-account-footer-actions-v1301">${secondaryActions}${printStatus}</div>${checkoutGroup}`;
      els.tableDetailFooter.classList.toggle('hidden',!secondaryActions&&!printStatus&&!confirmed.length);
    }
  }
  function tableHistoryUrl(tableId=0) {
    const url=new URL(window.location.href);
    if(Number(tableId)>0)url.searchParams.set('open_table',String(Number(tableId)));else url.searchParams.delete('open_table');
    url.searchParams.set('work','tables');url.hash='tables';
    return url;
  }
  function writeTableHistory(tableId,mode='push') {
    const next={...(history.state||{})};
    if(Number(tableId)>0)next.operatorTableId=Number(tableId);else delete next.operatorTableId;
    history[mode==='replace'?'replaceState':'pushState'](next,'',tableHistoryUrl(tableId));
  }
  function openTable(tableId,options={}) {
    const table=state.tables.find((row)=>Number(row.id)===Number(tableId));
    if(!table)return;
    if(!table.active&&canHandleOrders){window.location.assign(quickOrderUrl(table.id,'global'));return;}
    const wasOpen=state.selectedTableId>0;
    if(!wasOpen)state.tableListScrollY=Math.max(0,window.scrollY||0);
    state.selectedTableId=Number(table.id);
    renderTableCards();
    renderTableDetail(table);
    els.tableDetailShell?.classList.add('is-open');
    els.liveTablesLayout?.classList.add('has-detail');
    els.tableDetailShell?.setAttribute('aria-hidden','false');
    document.body.classList.add('operator-detail-open');document.body.classList.remove('operator-startup-table-detail');requestAnimationFrame(syncTablesWorkspaceHeight);
    const historyMode=String(options.historyMode||'push');
    if(historyMode!=='none'){
      const current=Number(history.state?.operatorTableId||0);
      if(current!==Number(table.id))writeTableHistory(table.id,current>0||historyMode==='replace'?'replace':'push');
    }
  }
  function closeTableView(restoreScroll=true) {
    els.tableDetailShell?.classList.remove('is-open');
    els.liveTablesLayout?.classList.remove('has-detail');
    els.tableDetailShell?.setAttribute('aria-hidden','true');
    document.body.classList.remove('operator-detail-open','operator-startup-table-detail');requestAnimationFrame(syncTablesWorkspaceHeight);
    state.selectedTableId=0;if(els.tableDetailFooter){els.tableDetailFooter.classList.add('hidden');els.tableDetailFooter.innerHTML='';}if(els.tableDetailMeta)els.tableDetailMeta.textContent='حساب میز';
    renderTableCards();
    if(restoreScroll)requestAnimationFrame(()=>window.scrollTo({top:state.tableListScrollY||0,left:0,behavior:'auto'}));
  }
  function closeTable(options={}) {
    const historyMode=String(options.historyMode||'auto');
    if(historyMode==='auto'&&Number(history.state?.operatorTableId||0)>0){history.back();return;}
    closeTableView(options.restoreScroll!==false);
    if(historyMode==='replace')writeTableHistory(0,'replace');
  }

  function freeTableGroups(sourceTableId) {
    const groups=new Map();
    state.tables.filter((t)=>Number(t.id)!==Number(sourceTableId)&&!t.active).forEach((t)=>{const zone=String(t.zone_label||'').trim()||'بدون دسته‌بندی';if(!groups.has(zone))groups.set(zone,[]);groups.get(zone).push(t);});
    return groups;
  }
  function openMoveTable() {
    const table=state.tables.find((row)=>Number(row.id)===state.selectedTableId);
    if(!canHandleOrders||!table||!table.active||Boolean(unresolvedAccommodation(table)))return;
    state.selectedMoveTarget=0;
    $('moveTableSummary').textContent=`کل حساب ${faTextDigits(table.name)} به میز آزاد انتخاب‌شده منتقل می‌شود.`;
    const groups=freeTableGroups(table.id);
    $('moveTableChoices').innerHTML=groups.size?[...groups.entries()].map(([zone,tables])=>`<section><h3>${esc(zone)}</h3><div>${tables.map((t)=>`<button type="button" data-move-target="${Number(t.id)}"><strong>${esc(t.name)}</strong><span>آزاد</span></button>`).join('')}</div></section>`).join(''):'<div class="empty-state">میز آزاد دیگری وجود ندارد.</div>';
    $('confirmMoveTable').disabled=true;
    modalOpen(els.moveTableModal);
  }

  function renderOperationControls(orderAcceptance={},waiterEnabled,stationStates={}) {
    if(!canSuperviseShift)return;
    document.querySelectorAll('[data-order-acceptance]').forEach((button)=>{
      const scope=button.dataset.orderAcceptance;
      const enabled=Boolean(orderAcceptance?.[scope]);
      const status=document.querySelector(`[data-acceptance-status="${scope}"]`);
      if(status){status.textContent=enabled?'فعال':'متوقف';status.className=`operation-state-badge ${enabled?'is-active':'is-stopped'}`;}
      button.textContent=enabled?(scope==='cafe'?'توقف سفارش آنلاین':`توقف آنلاین ${scope==='kitchen'?'آشپزخانه':'بار'}`):'فعال‌سازی آنلاین';
      button.className=`btn order-acceptance-toggle ${enabled?'btn-light':'btn-primary'}`;
      button.dataset.enabled=enabled?'1':'0';
    });
    if(els.waiterToggle){els.waiterToggle.textContent=waiterEnabled?'غیرفعال‌کردن فراخوان':'فعال‌کردن فراخوان';els.waiterToggle.className=`btn ${waiterEnabled?'btn-light':'btn-primary'}`;els.waiterToggle.dataset.enabled=waiterEnabled?'1':'0';}
    const busy=Object.entries(stationStates).filter(([,v])=>v).length;
    const stopped=['cafe','kitchen','bar'].filter((scope)=>!Boolean(orderAcceptance?.[scope]));
    const stoppedLabels={cafe:'کل کافه',kitchen:'آشپزخانه',bar:'بار'};
    if(els.operationsSummaryText)els.operationsSummaryText.textContent=stopped.length?`توقف آنلاین: ${stopped.map((s)=>stoppedLabels[s]).join('، ')}${busy?` · ${digits(busy)} بخش شلوغ`:''}`:`پذیرش آنلاین فعال · فراخوان ${waiterEnabled?'فعال':'غیرفعال'}${busy?` · ${digits(busy)} بخش شلوغ`:''}`;
    if(els.operationsSummaryState)els.operationsSummaryState.textContent=(stopped.length||!waiterEnabled||busy)?'نیازمند توجه':'عادی';
    els.stationControls?.querySelectorAll('[data-station-choice]').forEach((button)=>{const busyValue=button.dataset.busy==='1';button.classList.toggle('is-selected',Boolean(stationStates[button.dataset.stationChoice])===busyValue);});
  }

  async function updateOrderStatus(id,status,button) {
    if(status==='cancelled'&&!await CafeUI.confirm('این نوبت سفارش رد می‌شود، از صف تأیید خارج می‌شود و هیچ فیش آماده‌سازی برای آن ساخته نخواهد شد.','رد سفارش',{okLabel:'رد سفارش',danger:true,trigger:button}))return false;
    const card=button.closest('[data-order-id],.pending-order-card-v1280,.current-order-card'),actionLabel=status==='cancelled'?'در حال رد…':'در حال تأیید…',operationId=requestId(`order-${id}`);
    try{
      await CafeUI.runAction({button,loadingText:actionLabel,successText:status==='cancelled'?'رد شد':'تأیید شد',request:()=>post(window.OPERATOR_STATUS_API,{order_id:Number(id),status,request_id:operationId}),onSuccess:async(result)=>{
        if(!result.persisted||result.current_status!==status||result.request_id!==operationId)throw new Error('سرور نتیجه نهایی عملیات را تأیید نکرد.');
        card?.classList.add('is-resolved');
        state.orders=state.orders.filter(order=>Number(order.id)!==Number(id));
        renderAttention();
        modalClose(els.orderReviewModal);state.reviewOrderId=0;
        state.snapshot='';state.revision='';
        window.setTimeout(()=>{ void load(true,{abortPrevious:true,forceFull:true}); },0);
      }});return true;
    }catch(error){card?.classList.remove('is-resolved');CafeUI.toast(CafeUI.requestErrorMessage(error),'error');return false;}
  }
  async function waiterAction(button) {
    button.disabled=true;
    const callId=Number(button.dataset.id);
    try{
      await post(window.OPERATOR_WAITER_API,{call_id:callId,status:button.dataset.status});
      state.calls=state.calls.filter(call=>Number(call.id)!==callId);renderAttention();
      state.snapshot='';state.revision='';
      window.setTimeout(()=>{ void load(true,{abortPrevious:true}); },0);
    }catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');button.disabled=false;}
  }
  async function sessionAction(button) {
    const action=button.dataset.action,tableId=Number(button.dataset.table),operationId=requestId(action);
    if(action==='close'&&!await CafeUI.confirm('این میز بدون ثبت فاکتور آزاد می‌شود.','آزادکردن میز بدون فاکتور؟',{okLabel:'آزادکردن میز',danger:true,trigger:button}))return;
    try{
      await CafeUI.runAction({button,loadingText:action==='print_prebill'?'در حال ارسال به چاپ…':'در حال ثبت…',successText:action==='print_prebill'?'وارد صف شد':'ثبت شد',request:()=>post(window.OPERATOR_SESSION_API,{action,table_id:tableId,request_id:operationId}),onSuccess:async(data)=>{
        if(!data.persisted||data.request_id!==operationId)throw new Error('سرور ثبت عملیات را با شناسه همین درخواست تأیید نکرد.');
        if(action==='print_prebill'){
          CafeUI.toast(data.message||'صورتحساب وارد صف چاپ شد.');
        }else CafeUI.toast(data.message||'ثبت شد.');
        state.snapshot='';if(action==='close')closeTable();await load(true,{abortPrevious:true});
      }});
    }catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}
  }

  function billDeleteReason() {
    const selected=document.querySelector('input[name="bill_item_delete_reason"]:checked')?.value||'';
    if(!selected)return '';
    if(selected==='سایر'){const detail=$('billItemDeleteOtherReason')?.value.trim()||'';return detail?`سایر: ${detail}`:'';}
    return selected;
  }
  function billFinalQuantity(){return Math.max(0,Math.min(99,Number(normalizeNumericInput($('billItemFinalQuantity')?.value)||0)));}
  function billPreparedQuantity(){return Math.max(0,Number(normalizeNumericInput($('billItemPreparedQuantity')?.value)||0));}
  function billEditDirty(){
    if(!els.billItemModal||els.billItemModal.classList.contains('hidden'))return false;
    const current=Number($('billItemCurrent')?.value||0),next=billFinalQuantity();
    return next!==current||billDeleteReason()!==''||billPreparedQuantity()>0||String($('billItemDeleteOtherReason')?.value||'').trim()!=='';
  }
  async function closeBillItemSafely(){
    if(billEditDirty()&&!await CafeUI.confirm('تغییرات این اصلاح ثبت نشده است. بسته شود؟','بستن اصلاح',{okLabel:'بستن',trigger:document.activeElement}))return false;
    modalClose(els.billItemModal);return true;
  }
  function updateBillEditPreview(){
    const current=Number($('billItemCurrent')?.value||0),next=billFinalQuantity(),requiresPreparation=$('billItemRequiresPreparation')?.value==='1';
    if($('billItemFinalQuantity'))$('billItemFinalQuantity').value=faTextDigits(String(next));
    const preview=$('billItemFinalPreview'),reasonPanel=$('billItemDeleteReasonPanel'),preparedPanel=$('billItemPreparedPanel'),save=$('saveBillItem');
    const removed=Math.max(0,current-next);
    reasonPanel?.classList.toggle('hidden',next!==0);
    preparedPanel?.classList.toggle('hidden',!(requiresPreparation&&removed>0));
    if(requiresPreparation&&removed>0){
      const preparedInput=$('billItemPreparedQuantity');let prepared=Math.max(0,Math.min(removed,billPreparedQuantity()));preparedInput.value=faTextDigits(String(prepared));preparedInput.max=String(removed);
      $('billItemPreparedCaption').textContent=`از ${digits(removed)} عدد حذف‌شده`;
      const unprepared=removed-prepared;
      $('billItemPreparedImpact').textContent=unprepared>0?`${digits(unprepared)} عدد آماده‌نشده از آماده‌سازی کم و موجودی همان مقدار برگردانده می‌شود.`:'همه مقدار حذف‌شده آماده شده؛ موجودی برنمی‌گردد.';
    }else if($('billItemPreparedQuantity')){$('billItemPreparedQuantity').value='۰';$('billItemPreparedQuantity').max='0';}
    if(next===current){preview.textContent='تعداد بدون تغییر می‌ماند.';save.disabled=true;save.textContent='بدون تغییر';save.classList.remove('btn-danger');save.classList.add('btn-primary');}
    else if(next>current){const currentPrice=Number($('billItemExpectedPrice')?.value||-1),historicalPrice=Number($('billItemHistoricalPrice')?.value||-1);preview.textContent=currentPrice>=0&&currentPrice!==historicalPrice?`${digits(next-current)} عدد به سفارش تازه با قیمت فعلی ${moneyNumber(currentPrice)} تومان اضافه می‌شود.`:`${digits(next-current)} عدد به سفارش جدید اضافه می‌شود.`;save.disabled=currentPrice<0;save.textContent=currentPrice<0?'نیازمند تازه‌سازی':'ثبت تغییر';save.classList.remove('btn-danger');save.classList.add('btn-primary');}
    else if(next>0){preview.textContent=`${digits(removed)} عدد از این ردیف کم می‌شود.`;save.disabled=false;save.textContent='ثبت تغییر';save.classList.remove('btn-danger');save.classList.add('btn-primary');}
    else{preview.textContent=`${digits(current)} عدد «${$('billItemName')?.value||'این آیتم'}» از حساب حذف می‌شود.`;save.disabled=false;save.textContent='حذف از حساب';save.classList.remove('btn-primary');save.classList.add('btn-danger');}
    $('billItemError')?.classList.add('hidden');
  }
  function openBillSource(key) {
    const row=state.billSourceRows.get(String(key));if(!row)return CafeUI.toast('منبع این ردیف پیدا نشد.','warning');
    $('billSourceTitle').textContent=`اصلاح ${row.item_name}`;
    $('billSourceDescription').textContent='این ردیف از چند نوبت سفارش تشکیل شده است؛ نوبت موردنظر را انتخاب کنید.';
    $('billSourceList').innerHTML=row.sources.map((source,index)=>`<button type="button" class="bill-source-choice" data-bill-source-index="${index}" data-source-key="${esc(String(key))}"><span><strong>سفارش ${digits(source.order_number||source.order_id||'')}</strong><small>${esc(source.created_time||source.created_at||'')}</small></span><b>${digits(source.quantity)} عدد</b></button>`).join('');
    modalOpen(els.billSourceModal);
  }
  function selectBillSource(button) {
    const row=state.billSourceRows.get(String(button.dataset.sourceKey));const source=row?.sources?.[Number(button.dataset.billSourceIndex)];if(!source)return;
    modalClose(els.billSourceModal);
    const virtual=document.createElement('button');
    virtual.dataset.itemId=String(source.id);virtual.dataset.itemName=String(row.item_name||source.item_name||'آیتم');virtual.dataset.ordered=String(source.ordered_quantity||source.quantity||0);virtual.dataset.quantity=String(source.quantity||0);virtual.dataset.unitPrice=String(source.unit_price??0);virtual.dataset.currentPrice=source.current_menu_price===null||source.current_menu_price===undefined?'':String(source.current_menu_price);virtual.dataset.reason=String(source.adjustment_reason||'');virtual.dataset.requiresPreparation=String(source.preparation_station||'none')==='none'?'0':'1';
    openBillEdit(virtual);
  }
  function openBillEdit(button) {
    const current=Number(button.dataset.quantity||0),name=button.dataset.itemName||'آیتم';
    $('billItemId').value=button.dataset.itemId||'';$('billItemCurrent').value=String(current);$('billItemName').value=name;$('billItemHistoricalPrice').value=String(Number(button.dataset.unitPrice||0));$('billItemExpectedPrice').value=button.dataset.currentPrice??'';$('billItemFinalQuantity').value=faTextDigits(String(current));$('billItemRequiresPreparation').value=button.dataset.requiresPreparation==='0'?'0':'1';$('billItemPreparedQuantity').value='۰';
    els.billItemModal.dataset.operationId='';
    document.querySelectorAll('input[name="bill_item_delete_reason"]').forEach(input=>{input.checked=false;});$('billItemDeleteOtherReason').value='';$('billItemDeleteOtherReasonWrap').classList.add('hidden');$('billItemError').classList.add('hidden');
    $('billItemDescription').textContent=`${name} · تعداد فعلی: ${digits(current)}`;updateBillEditPreview();modalOpen(els.billItemModal);
  }
  async function saveBillItem(button) {
    const itemId=Number($('billItemId').value),current=Number($('billItemCurrent').value),next=billFinalQuantity();let payload;
    const error=$('billItemError');
    if(next===current)return;
    if(next>current){
      const operationId=els.billItemModal.dataset.operationId||requestId('bill-add');els.billItemModal.dataset.operationId=operationId;
      const expectedPrice=Number($('billItemExpectedPrice').value);if(!Number.isFinite(expectedPrice)||expectedPrice<0){error.textContent='قیمت فعلی این آیتم مشخص نیست؛ صفحه را تازه کنید.';error.classList.remove('hidden');return;}payload={action:'add_item',order_item_id:itemId,quantity:next-current,expected_price:expectedPrice,request_id:operationId};
    }else{
      const reason=next===0?billDeleteReason():'اصلاح سریع';if(next===0&&!reason){error.textContent='دلیل حذف کامل را انتخاب کنید.';error.classList.remove('hidden');return;}
      const requiresPreparation=$('billItemRequiresPreparation')?.value==='1';const removed=current-next;const prepared=Math.max(0,Math.min(removed,billPreparedQuantity()));
      payload={action:'adjust_item',order_item_id:itemId,quantity:next,reason};if(requiresPreparation)payload.prepared_removed_quantity=prepared;
    }
    try{await CafeUI.runAction({button,loadingText:'در حال ثبت…',successText:'ثبت شد',request:()=>post(window.OPERATOR_BILL_API,payload),onSuccess:async()=>{modalClose(els.billItemModal);state.snapshot='';window.setTimeout(()=>{void load(true,{abortPrevious:true,forceFull:true});},0);}});}catch(err){error.textContent=CafeUI.requestErrorMessage(err);error.classList.remove('hidden');}
  }

  async function changeDiscount(button,clear=false) {
    const panel=button.closest('[data-discount-table]');if(!panel)return;
    const error=panel.querySelector('.discount-inline-error');error?.classList.add('hidden');
    const normalized=normalizeNumericInput(panel.querySelector('.bill-discount-value')?.value||'');
    const value=clear?0:Number(normalized||0),type=clear?'none':(panel.dataset.discountType==='fixed'?'fixed':'percent');
    if(!clear&&value<=0){if(error){error.textContent='مقدار تخفیف را وارد کنید.';error.classList.remove('hidden');}return;}
    button.disabled=true;
    try{await post(window.OPERATOR_BILL_API,{action:'discount',table_id:Number(panel.dataset.discountTable),discount_type:type,discount_value:value});state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});}
    catch(err){if(error){error.textContent=CafeUI.requestErrorMessage(err);error.classList.remove('hidden');}else CafeUI.toast(CafeUI.requestErrorMessage(err),'error');}
    finally{button.disabled=false;}
  }
  async function reprintPrep(button) {
    const operationId=requestId('prep-reprint');
    try{
      await CafeUI.runAction({button,loadingText:'در حال ارسال…',successText:'وارد صف شد',request:()=>post(window.OPERATOR_BILL_API,{action:'reprint_prep',order_id:Number(button.dataset.order),request_id:operationId}),onSuccess:async(data)=>{
        if(data.request_id&&data.request_id!==operationId)throw new Error('پاسخ چاپ با درخواست فعلی یکسان نبود.');
        CafeUI.toast(data.message||'فیش آماده‌سازی وارد صف چاپ شد.');
      }});
    }catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}
  }

  function setSettlementLocked(modal,locked){
    if(!modal)return;modal.classList.toggle('is-transaction-locked',locked);modal.dataset.escapeClose=locked?'0':'1';
    modal.querySelectorAll('.settlement-close,.settlement-back-button').forEach(control=>control.classList.toggle('hidden',locked));
    modal.querySelectorAll('[data-settlement-lock-message]').forEach(message=>message.classList.toggle('hidden',!locked));
  }
  function returnToSettlementChoice(modal){setSettlementLocked(modal,false);modalClose(modal);modalOpen(els.checkoutModal);}

  function itemizedRowsForTable(table) {
    const rows=[];
    (table?.bill_items||[]).forEach((line,index)=>{
      const sources=(Array.isArray(line.source_lines)?line.source_lines:[]).map(source=>({
        ...source,
        id:Number(source.id||0),
        remaining_quantity:Math.max(0,Number(source.remaining_quantity??source.quantity??0)),
        unit_price:Number(source.unit_price??line.unit_price??0),
      })).filter(source=>source.id>0&&source.remaining_quantity>0);
      const remaining=sources.reduce((sum,source)=>sum+source.remaining_quantity,0);
      if(remaining<1)return;
      rows.push({
        key:String(index),item_name:String(line.item_name||'آیتم'),item_note:String(line.item_note||''),unit_price:Number(line.unit_price||0),
        ordered_quantity:Math.max(0,Number(line.quantity||0)),paid_quantity:Math.max(0,Number(line.paid_quantity||0)),remaining_quantity:remaining,sources,
      });
    });
    return rows;
  }
  function itemizedPayloadItems(){
    const payload=[];
    for(const row of (state.itemizedRows||[])){
      let needed=Math.max(0,Number(state.itemizedSelection.get(row.key)||0));
      if(!needed)continue;
      for(const source of row.sources){
        const take=Math.min(needed,Number(source.remaining_quantity||0));
        if(take>0)payload.push({order_item_id:Number(source.id),quantity:take});
        needed-=take;if(needed<=0)break;
      }
      if(needed>0)throw new Error('تعداد انتخاب‌شده با مانده واقعی حساب هماهنگ نیست؛ حساب را تازه کنید.');
    }
    return payload;
  }
  function itemizedSelectionKey(items){return (items||[]).map(row=>`${Number(row.order_item_id)}:${Number(row.quantity)}`).sort().join('|');}
  function itemizedSelectedGross(){return (state.itemizedRows||[]).reduce((sum,row)=>sum+Math.max(0,Number(state.itemizedSelection.get(row.key)||0))*Number(row.unit_price||0),0);}
  function setItemizedFlowNotice(message='',tone='success'){
    const node=$('itemizedFlowNotice');if(!node)return;
    if(state.itemizedNoticeTimer){clearTimeout(state.itemizedNoticeTimer);state.itemizedNoticeTimer=null;}
    const text=String(message||'');node.textContent=text;node.classList.toggle('hidden',!text);node.classList.toggle('is-warning',tone==='warning');
    if(text&&tone!=='warning')state.itemizedNoticeTimer=window.setTimeout(()=>{state.itemizedNoticeTimer=null;if(node.textContent===text){node.classList.add('hidden');node.textContent='';}},2600);
  }
  function applyItemizedReview(review,items){
    const submit=$('submitItemizedSettlement'),breakdown=$('itemizedSelectionBreakdown'),label=$('itemizedSelectionAmountLabel');
    state.itemizedReview={...review,items,selectionKey:itemizedSelectionKey(items)};
    if(label)label.textContent='مبلغ این پرداخت';
    if($('itemizedSelectionGross'))$('itemizedSelectionGross').textContent=money(review.total);
    if(breakdown){
      const discount=Number(review.discount||0),tax=Number(review.tax||0);const parts=[];if(discount>0)parts.push(`قبل تخفیف ${money(review.subtotal)}`,`سهم تخفیف ${money(discount)}`);if(tax>0)parts.push(`مالیات ${money(tax)}`);if(review.closes_session)parts.push('آخرین پرداخت · حساب کامل می‌شود');breakdown.textContent=parts.join(' · ');
      breakdown.classList.toggle('hidden',!breakdown.textContent);
    }
    if(submit){const units=(items||[]).reduce((sum,row)=>sum+Math.max(0,Number(row.quantity||0)),0);submit.disabled=false;submit.textContent=`${digits(units)} عدد · پرداخت ${money(review.total)}`;}
  }
  async function fetchItemizedReview(items){
    const tableId=Number($('checkoutTableId').value),key=itemizedSelectionKey(items),seq=++state.itemizedReviewSeq;
    const data=await post(window.OPERATOR_SESSION_API,{action:'checkout_itemized_review',table_id:tableId,expected_session_id:Number($('checkoutSessionId').value),expected_total:Number($('checkoutExpectedTotal').value),expected_signature:$('checkoutExpectedSignature').value,items,request_id:requestId('itemized-review')});
    const review=data.review||{};if(!Array.isArray(review.lines)||Number(review.total)<0)throw new Error('پاسخ بررسی پرداخت کامل نیست.');
    if(seq!==state.itemizedReviewSeq)return null;
    let current=[];try{current=itemizedPayloadItems();}catch(_){return null;}
    if(key!==itemizedSelectionKey(current))return null;
    applyItemizedReview(review,items);$('itemizedSelectionError')?.classList.add('hidden');return state.itemizedReview;
  }
  function scheduleItemizedReview(){
    if(state.itemizedReviewTimer){clearTimeout(state.itemizedReviewTimer);state.itemizedReviewTimer=null;}
    const submit=$('submitItemizedSettlement'),breakdown=$('itemizedSelectionBreakdown'),label=$('itemizedSelectionAmountLabel');
    let items=[];try{items=itemizedPayloadItems();}catch(error){if(submit){submit.disabled=true;submit.textContent='حساب را تازه کنید';}return;}
    if(!items.length){state.itemizedReview=null;if(label)label.textContent='جمع انتخاب‌شده';if(breakdown)breakdown.classList.add('hidden');if(submit){submit.disabled=true;submit.textContent='یک قلم انتخاب کنید';}return;}
    const key=itemizedSelectionKey(items);
    if(state.itemizedReview?.selectionKey===key){applyItemizedReview(state.itemizedReview,items);return;}
    state.itemizedReview=null;if(label)label.textContent='جمع قبل تخفیف';if(breakdown)breakdown.classList.add('hidden');if(submit){submit.disabled=true;submit.textContent='در حال محاسبه مبلغ…';}
    state.itemizedReviewTimer=window.setTimeout(()=>{state.itemizedReviewTimer=null;fetchItemizedReview(items).catch(error=>{const node=$('itemizedSelectionError');if(node){node.textContent=CafeUI.requestErrorMessage(error);node.classList.remove('hidden');}if(submit){submit.disabled=false;submit.textContent='بررسی و ثبت پرداخت';}});},180);
  }
  function renderItemizedSelection(){
    const rows=state.itemizedRows||[];
    let selectedUnits=0;
    $('itemizedSelectionList').innerHTML=rows.length?rows.map(row=>{
      const qty=Math.max(0,Math.min(row.remaining_quantity,Number(state.itemizedSelection.get(row.key)||0)));
      selectedUnits+=qty;
      const paidMeta=row.paid_quantity>0?`<em>${digits(row.paid_quantity)} پرداخت‌شده</em>`:'';
      const noteMeta=row.item_note?`<span class="itemized-selection-note">${esc(row.item_note)}</span>`:'';
      return `<div class="itemized-selection-row ${qty>0?'is-selected':''}"><div class="itemized-selection-copy"><strong>${esc(row.item_name)}</strong><small class="itemized-selection-meta"><span>مانده ${digits(row.remaining_quantity)} عدد · ${money(row.unit_price)}</span>${paidMeta}${noteMeta}</small></div><div class="itemized-qty-stepper" data-itemized-key="${esc(row.key)}"><button type="button" data-itemized-step="-1" aria-label="کم‌کردن ${esc(row.item_name)}" ${qty<=0?'disabled':''}>${spriteIcon('minus')}</button><output class="${qty>0?'':'is-zero'}" aria-label="تعداد انتخاب‌شده">${qty>0?digits(qty):''}</output><button type="button" data-itemized-step="1" aria-label="افزودن ${esc(row.item_name)}" ${qty>=row.remaining_quantity?'disabled':''}>${spriteIcon('plus')}</button></div></div>`;
    }).join(''):'<div class="empty-state">قلم پرداخت‌نشده‌ای باقی نمانده است.</div>';
    const gross=itemizedSelectedGross();
    $('itemizedSelectionGross').textContent=money(gross);
    $('itemizedSelectionCount').textContent=selectedUnits>0?`${digits(selectedUnits)} عدد انتخاب شده`:'هنوز قلمی انتخاب نشده';
    $('itemizedSelectionError')?.classList.add('hidden');scheduleItemizedReview();
  }
  function populateItemizedSettlement(table,{notice=''}={}){
    state.itemizedRows=itemizedRowsForTable(table);state.itemizedSelection=new Map();state.itemizedReview=null;state.itemizedReviewSeq++;if(state.itemizedReviewTimer){clearTimeout(state.itemizedReviewTimer);state.itemizedReviewTimer=null;}els.itemizedSettlementModal.dataset.operationId='';
    const total=Number(table.bill_final_total||0),paid=Number(table.bill_paid_total||0),remaining=Number(table.bill_remaining_total??total);
    const mobileSettlementView=window.matchMedia('(max-width:600px)').matches;
    $('itemizedAccountSummary').innerHTML=mobileSettlementView
      ?`<div class="itemized-mobile-balance"><span>مانده حساب</span><strong>${money(remaining)}</strong>${paid>0?`<small>پرداخت‌شده ${money(paid)}</small>`:''}</div>`
      :`<div><span>جمع حساب</span><strong>${money(total)}</strong></div><div><span>پرداخت‌شده</span><strong>${money(paid)}</strong></div><div><span>مانده حساب</span><strong>${money(remaining)}</strong></div>`;
    $('itemizedSettlementSubtitle').textContent=`${faTextDigits(table.name)} · سهم این مشتری را انتخاب کنید.`;
    const missed=$('addMissedItemizedItem');if(missed){missed.classList.toggle('hidden',!Boolean(table.bill_itemized_active));missed.dataset.table=String(Number(table.id));}
    const back=$('backItemizedSettlement');if(back){back.textContent='تغییر روش تسویه';back.classList.toggle('hidden',Boolean(table.bill_itemized_active));}if(els.itemizedSettlementModal)els.itemizedSettlementModal.classList.toggle('is-continuation',Boolean(table.bill_itemized_active));
    setItemizedFlowNotice(notice);renderItemizedSelection();setSettlementLocked(els.itemizedSettlementModal,false);
  }
  function openItemizedSettlement(table,options={}){populateItemizedSettlement(table,options);if(els.itemizedSettlementModal?.classList.contains('hidden'))modalOpen(els.itemizedSettlementModal);}
  function selectAllItemizedRemaining(){for(const row of (state.itemizedRows||[]))state.itemizedSelection.set(row.key,row.remaining_quantity);state.itemizedReview=null;renderItemizedSelection();}
  function changeItemizedQuantity(button){
    const stepper=button.closest('[data-itemized-key]'),key=String(stepper?.dataset.itemizedKey||''),row=(state.itemizedRows||[]).find(item=>item.key===key);if(!row)return;
    const next=Math.max(0,Math.min(row.remaining_quantity,Number(state.itemizedSelection.get(key)||0)+Number(button.dataset.itemizedStep||0)));
    if(next>0)state.itemizedSelection.set(key,next);else state.itemizedSelection.delete(key);state.itemizedReview=null;renderItemizedSelection();
  }
  function prepareSettlementContext(table,{resetPrint=true}={}){
    const pending=Number(table?.pending_preparation_adjustments||0),remaining=Math.max(0,Number(table.bill_remaining_total??table.bill_final_total??0)),paid=Math.max(0,Number(table.bill_paid_total||0));
    $('checkoutTableId').value=String(Number(table.id));$('checkoutSessionId').value=Number(table?.session_id||0);$('checkoutExpectedTotal').value=remaining;$('checkoutExpectedSignature').value=String(table?.bill_signature||'');
    const tax=Math.max(0,Number(table.bill_tax||0));$('checkoutModalSummary').textContent=`${tax>0?`مالیات ${moneyNumber(tax)} تومان · `:''}${paid>0?`جمع ${moneyNumber(table.bill_final_total)} · پرداخت‌شده ${moneyNumber(paid)} · `:''}مانده قابل تسویه: ${moneyNumber(remaining)} تومان${pending?` · ${digits(pending)} اصلاحیه آماده‌سازی هنوز باز است و بعد از تسویه هم باقی می‌ماند.`:''}`;
    $('directSettlementChoiceLabel').textContent=paid>0?'پرداخت تمام مانده':'تسویه';
    const itemizedActive=Boolean(table.bill_itemized_active);$('checkoutItemizedLockNote')?.classList.toggle('hidden',!itemizedActive);els.checkoutModal.querySelectorAll('[data-settlement="accommodation"],[data-settlement="subscriber"]').forEach(control=>{control.disabled=itemizedActive;control.setAttribute('aria-disabled',itemizedActive?'true':'false');});
    const box=$('checkoutPrintFinal');if(resetPrint)box.checked=Boolean(window.CUSTOMER_PRINT_CONFIGURED&&window.CHECKOUT_PRINT_DEFAULT);box.disabled=!window.CUSTOMER_PRINT_CONFIGURED;setSettlementLocked(els.checkoutModal,false);
  }
  function openSettlement(button){
    const tableId=Number(button.dataset.table||0),table=state.tables.find(item=>Number(item.id)===tableId);if(!table)return;
    prepareSettlementContext(table,{resetPrint:true});
    if(table.bill_itemized_active){modalClose(els.checkoutModal);openItemizedSettlement(table);return;}
    modalOpen(els.checkoutModal);
  }
  function settlementChoice(type){
    const tableId=Number($('checkoutTableId').value),table=state.tables.find(t=>Number(t.id)===tableId);if(!table)return;
    if(table.bill_itemized_active&&['accommodation','subscriber'].includes(type))return CafeUI.toast('پس از شروع پرداخت جداگانه، مقصد تسویه تا پایان مانده قفل است.','warning');
    modalClose(els.checkoutModal);
    if(type==='direct'){els.directSettlementModal.dataset.operationId='';$('confirmDirectSettlement').textContent=Number(table.bill_paid_total||0)>0?'پرداخت تمام مانده':'تأیید تسویه';$('directSettlementTable').textContent=faTextDigits(table.name);$('directSettlementAmount').textContent=moneyNumber($('checkoutExpectedTotal').value);$('directSettlementError')?.classList.add('hidden');setSettlementLocked(els.directSettlementModal,false);modalOpen(els.directSettlementModal);return;}
    if(type==='itemized'){openItemizedSettlement(table);return;}
    if(type==='accommodation'){openAccommodation(table);return;}if(type==='subscriber'){openSubscriber(table);}
  }
  function backFromItemizedSettlement(){
    const tableId=Number($('checkoutTableId').value),table=state.tables.find(t=>Number(t.id)===tableId);
    setItemizedFlowNotice('');if(table?.bill_itemized_active){modalClose(els.itemizedSettlementModal);return;}returnToSettlementChoice(els.itemizedSettlementModal);
  }
  function openMissedItemizedItem(){
    const tableId=Number($('checkoutTableId').value),table=state.tables.find(t=>Number(t.id)===tableId);if(!table?.bill_itemized_active)return CafeUI.toast('ابتدا اولین پرداخت جداگانه را ثبت کنید.','warning');
    window.location.assign(quickOrderUrl(tableId,'table-panel','late_accounting'));
  }
  async function continueItemizedSettlement(tableId,data){
    setSettlementLocked(els.itemizedSettlementModal,false);state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});
    const table=state.tables.find(item=>Number(item.id)===Number(tableId));
    if(!table?.active){modalClose(els.itemizedSettlementModal);closeTable();CafeUI.toast('پرداخت ثبت شد، اما وضعیت میز با انتظار مطابقت ندارد؛ صفحه را تازه کنید.','warning');return;}
    state.selectedTableId=tableId;openTable(tableId);prepareSettlementContext(table,{resetPrint:false});
    const paidNow=Number(data.total_amount||0),printWarning=String(data?.print_warning||'').trim();
    const notice=`پرداخت ${money(paidNow)} ثبت شد`;
    openItemizedSettlement(table,{notice});
    if(printWarning)setItemizedFlowNotice(`${notice} · چاپ رسید نیازمند بررسی است`,'warning');
  }
  async function submitItemizedSettlement(button){
    const tableId=Number($('checkoutTableId').value);let items=[];
    try{items=itemizedPayloadItems();if(!items.length)throw new Error('حداقل یک قلم برای پرداخت انتخاب کنید.');}
    catch(error){const node=$('itemizedSelectionError');node.textContent=error.message;node.classList.remove('hidden');return;}
    const key=itemizedSelectionKey(items);let review=state.itemizedReview?.selectionKey===key?state.itemizedReview:null;
    try{
      if(!review){button.disabled=true;button.textContent='در حال بررسی مبلغ…';review=await fetchItemizedReview(items);if(!review)return;}
      const operationId=els.itemizedSettlementModal.dataset.operationId||requestId('settle-itemized');els.itemizedSettlementModal.dataset.operationId=operationId;setSettlementLocked(els.itemizedSettlementModal,true);
      await CafeUI.runAction({button,loadingText:'در حال ثبت پرداخت…',successText:'پرداخت ثبت شد',request:()=>post(window.OPERATOR_SESSION_API,{action:'checkout_itemized',table_id:tableId,expected_session_id:Number($('checkoutSessionId').value),expected_total:Number($('checkoutExpectedTotal').value),expected_signature:$('checkoutExpectedSignature').value,items:review.items,print_final:$('checkoutPrintFinal').checked,request_id:operationId}),onSuccess:async(data)=>{
        if(!data.persisted||data.request_id!==operationId||Number(data.table_id)!==tableId)throw new Error('سرور نتیجه قطعی پرداخت را تأیید نکرد.');els.itemizedSettlementModal.dataset.operationId='';
        if(Boolean(data.closes_session))await finalizeSettlementUi(tableId,data,[els.itemizedSettlementModal],true);else await continueItemizedSettlement(tableId,data);
      }});
    }catch(error){setSettlementLocked(els.itemizedSettlementModal,false);if(await handleSettlementChanged(error,tableId,[els.itemizedSettlementModal]))return;if(!error?.data&&!error?.httpStatus&&await reconcileUnknownSettlement(tableId,els.itemizedSettlementModal.dataset.operationId,'direct','این پرداخت قبلاً با موفقیت ثبت شده است.',[els.itemizedSettlementModal]))return;const node=$('itemizedSelectionError');if(node){node.textContent=CafeUI.requestErrorMessage(error);node.classList.remove('hidden');}renderItemizedSelection();}
  }
  function settlementPrintWarning(result){return String(result?.print_warning||result?.checkout?.print_warning||'').trim();}
  function settlementClosedMessage(tableId){
    const table=state.tables.find(item=>Number(item.id)===Number(tableId));
    const name=String(table?.name||`میز ${digits(tableId)}`).trim();
    return `حساب ${faTextDigits(name)} تسویه و بسته شد`;
  }
  async function finalizeSettlementUi(tableId,result={},modals=[],closesSession=true) {
    const completionMessage=settlementClosedMessage(tableId),printWarning=settlementPrintWarning(result);
    modals.forEach(modal=>modalClose(modal));modalClose(els.checkoutModal);CafeUI.dialog?.closeAll?.();
    if(closesSession)closeTable();
    state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});
    const table=state.tables.find(item=>Number(item.id)===Number(tableId));
    if(closesSession){
      if(table?.active){CafeUI.toast('سند ثبت شد، اما تازه‌سازی وضعیت میز کامل نشد؛ اطلاعات را دوباره تازه کنید.','warning');return;}
      CafeUI.toast(completionMessage,'',{timeout:2800});
      if(printWarning)window.setTimeout(()=>CafeUI.toast(printWarning,'warning',{timeout:5000}),300);
      return;
    }
    if(table?.active){state.selectedTableId=tableId;openTable(tableId);}else CafeUI.toast('پرداخت ثبت شد، اما وضعیت میز با انتظار مطابقت ندارد؛ صفحه را تازه کنید.','warning');
  }
  async function reconcileUnknownSettlement(tableId,operationId,destination,message,modals=[]) {
    try{
      const response=await fetch(`${window.OPERATOR_SETTLEMENTS_API}?request_id=${encodeURIComponent(operationId)}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
      const data=await readJsonResponse(response);
      if(response.ok&&data.success&&data.found&&Number(data.table_id)===Number(tableId)&&String(data.destination)===String(destination)){
        if(!data.closes_session&&els.itemizedSettlementModal&&!els.itemizedSettlementModal.classList.contains('hidden')){els.itemizedSettlementModal.dataset.operationId='';state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});const table=state.tables.find(item=>Number(item.id)===Number(tableId));if(table?.active){state.selectedTableId=tableId;openTable(tableId);prepareSettlementContext(table,{resetPrint:false});openItemizedSettlement(table,{notice:message||'پرداخت قبلاً با موفقیت ثبت شده است.'});return true;}}
        modals.forEach(modal=>modalClose(modal));modalClose(els.checkoutModal);if(data.closes_session)closeTable();CafeUI.toast(message||'تسویه قبلاً با موفقیت ثبت شده است.');state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});const table=state.tables.find(item=>Number(item.id)===Number(tableId));if(!data.closes_session&&table?.active){state.selectedTableId=tableId;openTable(tableId);}return true;
      }
    }catch(_){ }
    return false;
  }
  async function handleSettlementChanged(error,tableId,modals=[]){
    if(error?.data?.code!=='settlement_changed')return false;
    modals.forEach(modal=>{if(modal)modal.dataset.operationId='';modalClose(modal);});modalClose(els.checkoutModal);closeTable();
    CafeUI.toast(error.data.message||'حساب تغییر کرده است؛ مبلغ جدید را دوباره بررسی کنید.','warning');state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});
    const table=state.tables.find(item=>Number(item.id)===Number(tableId));if(table?.active)openTable(tableId);return true;
  }
  async function confirmDirect(button) {
    const tableId=Number($('checkoutTableId').value),operationId=els.directSettlementModal.dataset.operationId||requestId('settle-direct');els.directSettlementModal.dataset.operationId=operationId;setSettlementLocked(els.directSettlementModal,true);
    try{await CafeUI.runAction({button,loadingText:'در حال ثبت تسویه…',successText:'تسویه شد',request:()=>post(window.OPERATOR_SESSION_API,{action:'checkout_direct',table_id:tableId,expected_session_id:Number($('checkoutSessionId').value),expected_total:Number($('checkoutExpectedTotal').value),expected_signature:$('checkoutExpectedSignature').value,print_final:$('checkoutPrintFinal').checked,request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId||Number(data.table_id)!==tableId)throw new Error('سرور نتیجه قطعی تسویه را تأیید نکرد.');els.directSettlementModal.dataset.operationId='';await finalizeSettlementUi(tableId,data,[els.directSettlementModal]);}});}catch(error){setSettlementLocked(els.directSettlementModal,false);if(await handleSettlementChanged(error,tableId,[els.directSettlementModal]))return;if(!error?.data&&!error?.httpStatus&&await reconcileUnknownSettlement(tableId,operationId,'direct','تسویه قبلاً با موفقیت ثبت شده است.',[els.directSettlementModal]))return;const node=$('directSettlementError');if(node){node.textContent=CafeUI.requestErrorMessage(error);node.classList.remove('hidden');}}
  }
  function openSubscriber(table) {
    els.subscriberModal.dataset.operationId='';state.selectedSubscriber=null;state.subscriberResults=[];state.subscriberAbort?.abort();
    $('subscriberAmountSummary').textContent=`${faTextDigits(table.name)} · ${moneyNumber($('checkoutExpectedTotal').value)} تومان`;setSettlementLocked(els.subscriberModal,false);
    $('subscriberSearch').value='';$('subscriberSearchResult').innerHTML='<div class="empty-state">نام یا شماره موبایل مشتری را وارد کنید.</div>';
    $('subscriberSearchResult').classList.remove('hidden');$('subscriberConfirm').classList.add('hidden');
    modalOpen(els.subscriberModal);focusSettlementControl($('subscriberSearch'));
  }
  function searchQueryReady(query){const value=String(query||'').trim();const digitCount=normalizeNumericInput(value).length;const letterCount=[...value.replace(/[0-9۰-۹٠-٩\s+_.@-]/gu,'')].length;return digitCount>=3||letterCount>=2;}
  function runSettlementSearchAction(inputId,searchFn){
    const input=$(inputId),value=String(input?.value||'').trim();
    const ready=searchQueryReady(value);
    const result=searchFn(value);
    if(ready)CafeUI.keyboard?.dismissForTouch?.(input);
    return result;
  }
  async function searchSubscribers(query=$('subscriberSearch')?.value||'') {
    const value=String(query).trim();
    state.subscriberAbort?.abort();
    if(!searchQueryReady(value)){state.subscriberResults=[];$('subscriberSearchResult').innerHTML='<div class="empty-state">حداقل دو حرف نام یا سه رقم موبایل وارد کنید.</div>';return;}
    state.subscriberAbort=new AbortController();
    const root=$('subscriberSearchResult');root.classList.remove('hidden');root.innerHTML='<div class="empty-state">در حال جست‌وجو…</div>';
    try{
      const response=await fetch(`${window.OPERATOR_SUBSCRIBERS_API}?q=${encodeURIComponent(value)}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:state.subscriberAbort.signal});
      const data=await readJsonResponse(response);if(!response.ok||!data.success)throw new Error(data.message||'جست‌وجو انجام نشد.');
      state.subscriberResults=Array.isArray(data.items)?data.items:[];
      root.innerHTML=state.subscriberResults.length?state.subscriberResults.map((item)=>`<button class="subscriber-result-row" type="button" data-subscriber-id="${Number(item.id)}"><span class="settlement-result-main"><strong>${esc(item.name)}</strong><small class="subscriber-mobile" dir="ltr">${esc(faTextDigits(item.mobile))}</small></span><span class="settlement-result-badge"><small>مانده فعلی</small><b>${moneyNumber(item.balance)}</b></span></button>`).join(''):'<div class="empty-state">مشتری فعالی با این مشخصات پیدا نشد.</div>';
    }catch(error){if(error.name==='AbortError')return;state.subscriberResults=[];root.innerHTML=`<div class="alert alert-error">${esc(CafeUI.requestErrorMessage(error))}</div>`;}
  }

  function selectSubscriber(item) {
    state.selectedSubscriber=item;const table=state.tables.find((t)=>Number(t.id)===Number($('checkoutTableId').value));const expectedTotal=Number($('checkoutExpectedTotal').value||0),next=Number(item.balance)+expectedTotal;$('subscriberConfirm').innerHTML=`<div class="settlement-confirm-card"><header><span><strong>${esc(item.name)}</strong><small class="subscriber-mobile" dir="ltr">${esc(faTextDigits(item.mobile))}</small></span></header><dl><div><dt>مانده فعلی</dt><dd>${moneyNumber(item.balance)}</dd></div><div><dt>فاکتور جدید</dt><dd>${moneyNumber(expectedTotal)}</dd></div><div class="is-total"><dt>مانده پس از ثبت</dt><dd>${moneyNumber(next)}</dd></div></dl></div><div class="modal-actions"><button class="btn btn-primary" type="button" id="confirmSubscriberCharge">ثبت در حساب مشتری</button><button class="btn btn-light" type="button" id="backSubscriberSearch">انتخاب مشتری دیگر</button></div>`;$('subscriberSearchResult').classList.add('hidden');$('subscriberConfirm').classList.remove('hidden');
  }
  async function confirmSubscriber(button) {
    if(!state.selectedSubscriber)return;const tableId=Number($('checkoutTableId').value),operationId=els.subscriberModal.dataset.operationId||requestId('settle-subscriber');els.subscriberModal.dataset.operationId=operationId;setSettlementLocked(els.subscriberModal,true);
    try{await CafeUI.runAction({button,loadingText:'در حال ثبت حساب…',successText:'ثبت شد',request:()=>post(window.OPERATOR_SUBSCRIBERS_API,{action:'charge',table_id:tableId,subscriber_id:Number(state.selectedSubscriber.id),expected_session_id:Number($('checkoutSessionId').value),expected_total:Number($('checkoutExpectedTotal').value),expected_signature:$('checkoutExpectedSignature').value,print_final:$('checkoutPrintFinal').checked,request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId||Number(data.table_id)!==tableId)throw new Error('سرور نتیجه قطعی ثبت حساب مشتری را تأیید نکرد.');els.subscriberModal.dataset.operationId='';await finalizeSettlementUi(tableId,data,[els.subscriberModal]);}});}catch(error){setSettlementLocked(els.subscriberModal,false);if(await handleSettlementChanged(error,tableId,[els.subscriberModal]))return;if(!error?.data&&!error?.httpStatus&&await reconcileUnknownSettlement(tableId,operationId,'subscriber','ثبت حساب مشتری قبلاً با موفقیت انجام شده است.',[els.subscriberModal]))return;const node=$('subscriberSettlementError');if(node){node.textContent=CafeUI.requestErrorMessage(error);node.classList.remove('hidden');}}
  }
  function openAccommodation(table) {
    if(!els.accommodationModal)return;state.accommodationAbort?.abort();state.accommodationAbort=null;setSettlementLocked(els.accommodationModal,false);state.selectedReservation=null;$('accommodationTableId').value=table.id;$('accommodationAmount').value=Number($('checkoutExpectedTotal').value||0);$('accommodationSearchQuery').value='';$('accommodationSearchButton').disabled=false;$('accommodationSearchResult').innerHTML='<div class="empty-state">رزرو فعال مهمان را جست‌وجو کنید.</div>';$('accommodationSearchResult').classList.remove('hidden');$('accommodationConfirm').classList.add('hidden');modalOpen(els.accommodationModal);focusSettlementControl($('accommodationSearchQuery'));
  }
  async function searchAccommodation(query=$('accommodationSearchQuery')?.value||'') {
    const value=String(query).trim(),root=$('accommodationSearchResult'),errorNode=$('accommodationSettlementError');state.accommodationAbort?.abort();
    if(!searchQueryReady(value)){root.dataset.items='[]';root.innerHTML='<div class="empty-state">حداقل دو حرف یا سه رقم وارد کنید.</div>';return;}
    state.accommodationAbort=new AbortController();$('accommodationSearchButton').disabled=true;errorNode?.classList.add('hidden');root.innerHTML='<div class="empty-state">در حال جست‌وجو…</div>';
    try{const data=await post(window.ACCOMMODATION_API,{action:'search',query:value},{signal:state.accommodationAbort.signal});const items=data.reservations||[];root.dataset.items=JSON.stringify(items);root.innerHTML=items.length?items.map((r,i)=>{const rooms=(r.room_names||[]).join('، ')||r.room_name||'اتاق نامشخص';return `<button class="accommodation-reservation-card" type="button" data-reservation-index="${i}" ${r.charge_allowed?'':'disabled'}><span class="settlement-result-main"><strong>${esc(r.guest_name||'مهمان')}</strong><b>${esc(faTextDigits(rooms))}</b><small>${esc(r.check_in||'—')} تا ${esc(r.check_out||'—')} · کد ${esc(faTextDigits(r.reservation_code||''))}</small>${!r.charge_allowed&&r.charge_block_reason?`<em>${esc(r.charge_block_reason)}</em>`:''}</span><span class="settlement-result-badge ${r.charge_allowed?'is-ready':'is-blocked'}">${r.charge_allowed?'قابل ثبت':'غیرفعال'}</span></button>`;}).join(''):'<div class="empty-state">رزرو فعالی با این مشخصات پیدا نشد.</div>';}catch(error){if(error.name==='AbortError')return;if(errorNode){errorNode.textContent=CafeUI.requestErrorMessage(error);errorNode.classList.remove('hidden');}root.innerHTML='<div class="empty-state">جست‌وجو کامل نشد.</div>';}finally{if(state.accommodationAbort&&!state.accommodationAbort.signal.aborted)$('accommodationSearchButton').disabled=false;}
  }
  function selectReservation(index){const items=JSON.parse($('accommodationSearchResult').dataset.items||'[]'),r=items[index];if(!r||!r.charge_allowed)return;state.selectedReservation=r;$('accommodationGuest').textContent=r.guest_name;$('accommodationRoom').textContent=faTextDigits((r.room_names||[]).join('، ')||r.room_name);$('accommodationDates').textContent=`${r.check_in||'—'} تا ${r.check_out||'—'}`;$('accommodationCode').textContent=faTextDigits(r.reservation_code);$('accommodationFinalAmount').textContent=`${moneyNumber($('accommodationAmount').value)} تومان`;$('accommodationExternalId').textContent=`CAFE-S-${Number($('checkoutSessionId').value)||'—'}`;$('accommodationSearchResult').classList.add('hidden');$('accommodationConfirm').classList.remove('hidden');}
  async function postAccommodation(button){
    if(!state.selectedReservation)return;const tableId=Number($('accommodationTableId').value),operationId=requestId('accommodation-charge');setSettlementLocked(els.accommodationModal,true);
    try{await CafeUI.runAction({button,loadingText:'در حال ثبت در اقامتگاه…',successText:'ثبت شد',request:()=>post(window.ACCOMMODATION_API,{action:'charge',table_id:tableId,reservation_code:state.selectedReservation.reservation_code,expected_session_id:Number($('checkoutSessionId').value),expected_total:Number($('checkoutExpectedTotal').value),expected_signature:$('checkoutExpectedSignature').value,print_final:$('checkoutPrintFinal').checked,request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId||Number(data.table_id)!==tableId)throw new Error('سرور نتیجه قطعی انتقال اقامت را تأیید نکرد.');await finalizeSettlementUi(tableId,data,[els.accommodationModal]);}});}catch(error){
      setSettlementLocked(els.accommodationModal,false);if(await handleSettlementChanged(error,tableId,[els.accommodationModal]))return;const errorNode=$('accommodationSettlementError');if(error.data?.transfer){modalClose(els.accommodationModal);modalClose(els.checkoutModal);state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});openTable(tableId);}else{const node=$('accommodationSettlementError');node.textContent=CafeUI.requestErrorMessage(error);node.classList.remove('hidden');}
    }
  }
  async function retryAccommodation(button){
    const operationId=requestId('accommodation-retry');
    try{await CafeUI.runAction({button,loadingText:'در حال پیگیری…',successText:'بررسی شد',request:()=>post(window.ACCOMMODATION_API,{action:'retry',transfer_id:Number(button.dataset.transfer),request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId)throw new Error('سرور نتیجه پیگیری انتقال را تأیید نکرد.');CafeUI.toast(data.message);state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});}});}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}
  }
  async function finalizeAccommodationLocal(button){
    const operationId=requestId('accommodation-local-finalize');
    try{await CafeUI.runAction({button,loadingText:'در حال تکمیل تسویه…',successText:'تکمیل شد',request:()=>post(window.ACCOMMODATION_API,{action:'finalize_local',transfer_id:Number(button.dataset.transfer),request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId)throw new Error('سرور تکمیل تسویه محلی را تأیید نکرد.');CafeUI.toast(data.message);state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});if(state.selectedTableId)openTable(state.selectedTableId);}});}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}
  }
  async function detachAccommodation(button){
    const transferId=Number(button.dataset.transfer);
    if(!transferId)return;
    if(!await CafeUI.confirm('میز برای مهمان بعدی آزاد می‌شود، اما این فاکتور تسویه‌شده محسوب نمی‌شود و با همان شناسه در پیگیری‌های باز باقی می‌ماند.','آزادسازی میز با حفظ حساب',{okLabel:'آزادسازی و انتقال به پیگیری',danger:true,trigger:button}))return;
    const operationId=requestId('accommodation-followup');
    try{await CafeUI.runAction({button,loadingText:'در حال آزادسازی…',successText:'منتقل شد',request:()=>post(window.ACCOMMODATION_API,{action:'detach_followup',transfer_id:transferId,request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId)throw new Error('سرور نتیجه آزادسازی میز را تأیید نکرد.');closeTable();CafeUI.toast(data.message);state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});}});}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}
  }


  async function settlementRequest(payload=null) {
    const options={headers:{Accept:'application/json'},cache:'no-store'};
    if(payload){options.method='POST';options.headers['Content-Type']='application/json';options.body=JSON.stringify({...payload,csrf_token:csrf});}
    const response=await fetch(window.OPERATOR_SETTLEMENTS_API,options);
    const data=await response.json().catch(()=>({}));
    return {response,data};
  }
  function settlementParty(row){
    if(row.destination==='accommodation')return [row.accommodation_guest,row.accommodation_room].filter(Boolean).join(' · ');
    if(row.destination==='subscriber')return row.subscriber_name||'';
    return '';
  }
  function renderTodaySettlements(items,canVoid){
    const root=$('todaySettlementsList');if(!root)return;
    const summary=$('todaySettlementsSummary');if(summary)summary.textContent=items.length?`${digits(items.length)} سند ثبت‌شده`:'بدون تسویه ثبت‌شده';
    if(!items.length){root.innerHTML='<div class="empty-state">در این روز کاری هنوز تسویه‌ای ثبت نشده است.</div>';return;}
    const invoiceBase=String(window.OPERATOR_INVOICES_URL||'');
    root.innerHTML=items.map((row)=>{
      const party=settlementParty(row),status=String(row.status),voided=status==='voided',reversal=status==='reversal',active=status==='completed',reference=reversal?row.reverses_invoice_number:row.reversal_invoice_number;
      const partyLine=[row.destination_label,party].filter(Boolean).join(' · ');
      const invoiceUrl=invoiceBase?`${invoiceBase}?id=${Number(row.id)}#invoiceDetail`:'';
      return `<article class="today-settlement-card ${voided||reversal?'is-voided':''}"><header><div><small>${esc(row.settled_at_label||row.settled_at||'')}</small><h3>${row.invoice_number?`<bdi dir="ltr" class="canonical-id">${esc(row.invoice_number)}</bdi>`:esc(faTextDigits(row.table_name))}</h3><p>${esc(faTextDigits(row.table_name))}${partyLine?` · ${esc(partyLine)}`:''}</p></div><span class="badge ${voided||reversal?'badge-cancelled':'badge-accounted'}">${esc(row.status_label)}</span></header><div class="today-settlement-amount"><strong>${reversal?'− ':''}${money(row.total)}</strong><span>ثبت‌کننده: ${esc(row.actor_name||'—')}</span><span>چاپ: ${esc(row.print_status_label||'چاپ نشده')}</span>${reference?`<span>${reversal?'فاکتور اصلی':'سند برگشت'}: <bdi dir="ltr" class="canonical-id">${esc(reference)}</bdi></span>`:''}</div><footer>${invoiceUrl?`<a class="btn btn-sm btn-light" href="${esc(invoiceUrl)}">مشاهده فاکتور</a>`:''}${active?`<button class="btn btn-sm btn-light settlement-reprint-action" data-settlement-id="${Number(row.id)}" type="button">چاپ مجدد</button>`:''}${canVoid&&active?`<button class="btn btn-sm btn-outline-danger settlement-void-action" data-settlement-id="${Number(row.id)}" type="button">ابطال تسویه</button>`:''}${(voided||reversal)&&row.void_reason?`<small>دلیل: ${esc(row.void_reason)}</small>`:''}</footer></article>`;
    }).join('');
  }

  async function loadTodaySettlements(){
    const root=$('todaySettlementsList');if(root)root.innerHTML='<div class="empty-state">در حال دریافت…</div>';const summary=$('todaySettlementsSummary');if(summary)summary.textContent='در حال دریافت اطلاعات…';
    try{const {response,data}=await settlementRequest();if(!response.ok||!data.success)throw new Error(data.message||'فهرست تسویه‌ها دریافت نشد.');renderTodaySettlements(data.items||[],Boolean(data.can_void));}
    catch(error){if(root)root.innerHTML=`<div class="alert alert-error">${esc(CafeUI.requestErrorMessage(error))}</div>`;const summary=$('todaySettlementsSummary');if(summary)summary.textContent='دریافت اطلاعات ناموفق بود';}
  }
  async function reprintSettlement(button){
    const operationId=requestId('settlement-reprint');
    button.disabled=true;try{const {response,data}=await settlementRequest({action:'reprint',settlement_id:Number(button.dataset.settlementId),request_id:operationId});if(!response.ok||!data.success)throw new Error(data.message||'چاپ مجدد انجام نشد.');CafeUI.toast(data.message);await loadTodaySettlements();}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}finally{button.disabled=false;}
  }
  function openSettlementVoid(button){
    if(!els.settlementVoidModal)return;$('settlementVoidId').value=button.dataset.settlementId;$('settlementVoidReason').value='';$('settlementVoidTargetWrap').classList.add('hidden');$('settlementVoidTarget').innerHTML='';modalOpen(els.settlementVoidModal);focusSettlementControl($('settlementVoidReason'));
  }
  async function confirmSettlementVoid(button){
    const reason=$('settlementVoidReason').value.trim();if(!reason){CafeUI.toast('دلیل ابطال را وارد کنید.','warning');return;}
    const targetWrap=$('settlementVoidTargetWrap'),target=targetWrap.classList.contains('hidden')?0:Number($('settlementVoidTarget').value||0);button.disabled=true;
    try{const {response,data}=await settlementRequest({action:'void',settlement_id:Number($('settlementVoidId').value),reason,target_table_id:target});
      if(data.needs_target){const options=(data.free_tables||[]).map((table)=>`<option value="${Number(table.id)}">${esc(faTextDigits(table.zone_label))} · ${esc(faTextDigits(table.name))}</option>`).join('');if(!options)throw new Error('میز آزادی برای بازگرداندن حساب وجود ندارد.');$('settlementVoidTarget').innerHTML=options;targetWrap.classList.remove('hidden');CafeUI.toast(data.message,'warning');return;}
      if(!response.ok||!data.success)throw new Error(data.message||'ابطال تسویه انجام نشد.');CafeUI.toast(data.message);modalClose(els.settlementVoidModal);await loadTodaySettlements();state.snapshot='';await load(true);
    }catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}finally{button.disabled=false;}
  }

  function nextDelay(){if(!navigator.onLine)return 30000;if(document.hidden)return 20000;if(state.failures)return Math.min(30000,4000*(2**Math.min(state.failures,3)));return 4000;}
  function schedule(delay=nextDelay()){clearTimeout(state.timer);state.timer=setTimeout(()=>load(false),delay);}
  async function load(immediate=false,options={}) {
    if(options.abortPrevious&&state.loadAbort)state.loadAbort.abort();
    if(state.loading&&!options.abortPrevious)return;
    if(!navigator.onLine){state.failures++;setConnection(false,'ارتباط دستگاه یا سرور در دسترس نیست.');schedule();return;}
    const sequence=++state.loadSequence;
    state.loading=true;
    const controller=new AbortController();state.loadAbort=controller;
    const timeout=setTimeout(()=>controller.abort(),10000);
    try{
      const response=await fetch(`${window.OPERATOR_API}?snapshot=${encodeURIComponent(options.forceFull?'':state.snapshot)}&revision=${encodeURIComponent(options.forceFull?'':state.revision)}`,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',signal:controller.signal});
      const data=await readJsonResponse(response);
      if(!response.ok||!data.success)throw new Error(data.message||`دریافت اطلاعات ناموفق بود (HTTP ${response.status}).`);
      if(sequence<state.appliedLoadSequence)return;
      const revision=Number(data.order_acceptance_revision||0);
      if(!state.acceptanceMutation&&revision>=state.acceptanceRevision){state.acceptanceRevision=revision;state.orderAcceptance=data.order_acceptance||{cafe:true,kitchen:true,bar:true};state.waiterEnabled=Boolean(data.waiter_enabled);state.stationStates=data.station_states||{};renderOperationControls(state.orderAcceptance,state.waiterEnabled,state.stationStates);}
      state.appliedLoadSequence=sequence;state.failures=0;setConnection(true);
      if(data.printing)state.printing=data.printing;if(Object.prototype.hasOwnProperty.call(data,'accommodation'))state.accommodation=data.accommodation;
      const newOrderSet=new Set((data.new_ids||[]).map(String)),newCallSet=new Set((data.call_ids||[]).map(String));state.knownOrders=newOrderSet;state.knownCalls=newCallSet;
      if(data.revision)state.revision=data.revision;if(!data.unchanged){
        state.snapshot=data.snapshot||'';state.orders=Array.isArray(data.orders)?data.orders:[];state.calls=Array.isArray(data.calls)?data.calls:[];state.tables=Array.isArray(data.tables)?data.tables:[];state.itemTotals=Array.isArray(data.item_totals)?data.item_totals:[];
        const startupTable=!state.initialized&&startupOpenTable?state.tables.find((table)=>Number(table.id)===startupOpenTable):null;
        renderAttention();renderItemTotals();
        if(!state.initialized&&startupOpenOrder&&findOrder(startupOpenOrder)){
          renderTableCards();setTab('attention');state.attentionFilter='orders';renderAttention();openOrderReview(startupOpenOrder);
        }else if(startupTable){
          setTab('tables');openTable(startupOpenTable);renderTableCards();
          if(startupResumeSettlement==='itemized'&&canHandleAccounts&&startupTable.bill_itemized_active){prepareSettlementContext(startupTable,{resetPrint:true});openItemizedSettlement(startupTable,{notice:startupQuickOrderMode==='late_accounting'&&startupQuickOrderSuccess?'قلم جاافتاده ثبت شد':''});}
        }else{
          document.body.classList.remove('operator-startup-table-detail');renderTableCards();
          if(state.selectedTableId){const selected=state.tables.find((table)=>Number(table.id)===state.selectedTableId);if(selected)renderTableDetail(selected);else closeTable();}
        }
      }
      else {if(Array.isArray(data.item_totals)){state.itemTotals=data.item_totals;renderItemTotals();}renderAttention();}
      state.initialized=true;
    }catch(error){
      if(error?.name==='AbortError'&&sequence<state.loadSequence)return;
      state.failures++;
      const message=error?.name==='AbortError'?'مهلت دریافت اطلاعات تمام شد.':String(error?.message||'دریافت اطلاعات کار روزانه ناموفق بود.');
      setConnection(false,message);
      if(!state.initialized){
        els.attentionBoard.innerHTML=`<div class="operator-load-error"><strong>اطلاعات کار روزانه دریافت نشد.</strong><span>${esc(message)}</span><button class="icon-action-button" type="button" data-operator-retry aria-label="تلاش مجدد برای دریافت اطلاعات" title="تلاش مجدد">${refreshIcon}</button></div>`;
        els.tablesBoard.innerHTML=`<div class="operator-load-error"><strong>وضعیت میزها در دسترس نیست.</strong><span>پس از رفع خطا دوباره تلاش کن.</span><button class="icon-action-button" type="button" data-operator-retry aria-label="تلاش مجدد برای دریافت اطلاعات" title="تلاش مجدد">${refreshIcon}</button></div>`;
      }
    }finally{
      clearTimeout(timeout);
      if(state.loadAbort===controller){
        state.loadAbort=null;state.loading=false;
        if(!state.acceptanceMutation)schedule(immediate?4000:nextDelay());
      }
    }
  }

  document.addEventListener('input',(event)=>{const input=event.target.closest('.bill-discount-value');if(!input)return;const panel=input.closest('[data-discount-table]');formatDiscountInput(input,panel?.dataset.discountType==='fixed'?'fixed':'percent');});
  document.addEventListener('paste',(event)=>{const input=event.target.closest('.bill-discount-value');if(!input)return;setTimeout(()=>{const panel=input.closest('[data-discount-table]');formatDiscountInput(input,panel?.dataset.discountType==='fixed'?'fixed':'percent');},0);});

  document.addEventListener('click',async(event)=>{
    const retryLoad=event.target.closest('[data-operator-retry],#operatorLiveRetry');if(retryLoad){state.snapshot='';load(true);return;}
    const acceptance=event.target.closest('[data-order-acceptance]');if(acceptance){changeOrderAcceptance(acceptance);return;}
    const tab=event.target.closest('[data-work-tab]');if(tab){const target=tab.dataset.workTab;if(target==='tables'){state.tableFilter='all';const url=new URL(location.href);url.searchParams.delete('table_filter');history.replaceState(history.state,'',url);}if(target==='attention'){state.attentionFilter='all';const url=new URL(location.href);url.searchParams.delete('attention_filter');history.replaceState(null,'',url);renderAttention();}setTab(target);if(target==='tables')renderTableCards();return;}
    const tableFilter=event.target.closest('[data-table-filter]');if(tableFilter){state.tableFilter=tableFilter.dataset.tableFilter||'all';const url=new URL(location.href);url.searchParams.set('table_filter',state.tableFilter);url.searchParams.set('work','tables');url.hash='tables';history.replaceState(history.state,'',url);renderTableCards();return;}
    const tableSort=event.target.closest('[data-table-sort]');if(tableSort){const next=String(tableSort.dataset.tableSort||'layout');if(!['layout','oldest','newest'].includes(next))return;state.tableSort=next;sessionState.set('sokna.operator.tableSort',next);renderTableCards();return;}
    const review=event.target.closest('[data-review-order]');if(review){openOrderReview(Number(review.dataset.reviewOrder));return;}
    const table=event.target.closest('[data-select-table]');if(table){if(table.hasAttribute('data-open-tables'))setTab('tables');const tableId=Number(table.dataset.selectTable),tableState=state.tables.find(row=>Number(row.id)===tableId);if(tableState&&!tableState.active&&canHandleOrders){window.location.assign(quickOrderUrl(tableId,'global'));return;}openTable(tableId);return;}
    const order=event.target.closest('.order-status-action');if(order){updateOrderStatus(order.dataset.id,order.dataset.status,order);return;}
    const waiter=event.target.closest('.waiter-action');if(waiter){waiterAction(waiter);return;}
    const session=event.target.closest('.session-action');if(session){sessionAction(session);return;}
    const billMode=event.target.closest('[data-bill-edit-mode]');if(billMode){setBillEditMode(billMode.dataset.billEditMode);return;}
    const billStep=event.target.closest('[data-bill-step]');if(billStep){const input=$(billStep.closest('[data-stepper-for]')?.dataset.stepperFor||'');if(input){const min=Number(input.min||0),max=Number(input.max||99);input.value=faTextDigits(String(Math.min(max,Math.max(min,Number(normalizeNumericInput(input.value)||min)+Number(billStep.dataset.billStep||0)))));if(els.billItemModal)els.billItemModal.dataset.operationId='';updateBillEditPreview();}return;}
    const sourcePicker=event.target.closest('.bill-item-source-picker');if(sourcePicker){openBillSource(sourcePicker.dataset.sourceKey);return;}
    const sourceChoice=event.target.closest('[data-bill-source-index]');if(sourceChoice){selectBillSource(sourceChoice);return;}
    const edit=event.target.closest('.bill-item-edit');if(edit){openBillEdit(edit);return;}
    const discountChoice=event.target.closest('[data-discount-type-choice]');if(discountChoice){const panel=discountChoice.closest('[data-discount-table]');if(panel&&!discountChoice.disabled){panel.dataset.discountType=discountChoice.dataset.discountTypeChoice;panel.querySelectorAll('[data-discount-type-choice]').forEach((item)=>{const active=item===discountChoice;item.classList.toggle('is-active',active);item.setAttribute('aria-checked',active?'true':'false');});const suffix=panel.querySelector('[data-discount-suffix]');if(suffix)suffix.textContent=panel.dataset.discountType==='fixed'?'تومان':'٪';const input=panel.querySelector('.bill-discount-value');if(input){formatDiscountInput(input,panel.dataset.discountType);input.focus();}}return;}
    const discount=event.target.closest('.bill-discount-save');if(discount){changeDiscount(discount,false);return;}
    const clear=event.target.closest('.bill-discount-clear');if(clear){changeDiscount(clear,true);return;}
    const reprint=event.target.closest('.prep-reprint-action');if(reprint){reprintPrep(reprint);return;}
    const itemizedStep=event.target.closest('[data-itemized-step]');if(itemizedStep){changeItemizedQuantity(itemizedStep);return;}
    const settlement=event.target.closest('.open-settlement-action');if(settlement){openSettlement(settlement);return;}
    const choice=event.target.closest('[data-settlement]');if(choice){settlementChoice(choice.dataset.settlement);return;}
    const moveTarget=event.target.closest('[data-move-target]');if(moveTarget){state.selectedMoveTarget=Number(moveTarget.dataset.moveTarget);$('moveTableChoices').querySelectorAll('[data-move-target]').forEach((b)=>b.classList.toggle('is-selected',b===moveTarget));$('confirmMoveTable').disabled=false;return;}
    const subscriber=event.target.closest('[data-subscriber-id]');if(subscriber){const item=state.subscriberResults.find((entry)=>Number(entry.id)===Number(subscriber.dataset.subscriberId));if(item)selectSubscriber(item);return;}
    const confirmSub=event.target.closest('#confirmSubscriberCharge');if(confirmSub){confirmSubscriber(confirmSub);return;}
    const backSub=event.target.closest('#backSubscriberSearch');if(backSub){state.selectedSubscriber=null;$('subscriberConfirm').classList.add('hidden');$('subscriberSearchResult').classList.remove('hidden');return;}
    const reservation=event.target.closest('[data-reservation-index]');if(reservation){selectReservation(Number(reservation.dataset.reservationIndex));return;}
    const settlementReprint=event.target.closest('.settlement-reprint-action');if(settlementReprint){reprintSettlement(settlementReprint);return;}
    const settlementVoid=event.target.closest('.settlement-void-action');if(settlementVoid){openSettlementVoid(settlementVoid);return;}
    const finalizeAccommodation=event.target.closest('.accommodation-finalize-action');if(finalizeAccommodation){finalizeAccommodationLocal(finalizeAccommodation);return;}
    const retry=event.target.closest('.accommodation-retry-action');if(retry){retryAccommodation(retry);return;}
    const detach=event.target.closest('.accommodation-detach-action');if(detach){detachAccommodation(detach);return;}
  });

  $('tableSortSelect')?.addEventListener('change',(event)=>{const next=String(event.currentTarget.value||'layout');if(!['layout','oldest','newest'].includes(next))return;state.tableSort=next;sessionState.set('sokna.operator.tableSort',next);renderTableCards();});
  $('closeTableDetail')?.addEventListener('click',closeTable);$('tableDetailBackdrop')?.addEventListener('click',closeTable);els.tableDetailTitle?.addEventListener('click',openMoveTable);
  $('closeMoveTable')?.addEventListener('click',()=>modalClose(els.moveTableModal));$('cancelMoveTable')?.addEventListener('click',()=>modalClose(els.moveTableModal));
  $('confirmMoveTable')?.addEventListener('click',async(event)=>{if(!state.selectedMoveTarget)return;const button=event.currentTarget,source=state.selectedTableId,target=state.selectedMoveTarget,operationId=requestId('table-move');if(!await CafeUI.confirm('کل حساب و سفارش‌های باز به میز انتخاب‌شده منتقل شود؟','تأیید انتقال',{okLabel:'انتقال حساب',trigger:button}))return;try{await CafeUI.runAction({button,loadingText:'در حال انتقال…',successText:'منتقل شد',request:()=>post(window.OPERATOR_SESSION_API,{action:'move',table_id:source,target_table_id:target,request_id:operationId}),onSuccess:async(data)=>{if(!data.persisted||data.request_id!==operationId||Number(data.source_table_id)!==source||Number(data.target_table_id)!==target)throw new Error('سرور نتیجه نهایی انتقال میز را تأیید نکرد.');state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});const sourceState=state.tables.find(t=>Number(t.id)===source),targetState=state.tables.find(t=>Number(t.id)===target);if(sourceState?.active||!targetState?.active)throw new Error('بازخوانی وضعیت میزها با نتیجه انتقال یکسان نبود.');modalClose(els.moveTableModal);state.selectedTableId=target;openTable(target);CafeUI.toast(data.message);}});}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');state.snapshot='';await load(true,{abortPrevious:true,forceFull:true});openMoveTable();}});
  $('closeBillItem')?.addEventListener('click',()=>{void closeBillItemSafely();});
  const handleBillDeleteReasonChange=(event)=>{const input=event.currentTarget,other=input.checked&&input.value==='سایر';$('billItemDeleteOtherReasonWrap')?.classList.toggle('hidden',!other);if(other)$('billItemDeleteOtherReason')?.focus();updateBillEditPreview();};
  $('closeBillItemIcon')?.addEventListener('click',()=>{void closeBillItemSafely();});$('closeBillSource')?.addEventListener('click',()=>modalClose(els.billSourceModal));$('cancelBillSource')?.addEventListener('click',()=>modalClose(els.billSourceModal));$('saveBillItem')?.addEventListener('click',(e)=>saveBillItem(e.currentTarget));$('billItemFinalQuantity')?.addEventListener('input',updateBillEditPreview);document.querySelectorAll('input[name="bill_item_delete_reason"]').forEach(input=>input.addEventListener('change',handleBillDeleteReasonChange));$('billItemDeleteOtherReason')?.addEventListener('input',updateBillEditPreview);if(window.CafeUI?.bindSwipeDismiss&&els.billItemModal){const sheet=els.billItemModal.querySelector('.rc4-bill-edit'),handle=els.billItemModal.querySelector('.bill-edit-swipe-handle');if(sheet&&handle)CafeUI.bindSwipeDismiss({sheet,handle,onDismiss:closeBillItemSafely});}
  $('closeCheckoutModal')?.addEventListener('click',()=>modalClose(els.checkoutModal));$('closeDirectSettlement')?.addEventListener('click',()=>modalClose(els.directSettlementModal));$('confirmDirectSettlement')?.addEventListener('click',(e)=>confirmDirect(e.currentTarget));$('backDirectSettlement')?.addEventListener('click',()=>returnToSettlementChoice(els.directSettlementModal));$('closeItemizedSettlement')?.addEventListener('click',()=>modalClose(els.itemizedSettlementModal));$('backItemizedSettlement')?.addEventListener('click',backFromItemizedSettlement);$('selectAllItemizedRemaining')?.addEventListener('click',selectAllItemizedRemaining);$('submitItemizedSettlement')?.addEventListener('click',(e)=>submitItemizedSettlement(e.currentTarget));$('addMissedItemizedItem')?.addEventListener('click',openMissedItemizedItem);$('closeOrderReview')?.addEventListener('click',()=>modalClose(els.orderReviewModal));
  $('closeSubscriberModal')?.addEventListener('click',()=>modalClose(els.subscriberModal));$('backSubscriberSettlement')?.addEventListener('click',()=>returnToSettlementChoice(els.subscriberModal));let subscriberTimer;$('subscriberSearch')?.addEventListener('input',(e)=>{clearTimeout(subscriberTimer);subscriberTimer=setTimeout(()=>searchSubscribers(e.target.value),300);});$('subscriberSearchButton')?.addEventListener('click',()=>runSettlementSearchAction('subscriberSearch',searchSubscribers));$('subscriberSearch')?.addEventListener('keydown',(e)=>{if(e.key==='Enter'&&!e.isComposing){e.preventDefault();runSettlementSearchAction('subscriberSearch',searchSubscribers);}});
  $('closeAccommodationModal')?.addEventListener('click',()=>modalClose(els.accommodationModal));$('backAccommodationSettlement')?.addEventListener('click',()=>returnToSettlementChoice(els.accommodationModal));$('accommodationSearchButton')?.addEventListener('click',()=>runSettlementSearchAction('accommodationSearchQuery',searchAccommodation));let accommodationTimer;$('accommodationSearchQuery')?.addEventListener('input',(e)=>{clearTimeout(accommodationTimer);accommodationTimer=setTimeout(()=>searchAccommodation(e.target.value),450);});$('accommodationSearchQuery')?.addEventListener('keydown',(e)=>{if(e.key==='Enter'&&!e.isComposing){e.preventDefault();runSettlementSearchAction('accommodationSearchQuery',searchAccommodation);}});$('accommodationBackButton')?.addEventListener('click',()=>{$('accommodationConfirm').classList.add('hidden');$('accommodationSearchResult').classList.remove('hidden');state.selectedReservation=null;});$('accommodationPostButton')?.addEventListener('click',(e)=>postAccommodation(e.currentTarget));
  $('openTodaySettlements')?.addEventListener('click',()=>{modalOpen(els.todaySettlementsModal);loadTodaySettlements();});$('closeTodaySettlements')?.addEventListener('click',()=>modalClose(els.todaySettlementsModal));$('refreshTodaySettlements')?.addEventListener('click',loadTodaySettlements);$('closeSettlementVoid')?.addEventListener('click',()=>modalClose(els.settlementVoidModal));$('cancelSettlementVoid')?.addEventListener('click',()=>modalClose(els.settlementVoidModal));$('confirmSettlementVoid')?.addEventListener('click',(e)=>confirmSettlementVoid(e.currentTarget));

  els.waiterToggle?.addEventListener('click',async()=>{try{const data=await post(window.OPERATOR_WAITER_API,{action:'toggle_feature'});CafeUI.toast(data.message||'وضعیت فراخوان تغییر کرد.');state.snapshot='';await load(true);}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}});
  navigator.serviceWorker?.addEventListener?.('message',(event)=>{
    const data=event.data||{};
    if(data.type!=='SOKNA_PUSH_ACTION_RESULT')return;
    if(data.success){CafeUI.toast(data.message||'اقدام اعلان ثبت شد.','success');state.snapshot='';void load(true,{abortPrevious:true,forceFull:true});}
  });

  const normalizedBoolean = (value) => {
    if (value === true || value === 1 || value === '1') return true;
    if (value === false || value === 0 || value === '0') return false;
    if (typeof value === 'string' && ['true','yes','on'].includes(value.toLowerCase())) return true;
    if (typeof value === 'string' && ['false','no','off'].includes(value.toLowerCase())) return false;
    return null;
  };

  async function changeOrderAcceptance(button){
    if(!button||button.dataset.busy==='1'||state.acceptanceMutation)return;
    const scope=button.dataset.orderAcceptance,enabled=button.dataset.enabled==='1',desired=!enabled,labels={cafe:'کل کافه',kitchen:'آشپزخانه',bar:'بار'};
    if(enabled&&!await CafeUI.confirm(`ثبت سفارش آنلاین مهمان برای ${labels[scope]} متوقف شود؟ منو و قیمت‌ها همچنان دیده می‌شوند و ثبت سریع کارکنان فعال می‌ماند.`,'توقف سفارش آنلاین',{okLabel:'توقف آنلاین',danger:true,trigger:button}))return;
    const previous=orderAcceptanceFallback(),stations=stationStatesFallback(),status=document.querySelector(`[data-acceptance-status="${scope}"]`);
    if(status){status.textContent='در حال همگام‌سازی…';status.className='operation-state-badge is-loading';}
    state.acceptanceMutation=true;state.loadAbort?.abort();clearTimeout(state.timer);const operationId=requestId(`acceptance-${scope}`);
    try{
      await post(window.OPERATOR_CONTROLS_API,{action:'set_order_acceptance',scope,enabled:desired,request_id:operationId});
      let verified=null,opposite=false,hadReadableResponse=false;
      for(let attempt=0;attempt<5;attempt++){
        if(attempt)await new Promise(resolve=>setTimeout(resolve,[250,450,700,1000][Math.min(attempt-1,3)]));
        try{
          const separator=window.OPERATOR_CONTROLS_API.includes('?')?'&':'?';
          const response=await fetch(`${window.OPERATOR_CONTROLS_API}${separator}_=${Date.now()}`,{headers:{Accept:'application/json','Cache-Control':'no-cache'},credentials:'same-origin',cache:'no-store'});
          const current=await readJsonResponse(response);
          if(response.ok&&current.success){
            hadReadableResponse=true;
            const actual=normalizedBoolean(current.order_acceptance?.[scope]);
            if(actual===desired){verified=current;break;}
            if(actual!==null)opposite=true;
          }
        }catch(_){ /* اختلال موقت شبکه خطای قطعی نیست. */ }
      }
      if(verified){
        state.acceptanceRevision=Math.max(state.acceptanceRevision,Number(verified.order_acceptance_revision||0));
        state.orderAcceptance={...previous,...verified.order_acceptance};state.stationStates={...stations,...(verified.station_states||{})};renderOperationControls(state.orderAcceptance,state.waiterEnabled,state.stationStates);
        CafeUI.toast(`سفارش‌گیری آنلاین ${labels[scope]} ${desired?'فعال':'متوقف'} شد.`,'success');
      }else if(hadReadableResponse&&opposite){
        throw new Error('وضعیت نهایی با انتخاب شما یکسان نیست. دوباره تلاش کنید.');
      }else{
        state.orderAcceptance={...previous,[scope]:desired};renderOperationControls(state.orderAcceptance,state.waiterEnabled,state.stationStates);
        const pendingStatus=document.querySelector(`[data-acceptance-status="${scope}"]`);
        if(pendingStatus){pendingStatus.textContent='در حال همگام‌سازی…';pendingStatus.className='operation-state-badge is-loading';}
        setTimeout(()=>{state.snapshot='';load(true,{abortPrevious:true,forceFull:true});},1800);
      }
    }catch(error){
      state.orderAcceptance=previous;state.stationStates=stations;renderOperationControls(state.orderAcceptance,state.waiterEnabled,state.stationStates);
      const errorStatus=document.querySelector(`[data-acceptance-status="${scope}"]`);
      if(errorStatus){errorStatus.textContent=CafeUI.requestErrorMessage(error);errorStatus.className='operation-state-badge is-error';}
    }finally{state.acceptanceMutation=false;schedule(3500);}
  }
  function orderAcceptanceFallback(){const result={};document.querySelectorAll('[data-order-acceptance]').forEach((item)=>result[item.dataset.orderAcceptance]=item.dataset.enabled==='1');return result;}
  function stationStatesFallback(){const result={};document.querySelectorAll('[data-station-card]').forEach((card)=>{const selected=card.querySelector('[data-station-choice].is-selected');result[card.dataset.stationCard]=selected?.dataset.busy==='1';});return result;}
  els.stationControls?.addEventListener('click',async(e)=>{const button=e.target.closest('[data-station-choice]');if(!button||button.classList.contains('is-selected'))return;const station=button.dataset.stationChoice,busy=button.dataset.busy==='1';const card=button.closest('[data-station-card]'),status=card?.querySelector('[data-station-save-state]'),buttons=[...card.querySelectorAll('[data-station-choice]')];buttons.forEach(item=>item.disabled=true);if(status){status.textContent='در حال ذخیره…';status.className='is-saving';}try{const data=await post(window.OPERATOR_CONTROLS_API,{action:'set_station',station,busy});buttons.forEach(item=>item.classList.toggle('is-selected',(item.dataset.busy==='1')===Boolean(data.busy)));if(status){status.textContent='ذخیره شد';status.className='is-saved';setTimeout(()=>{status.textContent='آماده';status.className='';},1600);}state.snapshot='';}catch(error){if(status){status.textContent='ذخیره نشد';status.className='is-error';}CafeUI.toast(CafeUI.requestErrorMessage(error),'error');}finally{buttons.forEach(item=>item.disabled=false);}});

  window.addEventListener('popstate',(event)=>{
    const topDialog=CafeUI.dialog?.top?.();
    if(topDialog&&state.selectedTableId){
      CafeUI.dialog.closeTop(false,'history');
      writeTableHistory(state.selectedTableId,'push');
      return;
    }
    const tableId=Number(event.state?.operatorTableId||0);
    if(tableId>0){openTable(tableId,{historyMode:'none'});return;}
    if(state.selectedTableId>0)closeTableView(true);
  });
  document.addEventListener('keydown',(e)=>{if(e.key==='Escape'&&!CafeUI.dialog?.top?.()&&els.tableDetailShell?.classList.contains('is-open'))closeTable();});

  els.attentionBoard?.addEventListener('toggle',(event)=>{const details=event.target.closest?.('[data-followups-manual]');if(!details)return;state.followupsOpen=details.open;sessionState.set('sokna.operator.followupsOpen',details.open?'1':'0');},true);
  document.addEventListener('sokna:staff-order-created',(event)=>{const detail=event.detail||{},tableId=Number(detail.table_id);state.snapshot='';state.revision='';if(detail.origin==='table-panel'&&state.selectedTableId===tableId){load(true,{abortPrevious:true,forceFull:true});return;}const optimistic=state.tables.find(table=>Number(table.id)===tableId);if(optimistic){optimistic.active=true;optimistic.status='active';optimistic.duration=optimistic.duration||'هم‌اکنون';}state.selectedTableId=0;setTab('tables');renderTableCards();load(true,{abortPrevious:true,forceFull:true});});

  window.addEventListener('online',()=>load(true));window.addEventListener('resize',()=>{if(!document.hidden)syncTablesWorkspaceHeight();},{passive:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden)load(true);});

  const availableTabs=[...document.querySelectorAll('[data-work-tab]')].map((button)=>button.dataset.workTab);
  const requestedTab=startupWork||location.hash.slice(1);
  const initial=startupOpenTable&&availableTabs.includes('tables')?'tables':(availableTabs.includes(requestedTab)?requestedTab:(availableTabs[0]||'items'));
  if(startupOpenTable){const listUrl=tableHistoryUrl(0);history.replaceState({...history.state,operatorTableId:0},'',listUrl);}
  setTab(initial);
  if(startupQuickOrderSuccess){
    if(!(startupQuickOrderMode==='late_accounting'&&startupResumeSettlement==='itemized'))CafeUI.toast(`سفارش ${faTextDigits(startupQuickOrderSuccess)} ثبت شد.`,'success');
    const cleanUrl=new URL(location.href);
    ['quick_order_success','quick_order_table','quick_order_order','quick_order_number','quick_order_mode','resume_settlement'].forEach((key)=>cleanUrl.searchParams.delete(key));
    history.replaceState(history.state,'',cleanUrl);
    setTimeout(()=>{
      state.quickOrderSuccessVisible=false;
      document.querySelectorAll('.table-card-v1280.is-quick-order-success,.bill-batch-v1280.is-quick-order-success').forEach((node)=>node.classList.remove('is-quick-order-success'));
      document.querySelector('.table-account-order-success')?.remove();
    },5200);
  }

  load(true);
})();

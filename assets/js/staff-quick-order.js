(() => {
  'use strict';

  const root = document.getElementById('quickOrderPage');
  if (!root || !window.STAFF_QUICK_ORDER_API) return;

  const $ = (id) => document.getElementById(id);
  const els = {
    tableStage: $('quickOrderTableStage'), workspace: $('quickOrderWorkspace'), tableInput: $('quickOrderTable'),
    tableCount: $('quickOrderTableCount'), tableGroups: $('quickOrderTableGroups'), tableName: $('quickOrderSelectedTableName'),
    tableMeta: $('quickOrderSelectedTableMeta'), changeTable: $('quickOrderChangeTable'), headerContext: $('quickOrderHeaderContext'),
    back: $('quickOrderBack'), menus: $('quickOrderMenus'), categories: $('quickOrderCategories'), categorySearch: $('quickOrderCategorySearch'), categoryTitle: $('quickOrderCategoryTitle'),
    mobilePending: $('quickOrderMobilePendingBanner'), mobilePendingCount: $('quickOrderMobilePendingCount'), uncertainNotice: $('quickOrderUncertainNotice'),
    draftStatus: $('quickOrderDraftStatus'), draftStatusText: $('quickOrderDraftStatusText'), draftCancel: $('quickOrderDraftCancel'),
    categoryBack: $('quickOrderCategoryBack'), searchWrap: $('quickOrderSearchWrap'), searchToggle: $('quickOrderSearchToggle'),
    search: $('quickOrderSearch'), searchClear: $('quickOrderSearchClear'), items: $('quickOrderItems'), cart: $('quickOrderCart'),
    cartClose: $('quickOrderCartClose'), cartBackdrop: $('quickOrderCartBackdrop'), cartTitle: $('quickOrderCartTitle'),
    cartLines: $('quickOrderCartLines'), clear: $('quickOrderClear'), takeawayTool: $('quickOrderTakeawayTool'), takeawayMode: $('quickOrderTakeawayMode'), takeawayAll: $('quickOrderTakeawayAll'), takeawayDone: $('quickOrderTakeawayDone'),
    pending: $('quickOrderPending'), current: $('quickOrderCurrentAccount'), currentTotal: $('quickOrderCurrentTotal'),
    currentCount: $('quickOrderCurrentCount'), currentLines: $('quickOrderCurrentLines'), note: $('quickOrderNote'),
    noteToggle: $('quickOrderNoteToggle'), noteWrap: $('quickOrderNoteWrap'), total: $('quickOrderTotal'),
    previousRow: $('quickOrderPreviousRow'), previousTotal: $('quickOrderPreviousTotal'), projected: $('quickOrderProjectedTotal'),
    newTaxHint: $('quickOrderNewTaxHint'), projectedTaxHint: $('quickOrderProjectedTaxHint'),
    projectedRow: $('quickOrderProjectedRow'), submit: $('quickOrderSubmit'), mobileBar: $('quickOrderMobileCartBar'),
    mobileCount: $('quickOrderMobileCartCount'), mobileTotal: $('quickOrderMobileCartTotal'),
  };

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const mobileMedia = window.matchMedia('(max-width: 1023px)');
  const sprite = String(window.SOKNA_ICON_SPRITE || '');
  const initialTableId = Math.max(0, Number(root.dataset.initialTable || 0));
  const returnUrl = String(root.dataset.returnUrl || '/');
  const successUrl = String(root.dataset.successUrl || returnUrl || '/');
  const origin = ['global','table-panel'].includes(String(root.dataset.origin || '')) ? String(root.dataset.origin) : (initialTableId ? 'table-panel' : 'global');
  const mode = String(root.dataset.mode || 'normal') === 'late_accounting' ? 'late_accounting' : 'normal';
  const lateAccounting = mode === 'late_accounting';
  const userKey = String(window.QUICK_ORDER_USER_KEY || '0');
  const validIcons = new Set(['bean','brew','tea','herbal','cup-hot','iced-coffee','mocktail','juice','sharbat','shake','smoothie','healthy-drink','protein','energy-drink','water','sugar-free','cold-drink','snowflake','brunch','breakfast','bakery','pastry','waffle','donut','iranian-food','grill','fried','seafood','sushi','noodles','soup','burger','hot-dog','pizza','pasta','sandwich','salad','appetizer','fries','vegan','diet','kids-menu','sharing','combo','sauce','food','cake','dessert','ice-cream','chocolate','service','seasonal','retail','gift','sparkles','list']);

  const state = {
    loaded: false, loading: false, tables: [], menus: [], menuKey: '', categories: [], items: [], category: 0,
    mobileView: 'categories', cart: new Map(), requestToken: '', pendingReview: new Set(), submitting: false, uncertain: false,
    searchOpen: false, changeTableMode: false, draftTableId: 0, clearUndo: null, clearUndoTimer: 0, cartGesture: null, cartGestureTimer: 0, fulfillmentEditing: false,
    serverDraftId: 0, serverDraftVersion: 0, serverDraftFingerprint: '', draftSaving: false, draftSaveTimer: 0, draftSavePromise: null, draftLoadSeq: 0, draftConflict: false, draftError: '',
  };

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const digits = (value) => Number(value || 0).toLocaleString('fa-IR');
  const textDigits = (value) => String(value ?? '').replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
  const money = (value) => Number(value || 0).toLocaleString('fa-IR');
  const latinDigits = (value) => String(value ?? '').replace(/[۰-۹]/g, (char) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(char))).replace(/[٠-٩]/g, (char) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(char)));
  const normalize = (value) => latinDigits(value).trim().toLocaleLowerCase('fa-IR').replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ');
  const icon = (name) => `<svg class="ui-icon" aria-hidden="true"><use href="${esc(sprite)}#icon-${esc(name)}"></use></svg>`;
  const selectedTable = () => state.tables.find((row) => Number(row.id) === Number(els.tableInput.value));
  const selectedLines = () => [...state.cart.values()].sort((a, b) => String(a.name).localeCompare(String(b.name), 'fa'));
  const cartQuantity = () => selectedLines().reduce((sum, row) => sum + Number(row.quantity || 0), 0);
  const cartTotal = () => selectedLines().reduce((sum, row) => sum + Number(row.price || 0) * Number(row.quantity || 0), 0);
  const fulfillment=window.SoknaFulfillment;
  const clampTakeaway=(line)=>fulfillment.clamp(line);
  const cartTakeawayQuantity=()=>fulfillment.takeawayQuantity(selectedLines());
  const cartEligibleQuantity=()=>fulfillment.eligibleQuantity(selectedLines());
  const cartIsAllTakeaway=()=>{const q=cartEligibleQuantity(),total=cartQuantity();return q>0&&q===total&&cartTakeawayQuantity()===q;};
  const requestLines = () => { const rows=[]; for(const line of selectedLines()){clampTakeaway(line);const q=Number(line.quantity||0);const base={id:Number(line.id),note:String(line.note||'').trim(),expected_price:Number(line.price)};if(lateAccounting){if(q>0)rows.push({...base,quantity:q,fulfillment_mode:'dine_in'});continue;}const t=Number(line.takeaway_quantity||0),d=q-t;if(d>0)rows.push({...base,quantity:d,fulfillment_mode:'dine_in'});if(t>0)rows.push({...base,quantity:t,fulfillment_mode:'takeaway'});} return rows; };
  const newToken = () => crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
  const CLEAR_UNDO_MS = 8000;
  const CART_DRAG_SNAP_MS = 180;
  const uncertainKey = (tableId = Number(els.tableInput.value || 0)) => lateAccounting ? `sokna.quick-order.uncertain.v2.${userKey}.late_accounting.${Number(tableId || 0)}` : `sokna.quick-order.uncertain.v2.${userKey}.${Number(tableId || 0)}`;
  const safeLocalGet = (key) => { try { return localStorage.getItem(key); } catch (_) { return null; } };
  const safeLocalSet = (key, value) => { try { localStorage.setItem(key, value); return true; } catch (_) { return false; } };
  const safeLocalRemove = (key) => { try { localStorage.removeItem(key); } catch (_) {} };
  function clearUncertain(tableId = Number(els.tableInput.value || 0)) {
    if (tableId) safeLocalRemove(uncertainKey(tableId));
    state.uncertain = false;
  }
  function readUncertain(tableId) {
    const raw = safeLocalGet(uncertainKey(tableId));
    if (!raw) return null;
    try {
      const saved = JSON.parse(raw);
      if (![2,3].includes(Number(saved?.version)) || Number(saved?.tableId) !== Number(tableId) || !saved.requestToken || !Array.isArray(saved.items) || Number(saved.expectedSessionId || 0) < 0) throw new Error('invalid uncertain request');
      if (Date.now() - Number(saved.updatedAt || 0) > 12 * 3600 * 1000) throw new Error('expired uncertain request');
      return saved;
    } catch (_) {
      safeLocalRemove(uncertainKey(tableId));
      return null;
    }
  }
  function persistUncertain(payload) {
    const record = {...payload, version: 3, updatedAt: Date.now()};
    safeLocalSet(uncertainKey(payload.tableId), JSON.stringify(record));
  }
  const sharedDraftEnabled = !lateAccounting && Boolean(window.STAFF_TABLE_DRAFT_API);
  const resetToken = () => { if (state.uncertain) return; invalidateClearUndo(); state.requestToken = ''; saveDraft(); };
  const draftKey = (tableId = Number(els.tableInput.value || 0)) => `sokna.quick-order.v2.${userKey}.late_accounting.${Number(tableId || 0)}`;

  function toast(message, error = false) {
    if (window.CafeUI?.toast) return window.CafeUI.toast(message, error ? 'error' : 'success');
    const node = $('panelToast');
    if (!node) return;
    node.textContent = message;
    node.className = `panel-toast ${error ? 'error' : 'success'}`;
    clearTimeout(node._timer);
    node._timer = setTimeout(() => node.classList.add('hidden'), 3800);
  }

  function requestErrorMessage(error, fallback = 'این تغییر انجام نشد.') {
    return window.CafeUI?.requestErrorMessage?.(error, fallback) || fallback;
  }

  function confirmAction(message, title, options = {}) {
    if (window.CafeUI?.confirm) return window.CafeUI.confirm(message, title, options);
    toast('پنجره تأیید سامانه آماده نیست؛ صفحه را تازه‌سازی کنید.', true);
    return Promise.resolve(false);
  }

  function renderDraftStatus(message = '') {
    if (!els.draftStatus || lateAccounting) return;
    const visible = Boolean(state.serverDraftId || state.draftSaving || state.draftConflict || state.draftError || message);
    els.draftStatus.classList.toggle('hidden', !visible);
    els.draftStatus.classList.toggle('is-saving', state.draftSaving);
    els.draftStatus.classList.toggle('is-conflict', state.draftConflict || Boolean(state.draftError));
    if (els.draftStatusText) {
      els.draftStatusText.textContent = message || (state.draftSaving
        ? 'در حال ذخیره روی سرور…'
        : state.draftConflict
          ? 'نسخه تازه‌تری ثبت شده است؛ پیش‌نویس دوباره بارگذاری شد.'
          : state.draftError
            ? state.draftError
            : state.serverDraftId
              ? `نسخه ${digits(state.serverDraftVersion)} · روی سرور ذخیره شده و برای همکاران قابل ادامه است.`
              : '');
    }
    if (els.draftCancel) els.draftCancel.disabled = state.draftSaving || state.uncertain || state.serverDraftId < 1;
  }

  function clearDraftState() {
    if (state.draftSaveTimer) window.clearTimeout(state.draftSaveTimer);
    state.draftSaveTimer = 0;
    state.cart.clear();
    state.requestToken = '';
    state.uncertain = false;
    state.fulfillmentEditing = false;
    state.serverDraftId = 0;
    state.serverDraftVersion = 0;
    state.serverDraftFingerprint = '';
    state.draftConflict = false;
    state.draftError = '';
    if (els.note) els.note.value = '';
    els.noteWrap?.classList.add('hidden');
    els.noteToggle?.setAttribute('aria-expanded', 'false');
    renderDraftStatus();
  }

  function saveLateAccountingDraft() {
    const tableId = Number(els.tableInput.value || 0);
    if (!tableId) return;
    state.draftTableId = tableId;
    const lines = selectedLines().map(({id, name, price, category_id, category_name, takeaway_allowed, quantity, note, takeaway_quantity}) => ({id:Number(id),name:String(name||''),price:Number(price||0),category_id:Number(category_id||0),category_name:String(category_name||''),takeaway_allowed:Number(takeaway_allowed??1),quantity:Number(quantity),note:String(note||''),takeaway_quantity:Number(takeaway_quantity||0)}));
    const payload = {version:7, tableId, menuKey:String(state.menuKey||''), lines, note:String(els.note?.value||''), category:Number(state.category||0), requestToken:state.requestToken, updatedAt:Date.now()};
    if (!lines.length && !payload.note.trim() && !payload.requestToken) {
      sessionStorage.removeItem(draftKey(tableId));
      return;
    }
    sessionStorage.setItem(draftKey(tableId), JSON.stringify(payload));
  }

  function restoreLateAccountingDraft(tableId) {
    clearDraftState();
    state.draftTableId = Number(tableId || 0);
    const raw = sessionStorage.getItem(draftKey(tableId));
    const pendingUncertain = readUncertain(tableId);
    if (!raw && !pendingUncertain) return;
    try {
      const draft = raw ? JSON.parse(raw) : {version:7,tableId,lines:[],note:'',category:0,requestToken:''};
      if (Number(draft?.version) !== 7 || Number(draft?.tableId) !== Number(tableId)) throw new Error('invalid draft binding');
      for (const row of Array.isArray(draft.lines) ? draft.lines : []) {
        const item = state.items.find((candidate) => Number(candidate.id) === Number(row.id)) || (Number(row.id)>0&&String(row.name||'')!==''&&Number(row.price)>=0 ? {id:Number(row.id),name:String(row.name),price:Number(row.price),category_id:Number(row.category_id||0),category_name:String(row.category_name||''),takeaway_allowed:Number(row.takeaway_allowed??1),order_available:1} : null);
        const quantity = Math.min(50, Math.max(1, Number(row.quantity || 0)));
        if (!item || Number(item.order_available ?? 1) !== 1 || !quantity) continue;
        state.cart.set(Number(item.id), {...item, quantity, note: String(row.note || '').slice(0, 500), takeaway_quantity: Math.max(0, Math.min(quantity, Number(row.takeaway_quantity || 0))), noteOpen: false});
      }
      els.note.value = String(draft.note || '').slice(0, 500);
      state.requestToken = String(draft.requestToken || '');
      if (state.categories.some((row) => Number(row.id) === Number(draft.category))) state.category = Number(draft.category);
      const uncertain = pendingUncertain || readUncertain(tableId);
      if (uncertain) {
        state.cart.clear();
        applyRequestRows(uncertain.items);
        els.note.value = String(uncertain.note || '').slice(0,500);
        state.requestToken = String(uncertain.requestToken || '');
        state.uncertain = true;
      }
    } catch (_) {
      sessionStorage.removeItem(draftKey(tableId));
      clearDraftState();
    }
  }

  function applyRequestRows(rows) {
    state.cart.clear();
    for (const row of Array.isArray(rows) ? rows : []) {
      const id = Number(row.id || row.item_id || 0);
      const item = state.items.find((candidate) => Number(candidate.id) === id) || (id > 0 && String(row.name || row.item_name_snapshot || '').trim() !== '' ? {
        id,
        name:String(row.name || row.item_name_snapshot || ''),
        price:Number(row.price ?? row.unit_price ?? row.expected_price ?? row.unit_price_snapshot ?? 0),
        category_id:Number(row.category_id || 0),
        category_name:String(row.category_name || ''),
        takeaway_allowed:Number(row.takeaway_allowed ?? 1),
        order_available:1,
      } : null);
      const quantity = Math.min(50, Math.max(1, Number(row.quantity || 0)));
      if (!item || !quantity) continue;
      const existing = state.cart.get(id);
      const mode = String(row.fulfillment_mode || '') === 'takeaway' ? 'takeaway' : 'dine_in';
      const incomingNote = String(row.note ?? row.item_note ?? '').slice(0,500).trim();
      if (existing) {
        existing.quantity = Math.min(50, Number(existing.quantity || 0) + quantity);
        if (mode === 'takeaway') existing.takeaway_quantity = Math.min(existing.quantity, Number(existing.takeaway_quantity || 0) + quantity);
        if (incomingNote && incomingNote !== String(existing.note || '').trim()) existing.note = [existing.note,incomingNote].filter(Boolean).join('؛ ').slice(0,500);
        clampTakeaway(existing);
        state.cart.set(id, existing);
      } else {
        state.cart.set(id, {...item, quantity, note:incomingNote, takeaway_quantity:mode === 'takeaway' ? quantity : 0, noteOpen:false});
      }
    }
  }

  const serverDraftFingerprint = (tableId, expectedSessionId, note, items) => JSON.stringify({
    table_id:Number(tableId||0),
    expected_session_id:Number(expectedSessionId||0),
    note:String(note||'').trim(),
    items:(Array.isArray(items)?items:[]).map((row)=>({
      id:Number(row.id||0), quantity:Number(row.quantity||0), note:String(row.note||'').trim(),
      expected_price:Number(row.expected_price??row.unit_price??0),
      fulfillment_mode:String(row.fulfillment_mode||'dine_in'),
    })),
  });

  function fingerprintFromServerDraft(draft) {
    if (!draft) return '';
    return serverDraftFingerprint(
      Number(draft.table_id||0),
      Number(draft.expected_session_id||0),
      String(draft.note||''),
      Array.isArray(draft.items) ? draft.items.map((row)=>({
        id:Number(row.id||0), quantity:Number(row.quantity||0), note:String(row.note||''),
        expected_price:Number(row.unit_price??row.expected_price??0),
        fulfillment_mode:String(row.fulfillment_mode||'dine_in'),
      })) : []
    );
  }

  function draftApiUrl(query = {}) {
    const url = new URL(window.STAFF_TABLE_DRAFT_API, window.location.href);
    Object.entries(query).forEach(([key,value])=>{ if(value !== null && value !== undefined && String(value) !== '') url.searchParams.set(key,String(value)); });
    return url.toString();
  }

  async function fetchDraftRecord({tableId = 0, draftId = 0} = {}) {
    if (!sharedDraftEnabled) return null;
    const response = await fetch(draftApiUrl(draftId > 0 ? {draft_id:draftId} : {table_id:tableId}), {headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
    const data = await response.json().catch(()=>({}));
    if (!response.ok || !data.success) {
      const error = new Error(data.message || 'دریافت پیش‌نویس انجام نشد.');
      error.code = String(data.code || '');
      error.status = Number(response.status || 0);
      throw error;
    }
    return data.draft || null;
  }

  function navigateOrderSuccess(table, data) {
    const destination = new URL(successUrl, window.location.origin);
    destination.searchParams.set('quick_order_success', table.name || `میز ${displayTableCode(table)}`);
    destination.searchParams.set('quick_order_table', String(table.id));
    destination.searchParams.set('quick_order_order', String(Number(data.order_id || 0)));
    destination.searchParams.set('quick_order_number', String(Number(data.order_number || data.order_id || 0)));
    destination.searchParams.set('quick_order_mode', lateAccounting ? 'late_accounting' : 'normal');
    destination.searchParams.set('open_table', String(table.id));
    if (lateAccounting) destination.searchParams.set('resume_settlement', 'itemized');
    else destination.searchParams.delete('resume_settlement');
    destination.searchParams.set('work', 'tables');
    destination.hash = 'tables';
    window.location.assign(`${destination.pathname}${destination.search}${destination.hash}`);
  }

  async function loadServerDraft(tableId, options = {}) {
    if (!sharedDraftEnabled) return null;
    const loadSeq = ++state.draftLoadSeq;
    const pending = options.skipUncertain ? null : readUncertain(tableId);
    if (pending?.kind === 'draft_finalize' && Number(pending.draftId || 0) > 0) {
      try {
        const recoveryDraft = await fetchDraftRecord({draftId:Number(pending.draftId)});
        if (loadSeq !== state.draftLoadSeq) return null;
        if (recoveryDraft?.state === 'finalized' && Number(recoveryDraft.final_order_id || 0) > 0) {
          clearUncertain(tableId);
          const table = state.tables.find((row)=>Number(row.id)===Number(tableId)) || {id:tableId,name:`میز ${tableId}`};
          navigateOrderSuccess(table,{order_id:Number(recoveryDraft.final_order_id),order_number:Number(recoveryDraft.final_order_id)});
          return recoveryDraft;
        }
      } catch (_) {}
    }

    const draft = await fetchDraftRecord({tableId});
    if (loadSeq !== state.draftLoadSeq) return draft;
    clearDraftState();
    state.draftTableId = Number(tableId || 0);
    if (draft) {
      state.serverDraftId = Number(draft.id || 0);
      state.serverDraftVersion = Number(draft.version || 0);
      state.serverDraftFingerprint = fingerprintFromServerDraft(draft);
      applyRequestRows(draft.items);
      els.note.value = String(draft.note || '').slice(0,500);
    }

    if (pending?.kind === 'draft_save') {
      const priorVersion = Number(pending.draftVersion || 0);
      const applied = Boolean(draft && Number(draft.version || 0) > priorVersion);
      if (applied) {
        clearUncertain(tableId);
      } else {
        applyRequestRows(pending.items);
        els.note.value = String(pending.note || '').slice(0,500);
        state.requestToken = '';
        clearUncertain(tableId);
        saveDraft();
      }
    } else if (pending?.kind === 'draft_finalize' && draft && Number(draft.id || 0) === Number(pending.draftId || 0)) {
      state.uncertain = true;
    }
    renderDraftStatus();
    renderCart();
    return draft;
  }

  async function saveDraftNow(options = {}) {
    if (lateAccounting) { saveLateAccountingDraft(); return null; }
    if (!sharedDraftEnabled) return null;
    if (state.draftSavePromise) await state.draftSavePromise;
    const table = selectedTable();
    const tableId = Number(table?.id || els.tableInput.value || 0);
    if (!tableId || (state.submitting && !options.allowSubmitting) || state.uncertain) return null;
    const items = requestLines();
    const note = String(els.note?.value || '').trim();
    if (!items.length && !note && state.serverDraftId < 1) { renderDraftStatus(); return null; }
    const expectedSessionId = Number(table?.session_id || 0);
    const fingerprint = serverDraftFingerprint(tableId, expectedSessionId, note, items);
    if (!options.force && fingerprint === state.serverDraftFingerprint) return {success:true,draft_id:state.serverDraftId,version:state.serverDraftVersion};

    const run = (async()=>{
      state.draftSaving = true; state.draftConflict = false; state.draftError = ''; renderDraftStatus();
      let response;
      try {
        response = await fetch(draftApiUrl(), {
          method:'POST', credentials:'same-origin', keepalive:Boolean(options.keepalive),
          headers:{Accept:'application/json','Content-Type':'application/json'},
          body:JSON.stringify({csrf_token:csrf,action:'save',table_id:tableId,expected_version:Number(state.serverDraftVersion||0),expected_session_id:expectedSessionId,note,items}),
        });
      } catch (error) {
        state.draftError = 'ارتباط هنگام ذخیره نامشخص شد؛ پیش‌نویس برای بازیابی نگه داشته شد.';
        state.uncertain = true;
        persistUncertain({kind:'draft_save',requestToken:newToken(),tableId,expectedSessionId,note,items,draftId:Number(state.serverDraftId||0),draftVersion:Number(state.serverDraftVersion||0)});
        renderDraftStatus(); renderCart();
        throw error;
      }
      const data = await response.json().catch(()=>({}));
      if (!response.ok || !data.success) {
        const error = new Error(data.message || 'ذخیره پیش‌نویس انجام نشد.');
        error.code = String(data.code || '');
        error.status = Number(response.status || 0);
        if (['version_conflict','session_changed'].includes(error.code)) {
          state.draftConflict = true;
          renderDraftStatus();
          await loadServerDraft(tableId,{skipUncertain:true});
          toast('پیش‌نویس توسط همکار یا وضعیت میز تغییر کرده بود؛ نسخه تازه بارگذاری شد.', true);
        } else {
          state.draftError = error.message;
          renderDraftStatus();
        }
        throw error;
      }
      const draft = data.draft || null;
      if (!draft) throw new Error('پاسخ ذخیره پیش‌نویس معتبر نیست.');
      state.serverDraftId = Number(draft.id || 0);
      state.serverDraftVersion = Number(draft.version || 0);
      state.serverDraftFingerprint = fingerprintFromServerDraft(draft);
      state.draftTableId = tableId;
      clearUncertain(tableId);
      state.draftError = '';
      renderDraftStatus();
      return data;
    })();
    state.draftSavePromise = run;
    try { return await run; }
    finally {
      if (state.draftSavePromise === run) state.draftSavePromise = null;
      state.draftSaving = false;
      renderDraftStatus();
    }
  }

  function saveDraft() {
    if (lateAccounting) { saveLateAccountingDraft(); return; }
    if (!sharedDraftEnabled || state.submitting || state.uncertain) return;
    if (state.draftSaveTimer) window.clearTimeout(state.draftSaveTimer);
    state.draftSaveTimer = window.setTimeout(()=>{
      state.draftSaveTimer = 0;
      void saveDraftNow().catch(()=>{});
    },240);
  }

  async function flushDraftSave(options = {}) {
    if (lateAccounting) { saveLateAccountingDraft(); return null; }
    if (state.draftSaveTimer) window.clearTimeout(state.draftSaveTimer);
    state.draftSaveTimer = 0;
    return saveDraftNow(options);
  }

  async function restoreDraft(tableId) {
    if (lateAccounting) { restoreLateAccountingDraft(tableId); return null; }
    return loadServerDraft(tableId);
  }

  function discardDraft(tableId) {
    if (lateAccounting) sessionStorage.removeItem(draftKey(tableId));
    safeLocalRemove(uncertainKey(tableId));
    if (Number(state.draftTableId) === Number(tableId)) {
      state.draftTableId = 0;
      state.serverDraftId = 0;
      state.serverDraftVersion = 0;
      state.serverDraftFingerprint = '';
      state.draftConflict = false;
      state.draftError = '';
      renderDraftStatus();
    }
  }

  function savedDraftHasContent(tableId) {
    if (!lateAccounting) return Number(state.draftTableId) === Number(tableId) && state.serverDraftId > 0;
    const raw = sessionStorage.getItem(draftKey(tableId));
    if (!raw) return false;
    try {
      const draft = JSON.parse(raw);
      return Number(draft?.version) === 7 && Number(draft?.tableId) === Number(tableId) && ((Array.isArray(draft.lines) && draft.lines.length > 0) || String(draft.note || '').trim() !== '');
    } catch (_) {
      sessionStorage.removeItem(draftKey(tableId));
      return false;
    }
  }

  async function cancelServerDraft(tableId, expectedVersion, options = {}) {
    if (!sharedDraftEnabled || Number(expectedVersion||0) < 1) return {success:true,cancelled:false};
    const response = await fetch(draftApiUrl(), {
      method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json'},
      body:JSON.stringify({csrf_token:csrf,action:'cancel',table_id:Number(tableId),expected_version:Number(expectedVersion)}),
    });
    const data = await response.json().catch(()=>({}));
    if (!response.ok || !data.success) {
      const error = new Error(data.message || 'لغو پیش‌نویس انجام نشد.');
      error.code = String(data.code || '');
      error.status = Number(response.status || 0);
      throw error;
    }
    if (options.clearCurrent && Number(els.tableInput.value||0) === Number(tableId)) clearDraftState();
    return data;
  }

  function hasDraft() {
    return state.cart.size > 0 || String(els.note.value || '').trim() !== '';
  }

  function discountAmount(subtotal, table) {
    const value = Math.max(0, Number(table?.discount_value || 0));
    if (table?.discount_type === 'percent') return Math.round(Math.max(0, subtotal) * Math.min(100, value) / 100);
    if (table?.discount_type === 'fixed') return Math.min(Math.max(0, subtotal), value);
    return 0;
  }

  function taxAmountFor(amount, rateBps) {
    const taxable = Math.max(0, Math.trunc(Number(amount) || 0));
    const rate = Math.max(0, Math.min(10000, Math.trunc(Number(rateBps) || 0)));
    if (!taxable || !rate) return 0;
    const whole = Math.floor(taxable / 10000) * rate;
    const remainder = taxable % 10000;
    return whole + Math.floor(((remainder * rate) + 5000) / 10000);
  }

  function proportionalTarget(totalValue, basisTotal, cumulativeBasis, final = false) {
    const total = Math.max(0, Math.trunc(Number(totalValue) || 0));
    const basis = Math.max(0, Math.trunc(Number(basisTotal) || 0));
    const cumulative = Math.max(0, Math.trunc(Number(cumulativeBasis) || 0));
    if (!total || !basis || !cumulative) return 0;
    if (final || cumulative >= basis) return total;
    const whole = Math.floor(total / basis) * cumulative;
    const remainder = total % basis;
    return Math.max(0, Math.min(total, whole + Math.floor(((remainder * cumulative) + Math.floor(basis / 2)) / basis)));
  }

  function calculateTaxFinancials(lines, requestedDiscount = 0) {
    const rows = (Array.isArray(lines) ? lines : []).map((line) => ({...line})).sort((a,b)=>Number(a.order_item_id||0)-Number(b.order_item_id||0));
    const subtotal = rows.reduce((sum,line)=>sum + Math.max(0,Number(line.unit_price||0))*Math.max(0,Number(line.quantity||0)),0);
    const discount = Math.max(0, Math.min(Math.trunc(Number(requestedDiscount)||0), subtotal));
    let runningGross = 0, allocated = 0, tax = 0, taxable = 0;
    rows.forEach((line,index)=>{
      const gross=Math.max(0,Number(line.unit_price||0))*Math.max(0,Number(line.quantity||0));
      runningGross += gross;
      const target=index===rows.length-1?discount:proportionalTarget(discount,subtotal,runningGross,false);
      const lineDiscount=Math.max(0,Math.min(gross,target-allocated));
      allocated += lineDiscount;
      const net=gross-lineDiscount;
      const policy=String(line.tax_policy_snapshot||line.tax_policy||'disabled');
      const rate=policy==='disabled'||policy==='exempt'?0:Number(line.tax_rate_bps_snapshot??line.tax_rate_bps??0);
      const lineTaxable=rate>0?net:0;
      taxable += lineTaxable;
      tax += taxAmountFor(lineTaxable,rate);
    });
    const net=subtotal-allocated;
    return {subtotal,discount:allocated,net,taxable,tax,total:net+tax};
  }

  function cartTaxLines() {
    const existingIds=(selectedTable()?.current_tax_lines||[]).map((line)=>Number(line.order_item_id||0));
    let synthetic=Math.max(0,...existingIds)+1;
    const rows=[];
    for(const line of selectedLines()){
      clampTakeaway(line);
      const totalQty=Math.max(0,Number(line.quantity||0));
      const takeaway=Math.max(0,Math.min(totalQty,Number(line.takeaway_quantity||0)));
      const dine=totalQty-takeaway;
      const item=state.items.find((row)=>Number(row.id)===Number(line.id))||line;
      for(const qty of [dine,takeaway]){
        if(!qty)continue;
        rows.push({order_item_id:synthetic++,quantity:qty,unit_price:Number(line.price||0),tax_policy_snapshot:String(item.tax_policy||'disabled'),tax_rate_bps_snapshot:Number(item.tax_rate_bps||0)});
      }
    }
    return rows;
  }

  function displayTableCode(table) {
    const canonical = Number(table?.table_number || 0);
    if (canonical > 0) return canonical.toLocaleString('fa-IR');
    const name = String(table?.name || '').trim();
    const nameNumber = latinDigits(name).match(/\d+/)?.[0];
    if (nameNumber) return Number(nameNumber).toLocaleString('fa-IR');
    return name.replace(/^(?:میز|table)\s*/iu, '').trim() || '—';
  }

  function tableNumber(table) {
    const canonical = Number(table?.table_number || 0);
    if (canonical > 0) return canonical;
    return Number(latinDigits(String(table?.name || '')).match(/\d+/)?.[0] || Number.MAX_SAFE_INTEGER);
  }

  function firstCategoryWithItems() {
    const ids = new Set(state.items.filter((item) => Number(item.order_available ?? 1) === 1).map((item) => Number(item.category_id)));
    return Number(state.categories.find((category) => ids.has(Number(category.id)))?.id || state.categories[0]?.id || 0);
  }

  function tableStatus(table) {
    const pending = Number(table.pending_order_count || 0);
    if (pending > 0) return {key: 'attention', label: `${digits(pending)} سفارش مهمان منتظر`};
    if (table.is_open) return {key: 'open', label: 'حساب باز'};
    return {key: 'free', label: 'میز آزاد'};
  }

  function tableCard(table) {
    const status = tableStatus(table);
    const amount = Number(table.current_final_total || 0) > 0 ? money(table.current_final_total) : '';
    return `<button class="quick-order-table-card is-${status.key}" type="button" data-qo-table="${Number(table.id)}"><span class="quick-order-table-code"><small>میز</small><strong>${esc(displayTableCode(table))}</strong></span><span class="quick-order-table-copy"><strong>${esc(textDigits(table.name))}</strong><small>${table.zone_label ? `${esc(textDigits(table.zone_label))} · ` : ''}${esc(status.label)}</small></span><span class="quick-order-table-amount">${amount || (status.key === 'free' ? 'آماده ثبت سفارش' : 'بدون مبلغ ثبت‌شده')}</span></button>`;
  }

  function renderTableGroups() {
    const sorted = [...state.tables].sort((a, b) => {
      const rank = (table) => Number(table.pending_order_count || 0) > 0 ? 0 : table.is_open ? 1 : 2;
      return rank(a) - rank(b) || tableNumber(a) - tableNumber(b) || Number(a.sort_order || 0) - Number(b.sort_order || 0);
    });
    els.tableCount.textContent = `${digits(sorted.length)} میز فعال`;
    if (!sorted.length) {
      els.tableGroups.innerHTML = '<div class="empty-state">میز فعالی برای ثبت سفارش وجود ندارد.</div>';
      return;
    }
    const groups = [
      ['نیازمند رسیدگی', sorted.filter((table) => Number(table.pending_order_count || 0) > 0)],
      ['حساب‌های باز', sorted.filter((table) => Number(table.pending_order_count || 0) === 0 && table.is_open)],
      ['میزهای آزاد', sorted.filter((table) => !table.is_open)],
    ];
    els.tableGroups.innerHTML = groups.filter(([, rows]) => rows.length).map(([label, rows]) => `<section class="quick-order-table-group"><header><strong>${esc(label)}</strong><span>${digits(rows.length)}</span></header><div class="quick-order-table-grid">${rows.map(tableCard).join('')}</div></section>`).join('');
  }

  const catalogUrl = (menuKey = '') => {
    const url = new URL(window.STAFF_QUICK_ORDER_API, window.location.href);
    if (lateAccounting) url.searchParams.set('mode', 'late_accounting');
    if (menuKey) url.searchParams.set('menu', menuKey);
    return url.toString();
  };

  function renderMenus() {
    if (!els.menus) return;
    const rows = Array.isArray(state.menus) ? state.menus : [];
    els.menus.classList.toggle('hidden', rows.length === 0);
    if (rows.length === 1) {
      els.menus.innerHTML = `<span class="quick-order-menu-context">منوی فعال: <strong>${esc(rows[0].name)}</strong></span>`;
      return;
    }
    els.menus.innerHTML = rows.map((menu) => `<button type="button" data-qo-menu="${esc(menu.menu_key)}" class="${String(menu.menu_key)===String(state.menuKey)?'is-active':''}" aria-pressed="${String(menu.menu_key)===String(state.menuKey)}">${esc(menu.name)}</button>`).join('');
  }

  async function selectMenu(menuKey) {
    const key = String(menuKey || '');
    if (!key || key === state.menuKey || state.loading) return;
    state.loading = true;
    try {
      const response = await fetch(catalogUrl(key), {headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) throw new Error(data.message || 'تغییر منو انجام نشد.');
      state.menus = Array.isArray(data.menus) ? data.menus : [];
      state.menuKey = String(data.selected_menu?.menu_key || key);
      state.categories = Array.isArray(data.categories) ? data.categories : [];
      state.items = Array.isArray(data.items) ? data.items : [];
      state.category = firstCategoryWithItems();
      state.searchOpen = false; els.search.value = '';
      renderMenus(); renderCategories(); renderItems(); saveDraft();
    } catch (error) { toast(requestErrorMessage(error, 'تغییر منو انجام نشد.'), true); }
    finally { state.loading = false; }
  }

  function renderCategories() {
    const categories = state.categories.filter((category) => state.items.some((item) => Number(item.category_id) === Number(category.id) && Number(item.order_available ?? 1) === 1));
    els.categories.innerHTML = categories.length ? categories.map((category) => {
      const explicitKey = String(category.icon_key || '').trim();
      const resolvedKey = String(category.icon || '').trim();
      const key = explicitKey || resolvedKey;
      const showIcon = validIcons.has(key) && !(explicitKey === '' && key === 'sparkles');
      return `<button type="button" class="quick-order-category ${Number(category.id) === Number(state.category) ? 'is-active' : ''} ${showIcon ? 'has-icon' : 'no-icon'}" data-qo-category="${Number(category.id)}" aria-pressed="${Number(category.id) === Number(state.category)}">${showIcon ? icon(key) : ''}<span>${esc(category.name)}</span></button>`;
    }).join('') : '<div class="empty-state">دسته‌بندی فعالی وجود ندارد.</div>';
  }

  function itemControl(item, line) {
    if (Number(item.order_available ?? 1) !== 1) return '<span class="quick-order-unavailable">فعلاً قابل سفارش نیست</span>';
    if (!line) return `<span class="quick-order-add" aria-hidden="true">${icon('plus')}</span>`;
    return `<div class="quick-order-inline-qty"><button class="is-minus" type="button" data-qo-delta="-1" data-id="${Number(item.id)}" aria-label="کم‌کردن ${esc(item.name)}">${icon('minus')}</button><span>${digits(line.quantity)}</span><button class="is-plus" type="button" data-qo-delta="1" data-id="${Number(item.id)}" aria-label="افزودن ${esc(item.name)}">${icon('plus')}</button></div>`;
  }

  function syncDesktopWorkspaceHeight(visibleCount = null) {
    if (mobileMedia.matches || !els.workspace || els.workspace.classList.contains('hidden')) {
      els.workspace?.style.removeProperty('--qo-desktop-height');
      return;
    }
    const query = normalize(els.search.value);
    const count = visibleCount == null ? state.items.filter((item) => query ? normalize(`${item.name} ${item.category_name || ''}`).includes(query) : Number(state.category) === 0 || Number(item.category_id) === Number(state.category)).length : Number(visibleCount);
    const columns = window.innerWidth >= 1600 ? 5 : 4;
    const rows = Math.max(1, Math.ceil(Math.max(1, count) / columns));
    const catalogHeight = 48 + Math.min(rows, 7) * 103 + 16;
    const hasPrevious = Number(selectedTable()?.current_final_total || 0) > 0 || Number(selectedTable()?.current_order_count || 0) > 0;
    const cartHeight = 42 + (hasPrevious ? 44 : 0) + Math.max(1, state.cart.size) * 54 + 42 + 116;
    const available = Math.max(480, window.innerHeight - 72);
    const viewportFloor = window.innerHeight >= 1000 ? 560 : window.innerHeight >= 850 ? 520 : 480;
    const target = Math.max(viewportFloor, Math.min(760, Math.max(catalogHeight, cartHeight)));
    els.workspace.style.setProperty('--qo-desktop-height', `${Math.min(available, target)}px`);
  }

  function renderItems() {
    const query = normalize(els.search.value);
    els.searchClear.classList.toggle('hidden', query === '');
    const visible = state.items.filter((item) => query ? normalize(`${item.name} ${item.category_name || ''}`).includes(query) : Number(state.category) === 0 || Number(item.category_id) === Number(state.category));
    els.items.innerHTML = visible.length ? visible.map((item) => {
      const line = state.cart.get(Number(item.id));
      return `<article class="quick-order-item ${line ? 'is-selected' : ''} ${Number(item.order_available ?? 1) === 1 ? '' : 'is-unavailable'}" ${Number(item.order_available ?? 1) === 1 ? `data-qo-add="${Number(item.id)}" role="button" tabindex="0"` : ''}><div class="quick-order-item-copy"><strong>${esc(item.name)}</strong><small>${money(item.price)}</small>${Number(item.order_available ?? 1) !== 1 ? `<em>${esc(item.unavailable_message || 'بخش مربوط فعلاً سفارش نمی‌پذیرد')}</em>` : ''}</div><div class="quick-order-item-action">${itemControl(item, line)}</div></article>`;
    }).join('') : `<div class="empty-state">${query ? 'آیتمی با این عبارت پیدا نشد.' : 'این دسته فعلاً آیتم قابل سفارش ندارد.'}</div>`;
    syncDesktopWorkspaceHeight(visible.length);
  }

  function renderPending(table) {
    const orders = Array.isArray(table.pending_orders) ? table.pending_orders : [];
    if (!orders.length) {
      els.pending.classList.add('hidden');
      els.pending.innerHTML = '';
      els.mobilePending?.classList.add('hidden');
      return;
    }
    els.pending.innerHTML = `<div class="quick-order-pending-head"><strong>${digits(orders.length)} سفارش مهمان منتظر است</strong><small>هر سفارش را جداگانه تأیید یا رد کنید.</small></div><div class="quick-order-pending-orders">${orders.map((order) => { const busy = state.pendingReview.has(Number(order.id)); return `<article><header><strong>سفارش ${digits(order.number || order.id)}</strong><span>جمع اقلام ${money(order.subtotal)}</span></header>${(order.items || []).map((item) => `<p>${digits(item.quantity)} × ${esc(item.name)}</p>`).join('')}<div class="quick-order-pending-actions"><button type="button" data-qo-pending-order="${Number(order.id)}" data-qo-pending-status="accounted" class="is-active"${busy ? ' disabled' : ''}>${busy ? 'در حال بررسی…' : 'تأیید سفارش'}</button><button type="button" data-qo-pending-order="${Number(order.id)}" data-qo-pending-status="cancelled"${busy ? ' disabled' : ''}>رد سفارش</button></div></article>`; }).join('')}</div>`;
    els.pending.classList.remove('hidden');
    els.mobilePending?.classList.toggle('hidden', !mobileMedia.matches);
    if (els.mobilePendingCount) els.mobilePendingCount.textContent = `${digits(orders.length)} سفارش مهمان منتظر بررسی است`;
  }

  function renderCurrent(table) {
    const lines = Array.isArray(table.current_items) ? table.current_items.filter((line) => Number(line.quantity || 0) > 0) : [];
    const has = Boolean(table.is_open && (Number(table.current_order_count || 0) > 0 || Number(table.current_final_total || 0) > 0));
    els.current.classList.toggle('hidden', !has);
    if (!has) {
      els.currentLines.innerHTML = '';
      els.currentTotal.textContent = '۰';
      els.currentCount.textContent = '';
      return;
    }
    els.currentTotal.textContent = money(table.current_final_total || 0);
    els.currentCount.textContent = `${digits(table.current_order_count || 0)} نوبت · ${digits(table.current_quantity || 0)} عدد`;
    els.current.open = false;
    els.currentLines.innerHTML = lines.length ? lines.map((line) => `<div><span><strong>${esc(line.name)}</strong>${line.note ? `<small>${esc(line.note)}</small>` : ''}</span><b>${digits(line.quantity)} × ${money(line.unit_price)}</b></div>`).join('') : '<div class="empty-state compact">حساب باز است، اما آیتم فعالی ندارد.</div>';
  }

  function clearUndoState(render = false) {
    if (state.clearUndoTimer) window.clearTimeout(state.clearUndoTimer);
    state.clearUndoTimer = 0;
    state.clearUndo = null;
    if (render) renderCart();
  }

  function currentClearUndo() {
    const undo = state.clearUndo;
    if (!undo) return null;
    if (Number(undo.tableId || 0) !== Number(els.tableInput.value || 0) || Date.now() >= Number(undo.expiresAt || 0)) {
      clearUndoState(false);
      return null;
    }
    return undo;
  }

  function invalidateClearUndo() {
    if (!state.clearUndo) return;
    clearUndoState(false);
  }

  function armClearUndo() {
    const tableId = Number(els.tableInput.value || 0);
    if (!tableId) return false;
    const lines = selectedLines().map((line) => ({...line, quantity: Number(line.quantity || 0), note: String(line.note || ''), noteOpen: Boolean(line.noteOpen)}));
    const note = String(els.note?.value || '');
    if (!lines.length && !note.trim()) return false;
    clearUndoState(false);
    state.clearUndo = {
      tableId,
      lines,
      note,
      noteOpen: !els.noteWrap?.classList.contains('hidden'),
      expiresAt: Date.now() + CLEAR_UNDO_MS,
    };
    state.clearUndoTimer = window.setTimeout(() => clearUndoState(true), CLEAR_UNDO_MS + 20);
    return true;
  }

  function restoreClearedCart() {
    const undo = currentClearUndo();
    if (!undo) { renderCart(); return false; }
    state.cart.clear();
    for (const saved of undo.lines) {
      const item = state.items.find((candidate) => Number(candidate.id) === Number(saved.id));
      if (!item) continue;
      const quantity=Math.min(50, Math.max(1, Number(saved.quantity || 1)));
      state.cart.set(Number(item.id), {...item, quantity, note: String(saved.note || '').slice(0, 500), takeaway_quantity: Math.max(0,Math.min(quantity,Number(saved.takeaway_quantity||0))), noteOpen: Boolean(saved.noteOpen)});
    }
    state.fulfillmentEditing = false;
    els.note.value = String(undo.note || '').slice(0, 500);
    els.noteWrap.classList.toggle('hidden', !undo.noteOpen);
    els.noteToggle.setAttribute('aria-expanded', undo.noteOpen ? 'true' : 'false');
    state.requestToken = '';
    clearUndoState(false);
    saveDraft();
    renderCart();
    toast('سبد سفارش بازگردانده شد.');
    return true;
  }

  function renderClearAction() {
    const undo = currentClearUndo();
    const isUndo = Boolean(undo);
    const actionLabel = isUndo ? 'بازگردانی حذف سبد' : 'پاک‌کردن سبد';
    const actionIcon = isUndo ? 'undo' : 'trash';
    els.clear.innerHTML = icon(actionIcon);
    els.clear.setAttribute('aria-label', actionLabel);
    els.clear.setAttribute('title', actionLabel);
    els.clear.dataset.qoClearAction = isUndo ? 'undo' : 'clear';
    const proxy = document.querySelector('[data-qo-clear-proxy]');
    if (proxy) {
      proxy.innerHTML = `${icon(actionIcon)}<span>${actionLabel}</span>`;
      proxy.setAttribute('aria-label', actionLabel);
      proxy.setAttribute('title', actionLabel);
      proxy.dataset.qoClearAction = isUndo ? 'undo' : 'clear';
    }
  }

  function renderCartLines() {
    const lines = selectedLines();
    const locked = state.uncertain ? ' disabled' : '';
    const readonly = state.uncertain ? ' readonly' : '';
    els.cartLines.innerHTML = lines.length ? lines.map((line) => {
      clampTakeaway(line);
      const take=Number(line.takeaway_quantity||0),quantity=Number(line.quantity||0),eligible=fulfillment.allowed(line);
      let fulfillmentUi='';
      if(state.fulfillmentEditing){
        if(!eligible){
          fulfillmentUi='<span class="quick-order-takeaway-ineligible">فقط داخل</span>';
        }else if(quantity===1){
          fulfillmentUi=`<button class="quick-order-takeaway-toggle ${take?'is-on':''}" type="button" data-qo-takeaway-toggle="${Number(line.id)}"${locked}>${take?`${icon('check')} بیرون‌بر`:'داخل کافه'}</button>`;
        }else{
          fulfillmentUi=`<div class="quick-order-takeaway-stepper"><button type="button" data-qo-takeaway-delta="-1" data-id="${Number(line.id)}" aria-label="کم‌کردن تعداد بیرون‌بر"${take<=0?' disabled':''}${locked}>${icon('minus')}</button>${fulfillment.ratioHtml(take,quantity,digits)}<button type="button" data-qo-takeaway-delta="1" data-id="${Number(line.id)}" aria-label="افزودن تعداد بیرون‌بر"${take>=quantity?' disabled':''}${locked}>${icon('plus')}</button></div>`;
        }
      }else if(take>0){
        fulfillmentUi=`<span class="quick-order-takeaway-badge">${esc(fulfillment.label(line,digits))}</span>`;
      }
      const unitMath=quantity>1?`${digits(quantity)} × ${money(line.price)}`:'';
      const hasTakeawayStepper=state.fulfillmentEditing&&eligible&&quantity>1;
      const quantityUi=state.fulfillmentEditing
        ? `<span class="quick-order-line-qty-readonly" aria-label="تعداد کل ${digits(quantity)}"><small>تعداد</small><strong>${digits(quantity)}</strong></span>`
        : `<div class="quick-order-inline-qty quick-order-line-qty"><button class="is-minus" type="button" data-qo-delta="-1" data-id="${Number(line.id)}" aria-label="کم‌کردن"${locked}>${icon('minus')}</button><span>${digits(line.quantity)}</span><button class="is-plus" type="button" data-qo-delta="1" data-id="${Number(line.id)}" aria-label="افزودن"${locked}>${icon('plus')}</button></div>`;
      return `<article class="quick-order-cart-line${state.fulfillmentEditing?' is-takeaway-editing':''}${hasTakeawayStepper?' has-takeaway-stepper':''}"><div class="quick-order-line-copy"><strong title="${esc(line.name)}">${esc(line.name)}</strong><div class="quick-order-line-meta">${unitMath?`<small>${unitMath}</small>`:''}<b class="quick-order-line-total">${money(line.price * line.quantity)}</b></div>${!state.fulfillmentEditing&&take>0?fulfillmentUi:''}</div><div class="quick-order-line-actions">${quantityUi}${state.fulfillmentEditing?fulfillmentUi:''}<button class="quick-order-line-note-button ${line.note ? 'has-note' : ''}" type="button" data-qo-note-toggle="${Number(line.id)}" aria-label="${line.note ? 'ویرایش یادداشت این آیتم' : 'افزودن یادداشت به این آیتم'}" title="${line.note ? 'ویرایش یادداشت' : 'یادداشت آیتم'}"${locked}>${icon('message')}</button></div>${line.note ? `<p class="quick-order-line-note">${esc(line.note)}</p>` : ''}${line.noteOpen ? `<textarea class="form-control" data-qo-note-input="${Number(line.id)}" maxlength="500" placeholder="یادداشت این آیتم"${readonly}>${esc(line.note || '')}</textarea>` : ''}</article>`;
    }).join('') : '<div class="empty-state">با لمس یک آیتم، اینجا اضافه می‌شود.</div>';
  }

  function renderCart() {
    const table = selectedTable();
    const distinct = state.cart.size;
    const quantity = cartQuantity();
    const cartSubtotal = cartTotal();
    const cartFinancials = calculateTaxFinancials(cartTaxLines(),0);
    const pending = Number(table?.pending_order_count || 0) > 0;
    const currentSubtotal = Number(table?.current_total || 0);
    const currentFinal = Number(lateAccounting ? (table?.remaining_total ?? table?.current_final_total ?? 0) : (table?.current_final_total || 0));
    const projectedLines=[...(Array.isArray(table?.current_tax_lines)?table.current_tax_lines:[]),...cartTaxLines()];
    const projectedSubtotal = currentSubtotal + cartSubtotal;
    const projectedFinancials=calculateTaxFinancials(projectedLines,discountAmount(projectedSubtotal,table));
    const projected = projectedFinancials.total;
    const countText = distinct ? `${digits(distinct)} قلم${distinct !== quantity ? ` · ${digits(quantity)} عدد` : ''}` : 'هنوز آیتمی انتخاب نشده';
    const hasPrevious = currentFinal > 0 || Number(table?.current_order_count || 0) > 0;
    const totalTakeaway = cartTakeawayQuantity();
    const eligibleQuantity = cartEligibleQuantity();
    const allEligibleTakeaway = eligibleQuantity > 0 && totalTakeaway === eligibleQuantity;
    els.takeawayTool?.classList.toggle('hidden', lateAccounting || distinct===0 || eligibleQuantity===0);
    if(els.takeawayTool){els.takeawayTool.textContent=totalTakeaway>0?`${digits(totalTakeaway)} بیرون‌بر`:'بیرون‌بر';els.takeawayTool.classList.toggle('is-active',state.fulfillmentEditing||totalTakeaway>0);els.takeawayTool.setAttribute('aria-pressed',state.fulfillmentEditing?'true':'false');}
    if(lateAccounting)state.fulfillmentEditing=false;
    els.takeawayMode?.classList.toggle('hidden', lateAccounting || !state.fulfillmentEditing || eligibleQuantity===0);
    if(els.takeawayAll){els.takeawayAll.textContent=allEligibleTakeaway?'همه داخل کافه':'همه بیرون‌بر';els.takeawayAll.disabled=state.uncertain;}
    const fulfillmentSummary=$('quickOrderFulfillmentSummary');
    if (fulfillmentSummary) { fulfillmentSummary.textContent=totalTakeaway>0?`${digits(totalTakeaway)} بیرون‌بر`:''; fulfillmentSummary.classList.toggle('hidden', totalTakeaway===0); }

    els.cartTitle.textContent = countText;
    els.total.textContent = money(cartFinancials.total);
    if(els.newTaxHint){els.newTaxHint.textContent=cartFinancials.tax>0?`شامل ${money(cartFinancials.tax)} مالیات`:'';els.newTaxHint.classList.toggle('hidden',cartFinancials.tax<=0);}
    els.previousTotal.textContent = money(currentFinal);
    els.projected.textContent = money(projected);
    if(els.projectedTaxHint){els.projectedTaxHint.textContent=projectedFinancials.tax>0?`شامل ${money(projectedFinancials.tax)} مالیات`:'';els.projectedTaxHint.classList.toggle('hidden',projectedFinancials.tax<=0);}
    els.mobileCount.textContent = countText;
    els.mobileTotal.textContent = money(cartFinancials.total);
    els.previousRow.classList.toggle('hidden', !hasPrevious);
    els.projectedRow.classList.toggle('hidden', !hasPrevious);
    els.mobileBar.disabled = !distinct && Number(table?.pending_order_count || 0) <= 0;
    els.uncertainNotice?.classList.toggle('hidden', !state.uncertain);
    els.changeTable.disabled = state.uncertain || lateAccounting;
    const canUndoClear = Boolean(currentClearUndo());
    renderClearAction();
    els.clear.disabled = state.uncertain || (!canUndoClear && !distinct && !String(els.note.value || '').trim());
    els.noteToggle.disabled = state.uncertain;
    els.note.readOnly = state.uncertain;
    renderDraftStatus();
    updateGeneralNoteUi();
    els.submit.disabled = !table || !distinct || pending || state.submitting;
    els.submit.textContent = pending ? 'ابتدا سفارش مهمان را بررسی کنید' : state.submitting ? 'در حال بررسی…' : state.uncertain ? `بررسی و تلاش دوباره برای ${textDigits(table?.name || 'میز')}` : distinct ? (lateAccounting ? `ثبت قلم جاافتاده برای ${textDigits(table?.name || 'میز')}` : `ثبت سفارش برای ${textDigits(table?.name || 'میز')}`) : 'یک آیتم انتخاب کنید';
    renderCartLines();
    renderItems();
  }

  function updateGeneralNoteUi() {
    const noteBox = els.noteToggle?.closest('.quick-order-note');
    if (!noteBox || !els.note || !els.noteWrap || !els.noteToggle) return;
    const value = String(els.note.value || '').trim();
    const open = !els.noteWrap.classList.contains('hidden');
    noteBox.classList.toggle('has-value', Boolean(value));
    noteBox.classList.toggle('is-open', open);
    const label = els.noteToggle.querySelector('span');
    if (label) label.textContent = value ? `یادداشت کلی: ${value.length > 34 ? value.slice(0, 34) + '…' : value}` : 'یادداشت کلی';
    els.noteToggle.title = value || 'یادداشت کلی سفارش';
    const menuLabel = document.querySelector('[data-qo-global-note] span');
    if (menuLabel) menuLabel.textContent = value ? 'ویرایش یادداشت کلی' : 'افزودن یادداشت کلی';
  }

  function renderSelectedContext() {
    const table = selectedTable();
    if (!table) return;
    const status = tableStatus(table);
    els.tableName.textContent = textDigits(table.name);
    els.tableMeta.textContent = [table.zone_label ? textDigits(table.zone_label) : '', status.label].filter(Boolean).join(' · ');
    els.headerContext.classList.remove('hidden');
    els.changeTable.classList.toggle('hidden', lateAccounting);
    renderPending(table);
    renderCurrent(table);
    renderCart();
  }

  function showTableView(focus = true, viewMode = 'browse') {
    if(lateAccounting){toast('در حالت قلم جاافتاده، میز قابل تغییر نیست.', true);return;}
    closeCart(false);
    state.changeTableMode = viewMode === 'change';
    const title = $('quickOrderTableTitle');
    const copy = title?.parentElement?.querySelector('p');
    if (title) title.textContent = state.changeTableMode ? 'انتقال سفارش به میز دیگر' : 'انتخاب میز';
    if (copy) copy.textContent = state.changeTableMode ? 'میز مقصد را انتخاب کنید؛ اقلام همین سفارش منتقل می‌شوند.' : 'میز موردنظر را انتخاب کنید.';
    els.tableStage.classList.remove('hidden');
    els.workspace.classList.add('hidden');
    els.tableStage.scrollTop = 0;
    els.headerContext.classList.add('hidden');
    els.changeTable.classList.add('hidden');
    if (focus) requestAnimationFrame(() => els.tableGroups.querySelector('button')?.focus({preventScroll: true}));
  }

  function showWorkspace(focus = true) {
    state.changeTableMode = false;
    els.tableStage.classList.add('hidden');
    els.workspace.classList.remove('hidden');
    state.category = state.category || firstCategoryWithItems();
    if (mobileMedia.matches) state.mobileView = 'categories';
    else state.mobileView = 'items';
    syncMobileView();
    renderSelectedContext();
    renderCategories();
    renderItems();
    if (focus) requestAnimationFrame(() => (mobileMedia.matches ? els.categories.querySelector('button') : els.search)?.focus?.({preventScroll: true}));
  }

  function syncMobileView() {
    const itemMode = mobileMedia.matches && state.mobileView === 'items';
    els.workspace.classList.toggle('is-mobile-items', itemMode);
    const category = state.categories.find((row) => Number(row.id) === Number(state.category));
    els.categoryTitle.textContent = itemMode ? (category?.name || 'اقلام') : (mobileMedia.matches ? 'دسته‌بندی‌ها' : category?.name || 'اقلام');
    if (!mobileMedia.matches) {
      state.searchOpen = true;
      els.searchWrap.classList.remove('is-collapsed');
    } else {
      els.searchWrap.classList.toggle('is-collapsed', !state.searchOpen);
    }
    els.searchToggle.setAttribute('aria-expanded', state.searchOpen ? 'true' : 'false');
  }

  function updateUrlTable(tableId) {
    const url = new URL(window.location.href);
    url.searchParams.set('table_id', String(tableId));
    history.replaceState(history.state, '', url);
  }

  async function selectTable(id, options = {}) {
    if(lateAccounting && Number(id)!==Number(initialTableId)){toast('در حالت قلم جاافتاده، میز ثابت است.', true);return;}
    if (state.uncertain) { toast('ابتدا نتیجه ثبت نامشخص سفارش فعلی را تعیین کنید.', true); return; }
    const next = state.tables.find((row) => Number(row.id) === Number(id));
    if (!next) return;
    const current = selectedTable();
    const currentId = Number(current?.id || 0);
    const nextId = Number(next.id);

    try {
      if (currentId && currentId !== nextId && state.changeTableMode && sharedDraftEnabled) {
        invalidateClearUndo();
        await flushDraftSave();
        const carry = hasDraft();
        const carryLines = selectedLines().map((row)=>({...row}));
        const carryNote = String(els.note?.value || '');
        const carryCategory = Number(state.category || 0);
        const destinationDraft = await fetchDraftRecord({tableId:nextId});
        if (carry && destinationDraft) {
          const replace = await confirmAction(`${next.name} یک سفارش نیمه‌کاره روی سرور دارد. با انتقال این سفارش، پیش‌نویس قبلی آن میز لغو و جایگزین می‌شود.`, 'پیش‌نویس میز مقصد', {okLabel:'جایگزینی و انتقال',danger:true});
          if (!replace) return;
          await cancelServerDraft(nextId,Number(destinationDraft.version||0));
        }
        if (state.serverDraftId > 0) await cancelServerDraft(currentId,state.serverDraftVersion);
        clearDraftState();
        els.tableInput.value = String(nextId);
        state.draftTableId = nextId;
        state.category = carryCategory;
        if (carry) {
          for (const row of carryLines) state.cart.set(Number(row.id),row);
          els.note.value = carryNote;
          await saveDraftNow({force:true});
        } else {
          await restoreDraft(nextId);
        }
      } else if (currentId && currentId !== nextId && state.changeTableMode) {
        invalidateClearUndo();
        saveDraft();
        const carry = hasDraft();
        discardDraft(nextId);
        discardDraft(currentId);
        els.tableInput.value = String(nextId);
        state.draftTableId = nextId;
        state.requestToken = '';
        if (carry) saveDraft();
      } else if (!currentId || currentId !== nextId) {
        invalidateClearUndo();
        if (currentId) await flushDraftSave();
        els.tableInput.value = String(nextId);
        await restoreDraft(nextId);
      } else {
        els.tableInput.value = String(nextId);
      }
    } catch (error) {
      toast(requestErrorMessage(error,'تغییر میز انجام نشد؛ پیش‌نویس فعلی حفظ شد.'),true);
      return;
    }

    updateUrlTable(nextId);
    showWorkspace(options.focus !== false);
  }

  function selectMobileCategory(categoryId, pushHistory = true) {
    state.category = Number(categoryId);
    state.mobileView = 'items';
    state.searchOpen = false;
    syncMobileView();
    renderCategories();
    renderItems();
    saveDraft();
    if (pushHistory && mobileMedia.matches && history.state?.quickOrderView !== 'items') history.pushState({quickOrderView: 'items'}, '', window.location.href);
    window.scrollTo({top: 0, behavior: 'instant'});
    requestAnimationFrame(() => els.items.querySelector('[data-qo-add]')?.focus?.({preventScroll: true}));
  }

  function showMobileCategories(fromHistory = false) {
    if (!mobileMedia.matches) return;
    state.mobileView = 'categories';
    state.searchOpen = false;
    els.search.value = '';
    syncMobileView();
    renderItems();
    if (!fromHistory && history.state?.quickOrderView === 'items') history.back();
    requestAnimationFrame(() => els.categories.querySelector(`[data-qo-category="${state.category}"]`)?.focus?.({preventScroll: true}));
  }

  async function loadCatalog() {
    if (state.loading || state.loaded) return;
    state.loading = true;
    els.submit.disabled = true;
    try {
      const response = await fetch(catalogUrl(state.menuKey), {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
      const data = await response.json().catch(() => ({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) throw new Error(data.message || 'دریافت اطلاعات ثبت سفارش انجام نشد.');
      state.tables = Array.isArray(data.tables) ? data.tables : [];
      state.menus = Array.isArray(data.menus) ? data.menus : [];
      state.menuKey = String(data.selected_menu?.menu_key || '');
      state.categories = Array.isArray(data.categories) ? data.categories : [];
      state.items = Array.isArray(data.items) ? data.items : [];
      state.category = firstCategoryWithItems();
      renderMenus();
      state.loaded = true;
      renderTableGroups();
      renderCategories();
      const requestedId = initialTableId || Number(new URL(window.location.href).searchParams.get('table_id') || 0);
      if (requestedId && state.tables.some((table) => Number(table.id) === requestedId)) {
        els.tableInput.value = String(requestedId);
        await restoreDraft(requestedId);
        showWorkspace(false);
      } else if (lateAccounting) {
        els.tableGroups.innerHTML = '<div class="empty-state is-error">حساب این میز برای ثبت قلم جاافتاده در دسترس نیست؛ به صندوق برگردید و حساب را تازه کنید.</div>';
        els.tableStage.classList.remove('hidden');
        els.workspace.classList.add('hidden');
      } else {
        showTableView(false);
      }
    } catch (error) {
      const message = requestErrorMessage(error, 'دریافت اطلاعات ثبت سفارش انجام نشد.');
      els.tableGroups.innerHTML = `<div class="empty-state is-error">${esc(message)}</div>`;
      els.items.innerHTML = `<div class="empty-state is-error">${esc(message)}</div>`;
      toast(message, true);
    } finally {
      state.loading = false;
      renderCart();
    }
  }

  function addItem(id) {
    if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، سبد قابل تغییر نیست.', true); return; }
    const item = state.items.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    if (Number(item.order_available ?? 1) !== 1) {
      toast(item.unavailable_message || 'این بخش فعلاً سفارش نمی‌پذیرد.', true);
      return;
    }
    const inheritTakeaway = cartIsAllTakeaway();
    const line = state.cart.get(Number(id)) || {...item, quantity: 0, note: '', takeaway_quantity: 0, noteOpen: false};
    const wasAllTakeaway=Number(line.quantity||0)>0 && Number(line.takeaway_quantity||0)===Number(line.quantity||0);
    const wasEmpty = Number(line.quantity || 0) === 0;
    line.quantity = Math.min(50, Number(line.quantity || 0) + 1);
    if(wasAllTakeaway || (wasEmpty && inheritTakeaway)) line.takeaway_quantity=line.quantity; else clampTakeaway(line);
    state.cart.set(Number(id), line);
    resetToken();
    renderCart();
  }

  function delta(id, amount) {
    if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، سبد قابل تغییر نیست.', true); return; }
    const line = state.cart.get(Number(id));
    if (!line) return;
    const wasAllTakeaway=Number(line.quantity||0)>0 && Number(line.takeaway_quantity||0)===Number(line.quantity||0);
    line.quantity = Number(line.quantity || 0) + Number(amount || 0);
    if (line.quantity < 1) state.cart.delete(Number(id));
    else { if(Number(amount||0)>0 && wasAllTakeaway) line.takeaway_quantity=line.quantity; else clampTakeaway(line); state.cart.set(Number(id), line); }
    resetToken();
    renderCart();
  }

  async function refreshCatalogPreservingDraft() {
    const tableId = Number(els.tableInput.value || 0);
    const currentCategory = Number(state.category || 0);
    const response = await fetch(catalogUrl(state.menuKey), {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
    const data = await response.json().catch(() => ({}));
    window.SoknaPushRuntime?.handleResponse?.(data);
    if (!response.ok || !data.success) throw new Error(data.message || 'به‌روزرسانی وضعیت میز انجام نشد.');
    state.tables = Array.isArray(data.tables) ? data.tables : [];
    state.menus = Array.isArray(data.menus) ? data.menus : [];
    state.menuKey = String(data.selected_menu?.menu_key || state.menuKey || '');
    state.categories = Array.isArray(data.categories) ? data.categories : [];
    state.items = Array.isArray(data.items) ? data.items : [];
    renderMenus();
    state.category = state.categories.some((row) => Number(row.id) === currentCategory) ? currentCategory : firstCategoryWithItems();
    renderTableGroups();
    renderCategories();
    if (tableId && state.tables.some((row) => Number(row.id) === tableId)) {
      renderSelectedContext();
      renderItems();
    }
  }

  async function reviewPendingOrder(orderId, status) {
    const id = Number(orderId || 0);
    if (!id || !['accounted','cancelled'].includes(status) || state.pendingReview.has(id)) return;
    if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، سفارش مهمان قابل تغییر نیست.', true); return; }
    const table = selectedTable();
    const order = (table?.pending_orders || []).find((row) => Number(row.id) === id);
    if (!table || !order) { toast('این سفارش دیگر منتظر بررسی نیست؛ فهرست را تازه کنید.', true); return; }
    if (status === 'cancelled') {
      const approved = await confirmAction('این سفارش از صف تأیید خارج می‌شود و برای آماده‌سازی ارسال نخواهد شد.', 'رد سفارش', {okLabel: 'رد سفارش', danger: true});
      if (!approved) return;
    }
    state.pendingReview.add(id);
    renderPending(table);
    try {
      const response = await fetch(window.OPERATOR_STATUS_API, {
        method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
        body: JSON.stringify({csrf_token: csrf, order_id: id, status, request_id: newToken()}),
      });
      const data = await response.json().catch(() => ({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) throw new Error(data.message || (status === 'cancelled' ? 'رد سفارش انجام نشد.' : 'تأیید سفارش انجام نشد.'));
      await refreshCatalogPreservingDraft();
      toast(data.message || (status === 'cancelled' ? 'سفارش رد شد.' : 'سفارش تأیید شد.'));
    } catch (error) {
      toast(requestErrorMessage(error, 'تغییر وضعیت سفارش انجام نشد.'), true);
    } finally {
      state.pendingReview.delete(id);
      const latest = selectedTable();
      if (latest) renderPending(latest);
      renderCart();
    }
  }

  function resetCartGestureVisuals() {
    if (state.cartGestureTimer) window.clearTimeout(state.cartGestureTimer);
    state.cartGestureTimer = 0;
    state.cartGesture = null;
    els.cart.classList.remove('is-dragging');
    els.cart.style.removeProperty('transform');
    els.cart.style.removeProperty('transition');
    els.cartBackdrop.style.removeProperty('opacity');
    els.cartBackdrop.style.removeProperty('transition');
  }

  function openCart() {
    const hasPending = Number(selectedTable()?.pending_order_count || 0) > 0;
    if (!mobileMedia.matches || (!state.cart.size && !hasPending)) return;
    resetCartGestureVisuals();
    els.cart.classList.add('is-open');
    els.cart.setAttribute('aria-hidden', 'false');
    els.cartBackdrop.classList.remove('hidden');
    document.body.classList.add('quick-order-cart-open');
    requestAnimationFrame(() => els.cartClose.focus({preventScroll: true}));
  }

  function closeCart(restore = true) {
    resetCartGestureVisuals();
    els.cart.classList.remove('is-open');
    els.cartBackdrop.classList.add('hidden');
    document.body.classList.remove('quick-order-cart-open');
    els.cart.setAttribute('aria-hidden', mobileMedia.matches ? 'true' : 'false');
    if (restore && mobileMedia.matches) els.mobileBar.focus?.({preventScroll: true});
  }

  function settleCartDrag(shouldClose) {
    const gesture = state.cartGesture;
    if (!gesture) return;
    state.cartGesture = null;
    els.cart.classList.remove('is-dragging');
    els.cart.style.transition = `transform ${CART_DRAG_SNAP_MS}ms ease`;
    els.cartBackdrop.style.transition = `opacity ${CART_DRAG_SNAP_MS}ms ease`;
    if (shouldClose) {
      els.cart.style.transform = 'translateY(105%)';
      els.cartBackdrop.style.opacity = '0';
      state.cartGestureTimer = window.setTimeout(() => closeCart(true), CART_DRAG_SNAP_MS + 24);
      return;
    }
    els.cart.style.transform = 'translateY(0)';
    els.cartBackdrop.style.opacity = '1';
    state.cartGestureTimer = window.setTimeout(() => resetCartGestureVisuals(), CART_DRAG_SNAP_MS + 24);
  }

  function beginCartDrag(event) {
    if (!mobileMedia.matches || !els.cart.classList.contains('is-open') || event.isPrimary === false) return;
    if (event.button != null && event.button !== 0) return;
    if (event.target.closest('button,a,input,textarea,select,summary,[contenteditable="true"]')) return;
    state.cartGesture = {pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, startAt: performance.now(), dy: 0, active: false};
    try { event.currentTarget.setPointerCapture?.(event.pointerId); } catch (_) {}
  }

  function moveCartDrag(event) {
    const gesture = state.cartGesture;
    if (!gesture || gesture.pointerId !== event.pointerId) return;
    const dx = event.clientX - gesture.startX;
    const dy = Math.max(0, event.clientY - gesture.startY);
    if (!gesture.active) {
      if (dy < 8) return;
      if (Math.abs(dx) > dy * 0.8) { state.cartGesture = null; return; }
      gesture.active = true;
      els.cart.classList.add('is-dragging');
    }
    if (dy <= 0) return;
    event.preventDefault();
    gesture.dy = dy;
    const height = Math.max(1, els.cart.getBoundingClientRect().height);
    const progress = Math.min(1, dy / height);
    els.cart.style.transform = `translateY(${dy}px)`;
    els.cartBackdrop.style.opacity = String(Math.max(0, 1 - progress * 1.55));
  }

  function endCartDrag(event) {
    const gesture = state.cartGesture;
    if (!gesture || gesture.pointerId !== event.pointerId) return;
    if (!gesture.active) { state.cartGesture = null; return; }
    const elapsed = Math.max(1, performance.now() - gesture.startAt);
    const velocity = gesture.dy / elapsed;
    const height = Math.max(1, els.cart.getBoundingClientRect().height);
    const threshold = Math.min(140, Math.max(88, height * 0.16));
    const shouldClose = gesture.dy >= threshold || (gesture.dy >= 36 && velocity >= 0.65);
    settleCartDrag(shouldClose);
  }

  function cancelCartDrag(event) {
    const gesture = state.cartGesture;
    if (!gesture || gesture.pointerId !== event.pointerId) return;
    if (gesture.active) settleCartDrag(false);
    else state.cartGesture = null;
  }

  async function submitSharedDraftOrder(table) {
    state.submitting = true;
    renderCart();
    try {
      let recovery = state.uncertain ? readUncertain(table.id) : null;
      if (recovery?.kind === 'draft_save') {
        state.uncertain = false;
        await restoreDraft(table.id);
        await flushDraftSave({force:true,allowSubmitting:true});
        recovery = readUncertain(table.id);
      }

      let draftId = Number(recovery?.kind === 'draft_finalize' ? recovery.draftId : state.serverDraftId || 0);
      let draftVersion = Number(recovery?.kind === 'draft_finalize' ? recovery.draftVersion : state.serverDraftVersion || 0);
      if (!(recovery?.kind === 'draft_finalize')) {
        await flushDraftSave({force:true,allowSubmitting:true});
        draftId = Number(state.serverDraftId || 0);
        draftVersion = Number(state.serverDraftVersion || 0);
      }
      if (draftId < 1 || draftVersion < 1) throw new Error('پیش‌نویس سرور آماده ثبت نهایی نیست.');

      if (!(recovery?.kind === 'draft_finalize')) {
        persistUncertain({
          kind:'draft_finalize',requestToken:newToken(),tableId:Number(table.id),
          expectedSessionId:Number(table.session_id||0),note:String(els.note?.value||'').trim(),items:requestLines(),
          draftId,draftVersion,
        });
      }

      let response;
      try {
        response = await fetch(draftApiUrl(), {
          method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json'},
          body:JSON.stringify({csrf_token:csrf,action:'finalize',draft_id:draftId,expected_version:draftVersion}),
        });
      } catch (error) {
        state.uncertain = true;
        throw error;
      }
      const data = await response.json().catch(()=>({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) {
        clearUncertain(table.id);
        const error = new Error(data.message || 'ثبت نهایی پیش‌نویس انجام نشد.');
        error.code = String(data.code || '');
        error.status = Number(response.status || 0);
        if (['version_conflict','session_changed','finalize_rejected','draft_closed'].includes(error.code)) {
          await loadServerDraft(table.id,{skipUncertain:true});
        }
        throw error;
      }
      clearUncertain(table.id);
      discardDraft(table.id);
      clearDraftState();
      navigateOrderSuccess(table,data);
    } catch (error) {
      const networkUnknown = error instanceof TypeError || /fetch|network|load failed/i.test(String(error?.message || ''));
      state.uncertain = networkUnknown || Boolean(readUncertain(table.id));
      toast(networkUnknown ? 'نتیجه ثبت نهایی هنوز مشخص نیست؛ تلاش دوباره همان پیش‌نویس را بررسی می‌کند.' : requestErrorMessage(error,'ثبت سفارش انجام نشد.'),true);
      if (!networkUnknown && ['version_conflict','session_changed','finalize_rejected','draft_closed'].includes(String(error?.code||''))) {
        state.uncertain = false;
        renderDraftStatus('نسخه تازه سرور بارگذاری شد؛ پیش از ثبت دوباره آن را بررسی کنید.');
      }
    } finally {
      state.submitting = false;
      renderCart();
    }
  }

  async function submitOrder() {
    const table = selectedTable();
    const lines = selectedLines();
    if (!table || !lines.length || els.submit.disabled) return;
    if (sharedDraftEnabled) return submitSharedDraftOrder(table);

    state.submitting = true;
    state.requestToken ||= newToken();
    const uncertainRequest = state.uncertain ? readUncertain(table.id) : null;
    const requestPayload = {
      requestToken: state.requestToken,
      tableId: Number(table.id),
      expectedSessionId: uncertainRequest ? Number(uncertainRequest.expectedSessionId || 0) : Number(table.session_id || 0),
      note: els.note.value.trim(),
      items: requestLines(),
    };
    persistUncertain(requestPayload);
    saveDraft();
    renderCart();
    try {
      const response = await fetch(window.STAFF_QUICK_ORDER_API, {
        method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
        body: JSON.stringify({csrf_token: csrf, mode, request_token: requestPayload.requestToken, table_id: requestPayload.tableId, expected_session_id: requestPayload.expectedSessionId, note: requestPayload.note, items: requestPayload.items}),
      });
      const data = await response.json().catch(() => ({}));
      window.SoknaPushRuntime?.handleResponse?.(data);
      if (!response.ok || !data.success) {
        const error = new Error(data.message || 'ثبت سفارش انجام نشد.');
        error.uncertain = [502,504].includes(Number(response.status));
        throw error;
      }
      discardDraft(table.id);
      clearDraftState();
      navigateOrderSuccess(table,data);
    } catch (error) {
      const networkUnknown = error?.uncertain === true || error instanceof TypeError || /fetch|network|load failed/i.test(String(error?.message || ''));
      state.uncertain = networkUnknown;
      if (!networkUnknown) {
        clearUncertain(table.id);
        state.requestToken = '';
        saveDraft();
      }
      toast(networkUnknown ? 'نتیجه ثبت سفارش هنوز مشخص نیست؛ سبد ثابت مانده و تلاش دوباره همان درخواست را بررسی می‌کند.' : requestErrorMessage(error, 'ثبت سفارش انجام نشد.'), true);
      if (!networkUnknown && /قیمت|قابل سفارش|سفارش مهمان منتظر|وضعیت میز|حساب این میز تغییر/.test(error.message)) {
        state.loaded = false;
        window.location.reload();
        return;
      }
    } finally {
      state.submitting = false;
      renderCart();
    }
  }

  async function leavePage(event) {
    if (state.changeTableMode && selectedTable()) {
      event.preventDefault();
      showWorkspace();
      return;
    }
    if (mobileMedia.matches && state.mobileView === 'items') {
      event.preventDefault();
      showMobileCategories();
      return;
    }
    if (!hasDraft()) return;
    event.preventDefault();
    const approved = await confirmAction(`این سفارش هنوز ثبت نشده است و ${digits(cartQuantity())} عدد انتخاب‌شده باقی می‌ماند. پیش‌نویس این میز حفظ می‌شود و بعداً می‌توانید ادامه دهید.`, 'خروج از ثبت سفارش', {okLabel: 'خروج'});
    if (approved) {
      if (sharedDraftEnabled) { try { await flushDraftSave({force:true}); } catch (_) {} }
      window.location.assign(returnUrl);
    }
  }

  document.addEventListener('click', (event) => {
    const clearProxy=event.target.closest('[data-qo-clear-proxy]');if(clearProxy){event.preventDefault();els.clear?.click();clearProxy.closest('details')?.removeAttribute('open');return;}
    const globalNoteProxy=event.target.closest('[data-qo-global-note]');if(globalNoteProxy){event.preventDefault();els.noteToggle?.click();globalNoteProxy.closest('details')?.removeAttribute('open');return;}
    const table = event.target.closest('[data-qo-table]');
    if (table) { selectTable(table.dataset.qoTable); return; }
    const menuButton = event.target.closest('[data-qo-menu]');
    if (menuButton) { selectMenu(menuButton.dataset.qoMenu); return; }
    const category = event.target.closest('[data-qo-category]');
    if (category) {
      if (mobileMedia.matches) selectMobileCategory(category.dataset.qoCategory);
      else { state.category = Number(category.dataset.qoCategory); renderCategories(); syncMobileView(); renderItems(); saveDraft(); }
      return;
    }
    const qty = event.target.closest('[data-qo-delta]');
    if (qty) { event.preventDefault(); event.stopPropagation(); delta(qty.dataset.id, Number(qty.dataset.qoDelta)); return; }
    const add = event.target.closest('[data-qo-add]');
    if (add) { addItem(add.dataset.qoAdd); return; }
    const takeawayDelta = event.target.closest('[data-qo-takeaway-delta]');
    if (takeawayDelta) {
      if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، نحوه سرو قابل تغییر نیست.', true); return; }
      const line=state.cart.get(Number(takeawayDelta.dataset.id));
      if(line&&fulfillment.allowed(line)){ line.takeaway_quantity=Math.max(0,Math.min(Number(line.quantity||0),Number(line.takeaway_quantity||0)+Number(takeawayDelta.dataset.qoTakeawayDelta||0))); state.cart.set(Number(line.id),line); resetToken(); renderCart(); }
      return;
    }
    const takeawayToggle=event.target.closest('[data-qo-takeaway-toggle]');
    if(takeawayToggle){if(state.uncertain)return toast('تا تعیین نتیجه ثبت قبلی، نحوه سرو قابل تغییر نیست.',true);const line=state.cart.get(Number(takeawayToggle.dataset.qoTakeawayToggle));if(line&&fulfillment.allowed(line)){line.takeaway_quantity=Number(line.takeaway_quantity||0)>0?0:1;resetToken();renderCart();}return;}
    const noteToggle = event.target.closest('[data-qo-note-toggle]');
    if (noteToggle) {
      if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، یادداشت قابل تغییر نیست.', true); return; }
      const line = state.cart.get(Number(noteToggle.dataset.qoNoteToggle));
      if (line) { line.noteOpen = !line.noteOpen; state.cart.set(Number(line.id), line); renderCart(); }
      return;
    }
    const pending = event.target.closest('[data-qo-pending-order][data-qo-pending-status]');
    if (pending) { reviewPendingOrder(pending.dataset.qoPendingOrder, pending.dataset.qoPendingStatus); }
  });

  els.items.addEventListener('keydown', (event) => {
    const card = event.target.closest('[data-qo-add]');
    if (card && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); addItem(card.dataset.qoAdd); }
  });
  els.search.addEventListener('input', renderItems);
  els.searchClear.addEventListener('click', () => { els.search.value = ''; els.search.focus(); renderItems(); });
  els.categorySearch?.addEventListener('click', () => {
    if (!mobileMedia.matches) { els.search?.focus?.(); return; }
    state.mobileView = 'items';
    state.searchOpen = true;
    els.search.value = '';
    syncMobileView();
    renderItems();
    if (history.state?.quickOrderView !== 'items') history.pushState({quickOrderView:'items'}, '', window.location.href);
    requestAnimationFrame(() => els.search.focus());
  });
  els.searchToggle.addEventListener('click', () => {
    state.searchOpen = !state.searchOpen;
    els.searchWrap.classList.toggle('is-collapsed', !state.searchOpen && mobileMedia.matches);
    els.searchToggle.setAttribute('aria-expanded', state.searchOpen ? 'true' : 'false');
    if (state.searchOpen) requestAnimationFrame(() => els.search.focus());
    else { els.search.value = ''; renderItems(); }
  });
  els.changeTable.addEventListener('click', () => { if(lateAccounting)return toast('در حالت قلم جاافتاده، میز قابل تغییر نیست.', true); if (state.uncertain) return toast('ابتدا نتیجه ثبت نامشخص سفارش فعلی را تعیین کنید.', true); showTableView(true, 'change'); });
  els.categoryBack.addEventListener('click', () => showMobileCategories());
  els.mobileBar.addEventListener('click', openCart);
  els.mobilePending?.addEventListener('click', openCart);
  els.cartClose.addEventListener('click', () => closeCart());
  els.cartBackdrop.addEventListener('click', () => closeCart());
  const cartHead = els.cart.querySelector('.quick-order-cart-head');
  cartHead?.addEventListener('pointerdown', beginCartDrag);
  cartHead?.addEventListener('pointermove', moveCartDrag);
  cartHead?.addEventListener('pointerup', endCartDrag);
  cartHead?.addEventListener('pointercancel', cancelCartDrag);
  els.back.addEventListener('click', leavePage);
  els.clear.addEventListener('click', () => {
    if (state.uncertain) { toast('تا تعیین نتیجه ثبت قبلی، سبد قابل تغییر نیست.', true); return; }
    if (currentClearUndo()) { restoreClearedCart(); return; }
    if (!state.cart.size && !String(els.note.value || '').trim()) return;
    if (!armClearUndo()) return;
    state.cart.clear();
    state.fulfillmentEditing = false;
    els.note.value = '';
    els.noteWrap.classList.add('hidden');
    els.noteToggle.setAttribute('aria-expanded', 'false');
    state.requestToken = '';
    saveDraft();
    renderCart();
    toast('سبد سفارش پاک شد؛ می‌توانید آن را بازگردانید.');
  });
  els.takeawayTool?.addEventListener('click',()=>{if(lateAccounting)return; if(state.uncertain)return toast('تا تعیین نتیجه ثبت قبلی، نحوه سرو قابل تغییر نیست.',true);state.fulfillmentEditing=!state.fulfillmentEditing;renderCart();});
  els.takeawayDone?.addEventListener('click',()=>{state.fulfillmentEditing=false;renderCart();});
  els.takeawayAll?.addEventListener('click',()=>{if(state.uncertain)return;const all=cartEligibleQuantity()>0&&cartTakeawayQuantity()===cartEligibleQuantity();fulfillment.setAll(state.cart.values(),!all);resetToken();renderCart();});
  els.noteToggle.addEventListener('click', () => {
    const open = els.noteWrap.classList.contains('hidden');
    els.noteWrap.classList.toggle('hidden', !open);
    els.noteToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    updateGeneralNoteUi();
    if (open) requestAnimationFrame(() => els.note.focus());
  });
  els.note.addEventListener('input', () => { resetToken(); updateGeneralNoteUi(); });
  els.cartLines.addEventListener('input', (event) => {
    const input = event.target.closest('[data-qo-note-input]');
    if (!input || state.uncertain) return;
    const line = state.cart.get(Number(input.dataset.qoNoteInput));
    if (!line) return;
    line.note = input.value.slice(0, 500);
    line.noteOpen = true;
    state.cart.set(Number(line.id), line);
    resetToken();
  });
  els.submit.addEventListener('click', submitOrder);
  els.draftCancel?.addEventListener('click', async () => {
    if (!sharedDraftEnabled || state.uncertain || state.draftSaving || state.serverDraftId < 1) return;
    const table=selectedTable(); if(!table)return;
    const approved=await confirmAction('این پیش‌نویس مشترک برای این میز لغو می‌شود و همکاران دیگر هم دیگر آن را نخواهند دید.','لغو پیش‌نویس',{okLabel:'لغو پیش‌نویس',danger:true});
    if(!approved)return;
    try { await cancelServerDraft(Number(table.id),state.serverDraftVersion,{clearCurrent:true}); renderCart(); toast('پیش‌نویس لغو شد.'); }
    catch(error){ if(String(error?.code||'')==='version_conflict') await restoreDraft(Number(table.id)); toast(requestErrorMessage(error,'لغو پیش‌نویس انجام نشد.'),true); }
  });

  mobileMedia.addEventListener?.('change', () => {
    closeCart(false);
    state.mobileView = mobileMedia.matches ? 'categories' : 'items';
    state.searchOpen = !mobileMedia.matches;
    syncMobileView();
    renderCategories();
    renderItems();
    renderCart();
  });

  window.addEventListener('popstate', (event) => {
    if (mobileMedia.matches && state.mobileView === 'items') {
      state.mobileView = 'categories';
      state.searchOpen = false;
      syncMobileView();
      renderItems();
      return;
    }
    if (event.state?.quickOrderView === 'items') selectMobileCategory(state.category, false);
  });
  window.addEventListener('resize', () => syncDesktopWorkspaceHeight(), {passive:true});
  window.addEventListener('pagehide', () => {
    if (state.submitting) return;
    if (sharedDraftEnabled) { if(state.draftSaveTimer)window.clearTimeout(state.draftSaveTimer);state.draftSaveTimer=0;void saveDraftNow({keepalive:true}).catch(()=>{}); }
    else saveDraft();
  });

  loadCatalog();
})();
